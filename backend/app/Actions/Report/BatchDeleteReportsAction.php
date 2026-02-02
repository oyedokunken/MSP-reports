<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Storage;

final class BatchDeleteReportsAction
{
    /**
     * @param array<int> $reportIds
     */
    public function execute(int $companyId, array $reportIds, bool $force = false): int
    {
        $query = GeneratedReport::whereIn('generated_report_id', $reportIds)
            ->where('company_id', $companyId);
        
        if ($force) {
            $query->where('delivered', false);
        }

        $reports = $query->get();

        $deletedCount = 0;
        foreach ($reports as $report) {
            // Delete the PDF file if it exists
            if ($report->file_path && Storage::disk('reports')->exists($report->file_path)) {
                Storage::disk('reports')->delete($report->file_path);
            }
            
            if ($force) {
                $report->forceDelete();
            } else {
                $report->delete();
            }
            
            $deletedCount++;
        }

        return $deletedCount;
    }
}
