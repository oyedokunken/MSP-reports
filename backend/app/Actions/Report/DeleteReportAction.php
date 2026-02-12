<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Concerns\LogsAudit;
use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteReportAction
{
    use LogsAudit;

    /**
     * @return int The ID of the deleted report
     */
    public function execute(int $companyId, int $reportId): int
    {
        $report = GeneratedReport::where('generated_report_id', $reportId)
            ->where('company_id', $companyId)
            ->first();

        if (!$report) {
            throw ValidationException::withMessages([
                'report_id' => ['Report not found or access denied.'],
            ]);
        }

        // Delete the PDF file if it exists
        if ($report->file_path && Storage::disk('reports')->exists($report->file_path)) {
            Storage::disk('reports')->delete($report->file_path);
        }

        $id = $report->generated_report_id;
        $reportType = $report->reportSchedule?->report_type ?? 'Unknown';
        $customerName = $report->customer?->canonical_name ?? 'Unknown';
        $report->delete();

        $this->audit($companyId, 'report_deleted', null, [
            'report_id' => $id,
            'report_type' => $reportType,
            'customer_name' => $customerName,
        ]);

        return $id;
    }
}
