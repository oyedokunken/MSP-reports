<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Jobs\DeliverReportJob;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Services\ReportGenerationService;
use Carbon\Carbon;

final class GenerateReportAction
{
    private ReportGenerationService $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function execute(ReportSchedule $schedule, array $options = []): GeneratedReport
    {
        $startDate = isset($options['start_date']) ? Carbon::parse($options['start_date'])->startOfDay() : null;
        $endDate = isset($options['end_date']) ? Carbon::parse($options['end_date'])->endOfDay() : null;
        $frequencyOverride = $options['frequency'] ?? null;
        $previewOnly = $options['preview_only'] ?? false;

        if ($frequencyOverride) {
            $schedule->frequency = $frequencyOverride;
        }

        // Generate report with custom dates only if explicitly provided
        // Otherwise, let the schedule's frequency determine the date range
        if ($startDate && $endDate) {
            $generatedReport = $this->reportService->generateReport($schedule, $startDate, $endDate);
        } else {
            $generatedReport = $this->reportService->generateReport($schedule);
        }

        if (!$previewOnly) {
            DeliverReportJob::dispatch($generatedReport);
        }

        $schedule->update(['last_generated_at' => now()]);

        return $generatedReport;
    }
}
