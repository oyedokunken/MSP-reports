<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ReportSchedule;
use App\Models\GeneratedReport;

class ReportAnalyticsController extends Controller
{
    public function getScheduledReports(Request $request)
    {
        $authUser = $request->user();

        $schedules = ReportSchedule::where('company_id', $authUser->company_id)
            ->with('customer:customer_id,canonical_name')
            ->orderBy('next_scheduled_at', 'asc')
            ->get()
            ->map(function ($schedule) {
                return [
                    'report_schedule_id' => $schedule->report_schedule_id,
                    'customer_id' => $schedule->customer_id,
                    'customer_name' => $schedule->customer->canonical_name ?? 'Unknown',
                    'report_type' => $schedule->report_type,
                    'frequency' => $schedule->frequency,
                    'next_scheduled_at' => $schedule->next_scheduled_at,
                    'last_generated_at' => $schedule->last_generated_at,
                    'active' => $schedule->active,
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
        
        $reports = GeneratedReport::where('company_id', $authUser->company_id)
            ->with([
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
            ->with(['customer:customer_id,canonical_name'])
            ->first();

        if (!$report) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report not found or access denied.'
            ], 404);
        }

        // Check if file exists on reports disk
        if (!$report->file_path || !Storage::disk('reports')->exists($report->file_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Report file not found.'
            ], 404);
        }

        $filename = basename($report->file_path);
        
        // Generate a user-friendly filename
        $customerName = str_replace(' ', '_', $report->customer->canonical_name ?? 'Customer');
        $generatedDate = $report->generated_at->format('Y-m-d');
        $reportType = str_replace('_', ' ', $report->reportSchedule->report_type ?? 'Report');
        $downloadFilename = "{$customerName}_{$reportType}_{$generatedDate}.pdf";

        return Storage::disk('reports')->download($report->file_path, $downloadFilename, [
            'Content-Type' => 'application/pdf',
            'X-Report-Name' => $downloadFilename,
            'X-Customer' => $report->customer->canonical_name ?? 'N/A',
            'X-Generated-At' => $report->generated_at->toDateTimeString(),
        ]);
    }
}
