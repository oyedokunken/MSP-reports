<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateReportJob;
use App\Models\ReportSchedule;
use Carbon\Carbon;

final class ScheduleEvaluator
{
    protected ReportScheduleService $scheduleService;

    public function __construct(ReportScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * @return array<string, int>
     */
    public function evaluateDueReports(): array
    {
        $results = [
            'evaluated' => 0,
            'triggered' => 0,
            'skipped' => 0,
        ];

        $schedules = ReportSchedule::where('active', true)
            ->with(['customer', 'company'])
            ->get();

        foreach ($schedules as $schedule) {
            $results['evaluated']++;

            if ($this->isScheduleDue($schedule)) {
                $this->triggerReportGeneration($schedule);
                $results['triggered']++;
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    public function isScheduleDue(ReportSchedule $schedule): bool
    {
        if (!$schedule->next_scheduled_at) {
            $nextRun = $this->scheduleService->calculateNextRun($schedule);
            $schedule->next_scheduled_at = $nextRun;
            $schedule->save();
            return false;
        }

        return now()->gte($schedule->next_scheduled_at);
    }

    public function triggerReportGeneration(ReportSchedule $schedule): void
    {
        GenerateReportJob::dispatch($schedule);

        $schedule->last_generated_at = now();
        $schedule->next_scheduled_at = $this->scheduleService->calculateNextRun($schedule);
        $schedule->save();
    }

    /**
     * Delegates to ReportScheduleService for consistent calculation.
     */
    public function calculateNextRun(ReportSchedule $schedule): Carbon
    {
        return $this->scheduleService->calculateNextRun($schedule);
    }

    /**
     * @return array<int, string>
     */
    public function previewNextRuns(ReportSchedule $schedule, int $count = 5): array
    {
        $runs = [];
        $tempSchedule = clone $schedule;

        for ($i = 0; $i < $count; $i++) {
            $nextRun = $this->scheduleService->calculateNextRun($tempSchedule);
            $runs[] = $nextRun->toDateTimeString();

            $tempSchedule->next_scheduled_at = $nextRun;
            $tempSchedule->last_generated_at = $nextRun;
        }

        return $runs;
    }
}
