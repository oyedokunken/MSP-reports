<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Report\BulkGenerateReportsAction;
use App\Actions\Report\GenerateReportAction;
use App\Actions\Report\ResetScheduleAction;
use App\Actions\Report\UpdateCustomerReportsAction;
use App\Actions\Report\UpdateScheduleAction;
use App\Http\Requests\Report\BulkGenerateReportsRequest;
use App\Http\Requests\Report\GenerateReportRequest;
use App\Http\Requests\Report\UpdateCustomerReportsRequest;
use App\Http\Requests\Report\UpdateReportDestinationRequest;
use App\Http\Requests\Report\UpdateScheduleRequest;
use App\Http\Requests\Report\GetAvailableReportsRequest;
use App\Http\Requests\Report\GetAvailableReportsForCustomerRequest;
use App\Http\Requests\Report\GetCustomerReportsRequest;
use App\Http\Requests\Report\ResetScheduleRequest;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Services\ReportGenerationService;
use App\Services\ReportRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ReportConfigController extends Controller
{
    protected ReportGenerationService $reportService;
    protected ReportRegistry $registry;

    public function __construct(ReportGenerationService $reportService, ReportRegistry $registry)
    {
        $this->reportService = $reportService;
        $this->registry = $registry;
    }

    public function getAvailableReports(GetAvailableReportsRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Use ReportRegistry instead of manual scanning
        $reports = $this->registry->getAvailable($company->company_id);

        $availableReports = collect($reports)->map(function ($report) {
            return [
                'report_type' => $report['id'],
                'name' => $report['name'],
                'description' => $report['description'],
                'category' => $report['category'] ?? 'general',
                'frequency_options' => $report['frequencies'] ?? ['on-demand'],
                'formats' => $report['formats'] ?? ['pdf'],
                'icon' => $report['icon'] ?? 'file-text',
                'enabled' => $report['enabled'] ?? true,
                'data_types' => $report['data_types'] ?? [],
                'required_integrations' => $report['required_integrations'] ?? [],
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => $availableReports
        ]);
    }

    public function getAvailableReportsForCustomer(GetAvailableReportsForCustomerRequest $request, int $customerId): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customerId)
            ->where('company_id', $company->company_id)
            ->first();

        // No need for "if (!$customer)" because authorize() handles it

        // Get frequency parameter for data availability check
        $frequency = $request->input('frequency', 'monthly');

        // Get customer's linked integrations
        $customerIntegrations = $customer->integrationCustomers()
            ->with('integration')
            ->get()
            ->pluck('integration.integration_type')
            ->unique()
            ->values()
            ->all();

        // Get all reports
        $allReports = $this->registry->getAvailable($company->company_id);

        // Filter reports based on customer's integrations
        $availableReports = array_filter($allReports, function (array $report) use ($customerIntegrations): bool {
            $requiredIntegrations = $report['required_integrations'] ?? [];

            // If no required integrations specified, report is always available
            if (empty($requiredIntegrations)) {
                return true;
            }

            // Special case: 'multiple' means customer needs more than one integration
            if (in_array('multiple', $requiredIntegrations)) {
                return count($customerIntegrations) > 1;
            }

            // Check if customer has at least one of the required integrations
            return !empty(array_intersect($requiredIntegrations, $customerIntegrations));
        });

        /** @var array<int, array<string, mixed>> $data */
        $data = array_map(function (array $report) use ($customerIntegrations, $customerId, $frequency): array {
            $requiredIntegrations = $report['required_integrations'] ?? [];
            $matchedIntegrations = array_intersect($requiredIntegrations, $customerIntegrations);

            // Check if report has data for the given frequency
            $hasData = false;
            if (isset($report['class']) && method_exists($report['class'], 'hasDataForFrequency')) {
                try {
                    $hasData = $report['class']::hasDataForFrequency($customerId, $frequency);
                } catch (\Exception $e) {
                    \Log::warning("Failed to check data availability for {$report['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'id' => $report['id'],
                'name' => $report['name'],
                'description' => $report['description'] ?? '',
                'category' => $report['category'] ?? 'General',
                'matched_integrations' => array_values($matchedIntegrations),
                'required_integrations' => $report['required_integrations'] ?? [],
                'has_data' => $hasData,
                'is_available' => true,
                'metadata' => $report['metadata'] ?? [],
            ];
        }, $availableReports);

        return response()->json([
            'status' => 'success',
            'data' => array_values($data),
        ]);
    }

    public function getCustomerReports(GetCustomerReportsRequest $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        // No need for "if (!$customer)" because authorize() handles it

        $emailDestination = $customer->report_destination
            ?? $customer->report_configuration['delivery']['destination'] ?? null;

        $ccEmails = $customer->report_configuration['delivery']['cc'] ?? [];

        $schedules = ReportSchedule::where('customer_id', $customer->customer_id)
            ->where('company_id', $company->company_id)
            ->with('template')
            ->get()
            ->map(function ($schedule) {
                return [
                    'report_schedule_id' => $schedule->report_schedule_id,
                    'report_type' => $schedule->report_type,
                    'frequency' => $schedule->frequency,
                    'time_of_day' => $schedule->time_of_day,
                    'active' => $schedule->active,
                    'is_customized' => $schedule->is_customized,
                    'template_id' => $schedule->template_id,
                    'template_name' => $schedule->template->template_name ?? null,
                    'template_description' => $schedule->template->description ?? null,
                    'next_scheduled_at' => $schedule->next_scheduled_at,
                    'last_generated_at' => $schedule->last_generated_at,
                    'schedule_config' => $schedule->schedule_config,
                    'day_of_month' => $schedule->schedule_config['day_of_month'] ?? null,
                    'day_of_week' => $schedule->schedule_config['day_of_week'] ?? null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->canonical_name,
                'email_destination' => $emailDestination,
                'cc_emails' => $ccEmails,
                'report_schedules' => $schedules
            ]
        ]);
    }

    public function updateCustomerReports(UpdateCustomerReportsRequest $request, int $id, UpdateCustomerReportsAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        // No need for "if (!$customer)" because authorize() handles it

        $result = $action->execute($company, $customer, $request->validated()['schedules']);

        return response()->json([
            'status' => 'success',
            'message' => 'Report schedules updated successfully.',
            'data' => $result
        ]);
    }

    public function updateCustomerReportDestination(UpdateReportDestinationRequest $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        // No need for "if (!$customer)" because authorize() handles it

        $validated = $request->validated();

        $reportConfig = $customer->report_configuration ?? [];
        $reportConfig['delivery'] = $validated;

        $customer->report_configuration = $reportConfig;
        $customer->report_destination = $validated['destination'];
        $customer->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer report delivery destination updated.',
            'data' => $customer->report_configuration['delivery']
        ]);
    }

    public function generateReport(GenerateReportRequest $request, int $customer_id, GenerateReportAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customer_id)
            ->where('company_id', $company->company_id)
            ->firstOrFail();

        $reportType = $request->input('report_type');

        $schedulesQuery = ReportSchedule::where('customer_id', $customer->customer_id)
            ->where('company_id', $company->company_id)
            ->where('active', true)
            ->with('template');

        if ($reportType) {
            $schedulesQuery->where('report_type', $reportType);
        }

        $schedules = $schedulesQuery->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active report schedules for this customer. Please create schedules first.'
            ], 400);
        }

        $generatedReports = [];
        $errors = [];

        /** @var ReportSchedule $schedule */
        foreach ($schedules as $schedule) {
            try {
                $generatedReport = $action->execute($schedule, [
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'frequency' => $request->input('frequency'),
                    'preview_only' => $request->boolean('preview_only'),
                ]);

                $customer = $generatedReport->customer;
                $emailDestination = $customer->report_destination
                    ?? $customer->report_configuration['delivery']['destination']
                    ?? null;

                $reportName = $schedule->template ? $schedule->template->template_name : ucfirst(str_replace('_', ' ', (string) $schedule->report_type));

                $generatedReports[] = [
                    'generated_report_id' => $generatedReport->generated_report_id,
                    'report_id' => $generatedReport->generated_report_id,
                    'report_type' => $schedule->report_type,
                    'report_name' => $reportName,
                    'customer_name' => $customer->canonical_name,
                    'email_destination' => $emailDestination,
                    'email_status' => $request->boolean('preview_only') ? 'preview' : 'queued'
                ];
            } catch (\Exception $e) {
                $errors[] = "Error generating {$schedule->report_type}: " . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Report generation completed.',
            'data' => [
                'generated' => count($generatedReports),
                'errors' => $errors,
                'reports' => $generatedReports
            ]
        ]);
    }

    /**
     * Generate report from a specific schedule
     */
    public function generateFromSchedule(GenerateReportRequest $request, int $schedule_id, GenerateReportAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $schedule = ReportSchedule::where('report_schedule_id', $schedule_id)
            ->where('company_id', $company->company_id)
            ->with('template')
            ->firstOrFail();

        try {
            $generatedReport = $action->execute($schedule, [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'frequency' => $request->input('frequency'),
                'preview_only' => $request->boolean('preview_only'),
            ]);

            $customer = $generatedReport->customer;
            $emailDestination = $customer->report_destination
                ?? $customer->report_configuration['delivery']['destination']
                ?? null;

            return response()->json([
                'status' => 'success',
                'message' => 'Report generated successfully',
                'data' => [
                    'report_id' => $generatedReport->generated_report_id,
                    'report_type' => $schedule->report_type,
                    'report_name' => $schedule->template ? $schedule->template->template_name : ucfirst(str_replace('_', ' ', (string) $schedule->report_type)),
                    'customer_name' => $customer->canonical_name,
                    'email_status' => $request->boolean('preview_only') ? 'preview' : 'queued',
                    'email_destination' => $emailDestination,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an individual schedule's settings
     */
    public function updateSchedule(UpdateScheduleRequest $request, int $schedule_id, UpdateScheduleAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $schedule = ReportSchedule::where('report_schedule_id', $schedule_id)
            ->where('company_id', $company->company_id)
            ->firstOrFail();

        $schedule = $action->execute($schedule, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule updated successfully',
            'data' => [
                'report_schedule_id' => $schedule->report_schedule_id,
                'report_type' => $schedule->report_type,
                'frequency' => $schedule->frequency,
                'time_of_day' => $schedule->time_of_day,
                'active' => $schedule->active,
                'schedule_config' => $schedule->schedule_config,
            ]
        ]);
    }

    /**
     * Reset an individual schedule to its template settings
     */
    public function resetSchedule(ResetScheduleRequest $request, int $schedule_id, ResetScheduleAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $schedule = ReportSchedule::where('report_schedule_id', $schedule_id)
            ->where('company_id', $company->company_id)
            ->with('template')
            ->firstOrFail();

        try {
            $schedule = $action->execute($schedule);

            return response()->json([
                'status' => 'success',
                'message' => 'Schedule reset to template successfully',
                'data' => $schedule
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate reports for multiple customers at once (bulk generation)
     */
    public function bulkGenerateReports(BulkGenerateReportsRequest $request, BulkGenerateReportsAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validated = $request->validated();

        try {
            $results = $action->execute($company, $validated['customer_ids'], $validated);

            $statusMessage = $results['successful'] > 0
                ? "Bulk report generation completed. {$results['successful']} customers processed successfully."
                : "Bulk report generation failed. No reports were generated.";

            return response()->json([
                'status' => $results['successful'] > 0 ? 'success' : 'error',
                'message' => $statusMessage,
                'data' => $results
            ], $results['successful'] > 0 ? 200 : 400);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => ['missing_customer_ids' => $e->errors()['customer_ids'] ?? []]
            ], 400);
        }
    }
}
