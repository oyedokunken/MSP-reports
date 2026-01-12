<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\DeliverReportJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use Carbon\Carbon;
use App\Services\ReportGenerationService;
use App\Services\ReportRegistry;

class ReportConfigController extends Controller
{
    protected ReportGenerationService $reportService;
    protected ReportRegistry $registry;

    public function __construct(ReportGenerationService $reportService, ReportRegistry $registry)
    {
        $this->reportService = $reportService;
        $this->registry = $registry;
    }

    public function getAvailableReports(Request $request)
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

    public function getAvailableReportsForCustomer(Request $request, $customerId)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customerId)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found.'
            ], 404);
        }

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
        $availableReports = collect($allReports)->filter(function ($report) use ($customerIntegrations) {
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
        })->map(function ($report) use ($customerIntegrations, $customerId, $frequency) {
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
                    $hasData = false;
                }
            }
            
            return [
                'id' => $report['id'],
                'report_type' => $report['id'],
                'name' => $report['name'],
                'description' => $report['description'],
                'category' => $report['category'] ?? 'general',
                'frequency_options' => $report['frequencies'] ?? ['on-demand'],
                'formats' => $report['formats'] ?? ['pdf'],
                'icon' => $report['icon'] ?? 'file-text',
                'enabled' => $report['enabled'] ?? true,
                'data_types' => $report['data_types'] ?? [],
                'integration_source' => !empty($matchedIntegrations) ? reset($matchedIntegrations) : 'Multiple',
                'has_data' => $hasData,
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->canonical_name,
                'linked_integrations' => $customerIntegrations,
                'available_reports' => $availableReports,
            ]
        ]);
    }

    public function getCustomerReports(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found.'
            ], 404);
        }

        // Get delivery configuration from nested structure
        $deliveryConfig = $customer->report_configuration['delivery'] ?? [];
        $method = $deliveryConfig['method'] ?? 'email';
        
        $emailDestination = $customer->report_destination 
            ?? $deliveryConfig[$method]['destination'] ?? null;
        
        $ccEmails = $deliveryConfig[$method]['cc'] ?? [];
        
        $schedules = ReportSchedule::where('customer_id', $customer->customer_id)
            ->where('company_id', $company->company_id)
            ->get()
            ->map(function ($schedule) {
                return [
                    'report_schedule_id' => $schedule->report_schedule_id,
                    'report_type' => $schedule->report_type,
                    'frequency' => $schedule->frequency,
                    'time_of_day' => $schedule->time_of_day,
                    'active' => $schedule->active,
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

    public function updateCustomerReports(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json(['status'=>'error','message'=>'Customer not found'], 404);
        }

        $validated = $request->validate([
            'schedules' => 'required|array|min:1',
            'schedules.*.report_type' => 'required|string',
            'schedules.*.frequency' => 'required|string|in:daily,weekly,monthly,yearly,on-demand',
            'schedules.*.time_of_day' => 'nullable|string',
            'schedules.*.active' => 'nullable|boolean',
            'schedules.*.schedule_config' => 'nullable|array',
        ]);

        $schedulesCreated = 0;
        $schedulesUpdated = 0;

        foreach ($validated['schedules'] as $scheduleData) {
            
            $existingSchedule = ReportSchedule::where('customer_id', $customer->customer_id)
                ->where('company_id', $company->company_id)
                ->where('report_type', $scheduleData['report_type'])
                ->first();

            if ($existingSchedule) {
                
                $existingSchedule->update([
                    'frequency' => $scheduleData['frequency'],
                    'time_of_day' => $scheduleData['time_of_day'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'schedule_config' => $scheduleData['schedule_config'] ?? null,
                ]);
                $schedulesUpdated++;
            } else {
                
                ReportSchedule::create([
                    'company_id' => $company->company_id,
                    'customer_id' => $customer->customer_id,
                    'report_type' => $scheduleData['report_type'],
                    'frequency' => $scheduleData['frequency'],
                    'time_of_day' => $scheduleData['time_of_day'] ?? '09:00',
                    'schedule_config' => $scheduleData['schedule_config'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'timezone' => $company->settings['reports']['report_timezone'] ?? 'UTC',
                ]);
                $schedulesCreated++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Report schedules updated successfully.',
            'data' => [
                'schedules_created' => $schedulesCreated,
                'schedules_updated' => $schedulesUpdated
            ]
        ]);
    }

    public function updateCustomerReportDestination(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found.'
            ], 404);
        }

        $validated = $request->validate([
            'method' => 'required|string|in:email,api,download',
            'destination' => 'required|string|email',
            'cc' => 'nullable|array',
            'cc.*' => 'nullable|email'
        ]);

        $reportConfig = $customer->report_configuration ?? [];
        
        // Store in nested structure expected by DeliveryManager
        $reportConfig['delivery'] = [
            'method' => $validated['method'],
            $validated['method'] => [
                'destination' => $validated['destination'],
                'cc' => $validated['cc'] ?? [],
            ]
        ];

        $customer->report_configuration = $reportConfig;
        $customer->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer report delivery destination updated.',
            'data' => $customer->report_configuration['delivery']
        ]);
    }

    public function generateReport(Request $request, $customer_id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customer_id)
            ->where('company_id', $company->company_id)
            ->firstOrFail();

        // Get custom date range from request if provided
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;
        $reportType = $request->input('report_type');
        
        $schedules = ReportSchedule::where('customer_id', $customer->customer_id)
            ->where('company_id', $company->company_id)
            ->where('active', true);
        
        // Filter by report type if specified
        if ($reportType) {
            $schedules->where('report_type', $reportType);
        }
        
        $schedules = $schedules->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active report schedules for this customer. Please create schedules first.'
            ], 400);
        }

        $generatedReports = [];
        $errors = [];

        foreach ($schedules as $schedule) {
            try {
                // Generate report with custom dates only if explicitly provided
                // Otherwise, let the schedule's frequency determine the date range
                if ($startDate && $endDate) {
                    $generatedReport = $this->reportService->generateReport($schedule, $startDate, $endDate);
                } else {
                    $generatedReport = $this->reportService->generateReport($schedule);
                }
                
                DeliverReportJob::dispatch($generatedReport);
                
                $customer = $generatedReport->customer;
                $deliveryConfig = $customer->report_configuration['delivery'] ?? [];
                $method = $deliveryConfig['method'] ?? 'email';
                $emailDestination = $customer->report_destination 
                    ?? $deliveryConfig[$method]['destination'] 
                    ?? null;
                
                $reportArray = $generatedReport->toArray();
                $reportArray['email_status'] = 'queued';
                $reportArray['email_destination'] = $emailDestination;
                $reportArray['customer_name'] = $customer->canonical_name;
                
                $generatedReports[] = $reportArray;

                
                $schedule->update(['last_generated_at' => now()]);

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
    public function generateFromSchedule(Request $request, $schedule_id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Find the schedule
        $schedule = ReportSchedule::where('report_schedule_id', $schedule_id)
            ->where('company_id', $company->company_id)
            ->firstOrFail();

        // Get custom date range from request if provided
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;

        try {
            // Generate report with custom dates if provided
            if ($startDate && $endDate) {
                $generatedReport = $this->reportService->generateReport($schedule, $startDate, $endDate);
            } else {
                $generatedReport = $this->reportService->generateReport($schedule);
            }
            
            // Queue delivery
            DeliverReportJob::dispatch($generatedReport);
            
            // Get customer email destination
            $customer = $generatedReport->customer;
            $deliveryConfig = $customer->report_configuration['delivery'] ?? [];
            $method = $deliveryConfig['method'] ?? 'email';
            $emailDestination = $customer->report_destination 
                ?? $deliveryConfig[$method]['destination'] 
                ?? null;
            
            // Update last generated timestamp
            $schedule->update(['last_generated_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Report generated successfully',
                'data' => [
                    'report_id' => $generatedReport->generated_report_id,
                    'report_type' => $generatedReport->report_type,
                    'customer_name' => $customer->canonical_name,
                    'email_status' => 'queued',
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
    public function updateSchedule(Request $request, $schedule_id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Find the schedule
        $schedule = ReportSchedule::where('report_schedule_id', $schedule_id)
            ->where('company_id', $company->company_id)
            ->firstOrFail();

        // Validate input
        $validated = $request->validate([
            'report_type' => 'nullable|string',
            'frequency' => 'nullable|string|in:daily,weekly,monthly,yearly,on-demand',
            'time_of_day' => 'nullable|string',
            'active' => 'nullable|boolean',
            'schedule_config' => 'nullable|array',
        ]);

        // Update schedule fields
        if (isset($validated['report_type'])) {
            $schedule->report_type = $validated['report_type'];
        }

        if (isset($validated['frequency'])) {
            $schedule->frequency = $validated['frequency'];
        }

        if (isset($validated['time_of_day'])) {
            $schedule->time_of_day = $validated['time_of_day'];
        }

        if (isset($validated['active'])) {
            $schedule->active = $validated['active'];
        }

        if (isset($validated['schedule_config'])) {
            // Merge with existing schedule_config to preserve included_sections
            $existingConfig = $schedule->schedule_config ?? [];
            $schedule->schedule_config = array_merge($existingConfig, $validated['schedule_config']);
        }

        $schedule->save();

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
}
