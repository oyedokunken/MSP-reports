<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
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
            'data' => $schedules
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

        $report = GeneratedReport::where('generated_report_id', $id)
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
        $generatedDate = $report->generated_at->format('Y-m-d');
        $reportType = $report->reportSchedule ? str_replace('_', ' ', $report->reportSchedule->report_type) : 'Report';
        $downloadFilename = "{$customerName}_{$reportType}_{$generatedDate}.pdf";

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
            'X-Report-Name' => $downloadFilename,
            'X-Customer' => $report->customer->canonical_name ?? 'N/A',
            'X-Generated-At' => $report->generated_at->toDateTimeString(),
        ]);
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

        $schedule->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Schedule deleted successfully: {$reportType} for {$customerName}",
            'data' => [
                'deleted_schedule_id' => $id,
                'customer_name' => $customerName,
                'report_type' => $reportType,
            ]
        ], 200);
    }
}
