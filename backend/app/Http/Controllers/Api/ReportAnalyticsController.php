<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use App\Models\ReportShareToken;
use App\Jobs\DeliverReportJob;

class ReportAnalyticsController extends Controller
{
    public function getScheduledReports(Request $request)
    {
        $authUser = $request->user();

        $schedules = ReportSchedule::where('company_id', $authUser->company_id)
            ->with('customer:customer_id,canonical_name,report_destination,report_configuration')
            ->orderBy('next_scheduled_at', 'asc')
            ->get()
            ->map(function ($schedule) {
                $customer = $schedule->customer;
                
                $deliveryConfig = $customer->report_configuration['delivery'] ?? [];
                $method = $deliveryConfig['method'] ?? 'email';
                
                $emailDestination = $customer->report_destination 
                    ?? $deliveryConfig[$method]['destination'] ?? null;
                
                $ccEmails = $deliveryConfig[$method]['cc'] ?? [];
                
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
    
    public function getReportHistory(Request $request)
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

        $formattedReports = $reports->map(function ($report) {
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

    public function downloadReport(Request $request, $id)
    {
        $authUser = $request->user();

        $report = GeneratedReport::withTrashed()
            ->where('generated_report_id', $id)
            ->where('company_id', $authUser->company_id)
            ->with(['customer:customer_id,canonical_name', 'reportSchedule:report_schedule_id,report_type'])
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

        $filename = basename($report->file_path);
        
        $customerName = str_replace(' ', '_', $report->customer->canonical_name ?? 'Customer');
        $customerName = str_replace(['/', '\\'], '-', $customerName); // Remove slashes
        $generatedDate = $report->generated_at->format('Y-m-d');
        $reportType = $report->reportSchedule ? str_replace('_', ' ', $report->reportSchedule->report_type) : 'Report';
        $reportType = str_replace(['/', '\\'], '-', $reportType); // Remove slashes
        $downloadFilename = "{$customerName}_{$reportType}_{$generatedDate}.pdf";

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
            'X-Report-Name' => $downloadFilename,
            'X-Customer' => $report->customer->canonical_name ?? 'N/A',
            'X-Generated-At' => $report->generated_at->toDateTimeString(),
        ]);
    }

    public function emailReport(Request $request, $id)
    {
        $authUser = $request->user();

        // Validate request
        $validated = $request->validate([
            'emails' => 'required|string',
        ]);

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

        if ($report->status !== 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Report is not ready for delivery.'
            ], 400);
        }

        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report file not found.'
            ], 404);
        }

        // Parse and validate email addresses
        $emailString = $validated['emails'];
        $emailAddresses = array_map('trim', explode(',', $emailString));
        $emailAddresses = array_filter($emailAddresses); // Remove empty strings
        
        // Validate each email
        $invalidEmails = [];
        foreach ($emailAddresses as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidEmails[] = $email;
            }
        }

        if (!empty($invalidEmails)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email address(es): ' . implode(', ', $invalidEmails)
            ], 400);
        }

        if (empty($emailAddresses)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please provide at least one valid email address.'
            ], 400);
        }

        $customer = $report->customer;

        // Dispatch the email job with custom recipients
        try {
            DeliverReportJob::dispatch($report, $emailAddresses);

            Log::info('Report email queued', [
                'report_id' => $report->generated_report_id,
                'customer' => $customer->canonical_name,
                'recipients' => $emailAddresses,
                'user' => $authUser->email,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Report has been queued for email delivery.',
                'data' => [
                    'report_id' => $report->generated_report_id,
                    'customer_name' => $customer->canonical_name,
                    'email_recipients' => $emailAddresses,
                    'report_type' => $report->reportSchedule->report_type ?? 'Unknown',
                ]
            ], 200);
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

    public function deleteSchedule(Request $request, $id)
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

    public function deleteReport(Request $request, $id)
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
        if ($report->file_path && Storage::exists($report->file_path)) {
            Storage::delete($report->file_path);
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

    /**
     * Generate a quick share link for a report (valid for 1 hour)
     */
    public function generateShareLink(Request $request, $id)
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

        if ($report->status !== 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Report is not ready for sharing.'
            ], 400);
        }

        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report file not found.'
            ], 404);
        }

        // Generate a secure token with HMAC signature for tamper detection
        $secureToken = ReportShareToken::generateSecureToken(
            $report->generated_report_id,
            $authUser->company_id
        );
        
        // Create share token with 1 hour expiry
        $shareToken = ReportShareToken::create([
            'generated_report_id' => $report->generated_report_id,
            'company_id' => $authUser->company_id,
            'token' => $secureToken['token'],
            'signature' => $secureToken['signature'],
            'expires_at' => $secureToken['expires_at'],
        ]);

        // Build the share URL using the request's base URL for reliable local development
        $baseUrl = $request->getSchemeAndHttpHost();
        $shareUrl = "{$baseUrl}/api/reports/shared/{$secureToken['token']}";

        Log::info('Share link generated', [
            'report_id' => $report->generated_report_id,
            'expires_at' => $shareToken->expires_at,
            'user' => $authUser->email,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Share link generated successfully.',
            'share_url' => $shareUrl,
            'expires_at' => $shareToken->expires_at->toIso8601String(),
            'expires_in_minutes' => 60,
        ], 200);
    }

    /**
     * Download a shared report using a token (no authentication required)
     */
    public function downloadSharedReport(Request $request, $token)
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

        if (!$report || !$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report file not found.'
            ], 404);
        }

        $customerName = str_replace(' ', '_', $report->customer->canonical_name ?? 'Customer');
        $generatedDate = $report->generated_at->format('Y-m-d');
        $reportType = $report->reportSchedule ? str_replace('_', ' ', $report->reportSchedule->report_type) : 'Report';
        $downloadFilename = "{$customerName}_{$reportType}_{$generatedDate}.pdf";

        Log::info('Shared report downloaded', [
            'report_id' => $report->generated_report_id,
            'token' => $token,
        ]);

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
