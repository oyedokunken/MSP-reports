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

        
        $storagePath = 'private/reports';
        if (!Storage::exists($storagePath)) {
            Storage::makeDirectory($storagePath);
        }

        
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
