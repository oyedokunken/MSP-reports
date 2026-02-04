<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\GeneratedReport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

final class GetReportHistoryAction
{
    /**
     * @param int $companyId
     * @param array{per_page?: int, customer_id?: int|null} $filters
     * @return LengthAwarePaginator<int, GeneratedReport>
     */
    public function execute(int $companyId, array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 50;
        $customerId = $filters['customer_id'] ?? null;
        
        $query = GeneratedReport::where('company_id', $companyId);
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        $reports = $query->with([
                'customer:customer_id,canonical_name',
                'reportSchedule:report_schedule_id,report_type',
            ])
            ->orderBy('generated_at', 'desc')
            ->paginate($perPage);

        // Process reports for missing files (Maintenance: this could be moved to a job)
        // We're doing it here to ensure the UI reflects the true state, 
        // effectively "Lazy Validation" of file existence.
        foreach ($reports->items() as $report) {
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
        }

        return $reports;
    }
}
