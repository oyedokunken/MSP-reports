<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Report\BatchDeleteReportsAction;
use App\Actions\Report\DeliverReportAction;
use App\Actions\Report\EmailReportAction;
use App\Actions\Report\GenerateShareLinkAction;
use App\Actions\Report\GetScheduledReportsAction;
use App\Actions\Report\GetReportHistoryAction;
use App\Actions\Report\DownloadReportAction;
use App\Actions\Report\DeleteReportScheduleAction;
use App\Actions\Report\DeleteReportAction;
use App\Http\Requests\Report\BatchDeleteReportsRequest;
use App\Http\Requests\Report\DiscardReportsRequest;
use App\Http\Requests\Report\EmailReportRequest;
use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportAnalyticsController extends Controller
{
    public function getScheduledReports(Request $request, GetScheduledReportsAction $action): JsonResponse
    {
        $authUser = $request->user();
        $includeInactive = $request->boolean('include_inactive', false);
        
        $schedules = $action->execute($authUser, $includeInactive);

        $formattedSchedules = $schedules->map(function (ReportSchedule $schedule) {
            /** @var \App\Models\Customer $customer */
            $customer = $schedule->customer;
            
            $emailDestination = $customer->report_destination 
                ?? $customer->report_configuration['delivery']['destination'] ?? null;
            
            $ccEmails = $customer->report_configuration['delivery']['cc'] ?? [];
            
            return [
                'report_schedule_id' => $schedule->report_schedule_id,
                'customer_id' => $schedule->customer_id,
                'customer_name' => $customer->canonical_name ?? 'Unknown',
                'report_type' => $schedule->report_type,
                'frequency' => $schedule->frequency,
                'next_scheduled_at' => $schedule->next_scheduled_at,
                'last_generated_at' => $schedule->last_generated_at,
                'active' => $schedule->active,
                'email_destination' => $emailDestination,
                'cc_emails' => $ccEmails,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedSchedules,
            'meta' => [
                'total_count' => $schedules->count(),
                'scoped_to_customers' => $authUser->scoped_customers,
                'is_scoped_user' => !empty($authUser->scoped_customers),
            ]
        ], 200);
    }
    
    public function getReportHistory(Request $request, GetReportHistoryAction $action): JsonResponse
    {
        $authUser = $request->user();

        $filters = [
            'per_page' => (int) $request->input('per_page', 50),
            'customer_id' => $request->input('customer_id') ? (int) $request->input('customer_id') : null,
        ];

        $reports = $action->execute($authUser->company_id, $filters);

        $formattedReports = $reports->map(function (GeneratedReport $report) {
            return [
                'generated_report_id' => $report->generated_report_id,
                'customer_id' => $report->customer_id,
                'customer_name' => $report->customer->canonical_name ?? 'Unknown',
                'report_type' => $report->reportSchedule->report_type ?? 'Unknown',
                'generated_at' => $report->generated_at,
                'status' => $report->status,
                'file_size' => $report->file_size,
                'delivered' => $report->delivered,
                'delivered_at' => $report->delivered_at,
                'delivery_method' => $report->delivery_method,
                'error_message' => $report->error_message,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'reports' => $formattedReports,
                'pagination' => [
                    'total' => $reports->total(),
                    'per_page' => $reports->perPage(),
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                ]
            ]
        ], 200);
    }

    public function downloadReport(Request $request, int $id, DownloadReportAction $action): StreamedResponse|JsonResponse
    {
        try {
            return $action->execute($request->user()->company_id, $id);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function emailReport(EmailReportRequest $request, int $id, EmailReportAction $action): JsonResponse
    {
        $authUser = $request->user();

        $report = GeneratedReport::where('generated_report_id', $id)
            ->where('company_id', $authUser->company_id)
            ->with(['customer', 'reportSchedule', 'company'])
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report not found or access denied.'
            ], 404);
        }

        try {
            $result = $action->execute($report, $request->validated()['emails'], $authUser);
            return response()->json([
                'status' => 'success',
                'message' => 'Report has been queued for email delivery.',
                'data' => $result
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to queue report email', [
                'report_id' => $report->generated_report_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to queue report for delivery: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deliver a previously generated report using configured delivery methods
     */
    public function deliverReport(Request $request, int $id, DeliverReportAction $action): JsonResponse
    {
        $authUser = $request->user();

        $report = GeneratedReport::where('generated_report_id', $id)
            ->where('company_id', $authUser->company_id)
            ->with(['customer', 'reportSchedule', 'company'])
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report not found or access denied.'
            ], 404);
        }

        try {
            $result = $action->execute($report, $authUser);

            return response()->json([
                'status' => 'success',
                'message' => 'Report has been queued for delivery.',
                'data' => $result
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to initiate report delivery', [
                'report_id' => $report->generated_report_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initiate delivery: ' . $e->getMessage()
            ], 500);
        }
    }


    public function deleteSchedule(Request $request, int $id, DeleteReportScheduleAction $action): JsonResponse
    {
        try {
            $result = $action->execute($request->user()->company_id, $id);

            return response()->json([
                'status' => 'success',
                'message' => "Schedule deleted successfully: {$result['report_type']} for {$result['customer_name']}" . 
                            ($result['template_deleted'] ? " (template also deleted)" : ""),
                'data' => $result
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function deleteReport(Request $request, int $id, DeleteReportAction $action): JsonResponse
    {
        try {
            $deletedId = $action->execute($request->user()->company_id, $id);

            return response()->json([
                'status' => 'success',
                'message' => 'Report deleted successfully',
                'data' => [
                    'deleted_report_id' => $deletedId
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function batchDelete(BatchDeleteReportsRequest $request, BatchDeleteReportsAction $action): JsonResponse
    {
        $validated = $request->validated();

        $deletedCount = $action->execute($request->user()->company_id, $validated['report_ids']);

        return response()->json([
            'status' => 'success',
            'message' => "Successfully deleted {$deletedCount} reports",
            'data' => [
                'deleted_count' => $deletedCount
            ]
        ], 200);
    }

    public function discardReports(DiscardReportsRequest $request, BatchDeleteReportsAction $action): JsonResponse
    {
        $reportIds = $request->validated()['report_ids'] ?? [];
        
        if (empty($reportIds)) {
            return response()->json([
                'status' => 'success',
                'message' => 'No reports to discard'
            ]);
        }

        $deletedCount = $action->execute($request->user()->company_id, $reportIds, true);

        return response()->json([
            'status' => 'success',
            'message' => "Discarded {$deletedCount} reports successfully",
            'data' => [
                'discarded_count' => $deletedCount
            ]
        ]);
    }

    /**
     * Generate a quick share link for a report (valid for 1 hour)
     */
    public function generateShareLink(Request $request, int $id, GenerateShareLinkAction $action): JsonResponse
    {
        $authUser = $request->user();

        $report = GeneratedReport::where('generated_report_id', $id)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report not found or access denied.'
            ], 404);
        }

        try {
            $result = $action->execute($report, $authUser, $request->getSchemeAndHttpHost());

            return response()->json([
                'status' => 'success',
                'message' => 'Share link generated successfully.',
                'share_url' => $result['share_url'],
                'expires_at' => $result['expires_at'],
                'expires_in_minutes' => $result['expires_in_minutes'],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Download a shared report using a token (no authentication required)
     */
    public function downloadSharedReport(Request $request, string $token, DownloadReportAction $action): StreamedResponse|JsonResponse
    {
        try {
            return $action->executeShared($token);
        } catch (ValidationException $e) {
            $status = $e->getMessage() === 'This share link has expired.' ? 410 : 
                     ($e->getMessage() === 'Invalid share link signature.' ? 403 : 404);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * Preview the email that will be sent with a report
     */
    public function previewEmail(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();

        $report = GeneratedReport::where('generated_report_id', $id)
            ->where('company_id', $authUser->company_id)
            ->with(['customer', 'reportSchedule', 'company'])
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report not found or access denied.'
            ], 404);
        }

        $company = $report->company;
        $customer = $report->customer;
        $branding = $company->settings['white_label'] ?? [];

        // Build email preview data
        $period = $report->report_period;
        $periodString = 'N/A';
        
        if (is_array($period) && isset($period['start']) && isset($period['end'])) {
            $start = \Carbon\Carbon::parse($period['start'])->format('F j, Y');
            $end = \Carbon\Carbon::parse($period['end'])->format('F j, Y');
            $periodString = "{$start} - {$end}";
        }

        // Get delivery destination
        $destination = $customer->report_destination 
            ?? $customer->report_configuration['delivery']['destination'] 
            ?? null;
        $ccEmails = $customer->report_configuration['delivery']['cc'] ?? [];

        // Render email HTML
        $emailHtml = view('emails.report-delivery', [
            'customerName' => $customer->canonical_name,
            'reportType' => $report->reportSchedule->report_type ?? 'Report',
            'reportPeriod' => $periodString,
            'generatedAt' => $report->generated_at->format('F j, Y'),
            'companyName' => $branding['company_name'] ?? $company->name,
            'supportEmail' => $branding['support_email'] ?? config('mail.from.address'),
            'supportPhone' => $branding['support_phone'] ?? '',
            'footerText' => $branding['footer_text'] ?? '',
            'primaryColor' => $branding['primary_color'] ?? '#1a56db',
        ])->render();

        return response()->json([
            'status' => 'success',
            'data' => [
                'subject' => $branding['report_header_text'] ?? 'Your Monthly Service Report',
                'from' => [
                    'name' => $branding['email_from_name'] ?? $company->name,
                    'email' => config('mail.from.address'),
                ],
                'to' => $destination,
                'cc' => $ccEmails,
                'customer_name' => $customer->canonical_name,
                'report_type' => $report->reportSchedule->report_type ?? 'Report',
                'report_period' => $periodString,
                'generated_at' => $report->generated_at->format('F j, Y'),
                'attachment_name' => $report->file_path ? basename($report->file_path) : 'Report.pdf',
                'html' => $emailHtml,
            ]
        ]);
    }
}
