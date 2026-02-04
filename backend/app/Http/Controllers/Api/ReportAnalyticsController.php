<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Report\BatchDeleteReportsAction;
use App\Actions\Report\DeliverReportAction;
use App\Actions\Report\EmailReportAction;
use App\Actions\Report\GenerateShareLinkAction;
use App\Http\Requests\Report\BatchDeleteReportsRequest;
use App\Http\Requests\Report\DiscardReportsRequest;
use App\Http\Requests\Report\EmailReportRequest;
use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\ReportShareToken;
use App\Services\ReportNamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportAnalyticsController extends Controller
{
    public function getScheduledReports(Request $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $includeInactive = $request->boolean('include_inactive', false);
        
        $query = ReportSchedule::where('company_id', $authUser->company_id);
        
        if (!$includeInactive) {
            $query->where('active', true);
        }

        $schedules = $query->with('customer:customer_id,canonical_name,report_destination,report_configuration')
            ->orderBy('next_scheduled_at', 'asc')
            ->get()
            ->map(function (\App\Models\ReportSchedule $schedule) {
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
            'data' => $schedules,
            'meta' => [
                'total_count' => $schedules->count(),
                'scoped_to_customers' => $authUser->scoped_customers,
                'is_scoped_user' => !empty($authUser->scoped_customers),
            ]
        ], 200);
    }
    
    public function getReportHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $perPage = $request->input('per_page', 50);
        $customerId = $request->input('customer_id');
        
        $query = GeneratedReport::where('company_id', $authUser->company_id);
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        $reports = $query->with([
                'customer:customer_id,canonical_name',
                'reportSchedule:report_schedule_id,report_type',
            ])
            ->orderBy('generated_at', 'desc')
            ->paginate($perPage);

        $formattedReports = $reports->map(function (\App\Models\GeneratedReport $report) {
            // Check if file exists for completed reports
            if ($report->status === 'completed' && $report->file_path) {
                if (!Storage::disk('reports')->exists($report->file_path)) {
                    // Mark report as failed if file is missing
                    $report->update([
                        'status' => 'failed',
                        'error_message' => 'Report file not found on disk',
                    ]);
                    
                    Log::warning('Report file missing, marked as failed', [
                        'report_id' => $report->generated_report_id,
                        'file_path' => $report->file_path,
                        'customer' => $report->customer->canonical_name ?? 'Unknown',
                    ]);
                }
            }
            
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

    public function downloadReport(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $report = GeneratedReport::withTrashed()
            ->where('generated_report_id', $id)
            ->where('company_id', $authUser->company_id)
            ->with(['customer:customer_id,canonical_name', 'reportSchedule:report_schedule_id,report_type,frequency'])
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report not found or access denied.'
            ], 404);
        }

        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report file not found.'
            ], 404);
        }

        // Use ReportNamingService for consistent filename generation
        $namingService = app(ReportNamingService::class);
        $downloadFilename = 'Report.pdf';
        
        if ($report->reportSchedule) {
            $downloadFilename = $namingService->getDownloadName($report->reportSchedule, $report->company, $report->customer, $report->generated_at) . '.pdf';
        } else {
            // Fallback for reports without schedules (unlikely in current system)
            $filename = basename($report->file_path);
            $downloadFilename = $filename;
        }

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
            'X-Report-Name' => $downloadFilename,
            'X-Customer' => $report->customer->canonical_name ?? 'N/A',
            'X-Generated-At' => $report->generated_at->toDateTimeString(),
        ]);
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


    public function deleteSchedule(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        $schedule = ReportSchedule::where('report_schedule_id', $id)
            ->where('company_id', $authUser->company_id)
            ->with('customer:customer_id,canonical_name')
            ->first();

        if (!$schedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Schedule not found or access denied.'
            ], 404);
        }

        $customerName = $schedule->customer->canonical_name ?? 'Unknown';
        $reportType = $schedule->report_type;
        $templateId = $schedule->template_id;

        // Delete the schedule
        $schedule->delete();

        // If schedule has a template_id, also delete the template
        $templateDeleted = false;
        if ($templateId) {
            try {
                $template = \App\Models\ReportTemplate::where('template_id', $templateId)
                    ->where('company_id', $authUser->company_id)
                    ->first();
                
                if ($template) {
                    $template->delete();
                    $templateDeleted = true;
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to delete associated template', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Schedule deleted successfully: {$reportType} for {$customerName}" . 
                        ($templateDeleted ? " (template also deleted)" : ""),
            'data' => [
                'deleted_schedule_id' => $id,
                'customer_name' => $customerName,
                'report_type' => $reportType,
                'template_deleted' => $templateDeleted,
            ]
        ], 200);
    }

    public function deleteReport(Request $request, int $id): \Illuminate\Http\JsonResponse
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

        // Delete the PDF file if it exists
        if ($report->file_path && Storage::disk('reports')->exists($report->file_path)) {
            Storage::disk('reports')->delete($report->file_path);
        }

        $reportId = $report->generated_report_id;
        $report->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Report deleted successfully',
            'data' => [
                'deleted_report_id' => $reportId
            ]
        ], 200);
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
    public function downloadSharedReport(Request $request, string $token): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $shareToken = ReportShareToken::where('token', $token)
            ->with(['generatedReport.customer', 'generatedReport.reportSchedule'])
            ->first();

        if (!$shareToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid share link.'
            ], 404);
        }

        if ($shareToken->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This share link has expired.'
            ], 410);
        }

        // Verify HMAC signature to detect tampering
        if ($shareToken->signature && !$shareToken->verifySignature()) {
            Log::warning('Share link signature verification failed', [
                'token' => $token,
                'report_id' => $shareToken->generated_report_id,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid share link.'
            ], 403);
        }

        $report = $shareToken->generatedReport;

        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report file not found.'
            ], 404);
        }

        // Use ReportNamingService for consistent filename generation
        $namingService = app(ReportNamingService::class);
        $downloadFilename = 'Report.pdf';
        
        if ($report->reportSchedule) {
            $downloadFilename = $namingService->getDownloadName($report->reportSchedule, $report->company, $report->customer, $report->generated_at) . '.pdf';
        } else {
            $downloadFilename = basename($report->file_path);
        }

        Log::info('Shared report downloaded', [
            'report_id' => $report->generated_report_id,
            'token' => $token,
        ]);

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Preview the email that will be sent with a report
     */
    public function previewEmail(Request $request, int $id): \Illuminate\Http\JsonResponse
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
