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
            ->with([
                'customer:customer_id,canonical_name',
                'company:company_id,name',
                'generatedReports:report_schedule_id,status,generated_at'
            ])
            ->orderBy('next_scheduled_at', 'asc')
            ->get([
                'report_schedule_id',
                'company_id',
                'customer_id',
                'report_type',
                'frequency',
                'time_of_day',
                'active',
                'last_generated_at',
                'next_scheduled_at',
                'timezone',
            ]);

        return response()->json([
            'status' => 'success',
            'count' => $schedules->count(),
            'schedules' => $schedules
        ], 200);
    }
    
    public function getReportHistory(Request $request)
    {
        $authUser = $request->user();

        $reports = GeneratedReport::where('company_id', $authUser->company_id)
            ->with([
                'customer:customer_id,canonical_name',
                'reportSchedule:report_schedule_id,report_type,frequency,time_of_day',
            ])
            ->orderBy('generated_at', 'desc')
            ->get([
                'generated_report_id',
                'company_id',
                'customer_id',
                'report_schedule_id',
                'generated_at',
                'report_period',
                'status',
                'delivery_method',
                'file_path',
                'file_size',
                'delivered',
                'delivered_at',
                'error_message',
                'retention_until',
            ]);

        return response()->json([
            'status' => 'success',
            'count' => $reports->count(),
            'reports' => $reports
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

        // Ensure storage directory exists
        $storagePath = 'private/reports';
        if (!Storage::exists($storagePath)) {
            Storage::makeDirectory($storagePath);
        }

        // If file is missing, regenerate it
        if (!$report->file_path || !Storage::exists($report->file_path)) {
            $customerName = $report->customer->canonical_name ?? 'Unknown_Customer';
            $timestamp = now()->format('Ymd_His');
            $fileName = "report_{$customerName}_{$timestamp}.txt";
            $filePath = "{$storagePath}/{$fileName}";

            $content = "Generated Report\n"
                . "Customer: {$customerName}\n"
                . "Generated At: {$report->generated_at}\n"
                . "Company ID: {$report->company_id}\n"
                . "Report Period: {$report->report_period}\n"
                . "Status: {$report->status}\n";

            Storage::put($filePath, $content);

            // Update record with file info
            $report->update([
                'file_path' => $filePath,
                'file_size' => Storage::size($filePath),
                'status' => 'completed'
            ]);
        }

        $filename = basename($report->file_path);
        $mimeType = File::mimeType(Storage::path($report->file_path));

        return Storage::download($report->file_path, $filename, [
            'Content-Type' => $mimeType,
            'X-Report-Name' => $filename,
            'X-Customer' => $report->customer->canonical_name ?? 'N/A',
            'X-Generated-At' => $report->generated_at,
        ]);
    }
}
