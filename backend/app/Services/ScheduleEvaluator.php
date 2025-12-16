<?php

namespace App\Services;

use App\Jobs\GenerateReportJob;
use App\Models\ReportSchedule;
use Carbon\Carbon;

class ScheduleEvaluator
{
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
            $nextRun = $this->calculateNextRun($schedule);
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
        $schedule->next_scheduled_at = $this->calculateNextRun($schedule);
        $schedule->save();
    }

    public function calculateNextRun(ReportSchedule $schedule): Carbon
    {
        $timezone = $schedule->timezone ?? $schedule->company->settings['reports']['report_timezone'] ?? 'UTC';
        $config = $schedule->schedule_config;
        $timeOfDay = $schedule->time_of_day ?? '09:00';

        [$hour, $minute] = explode(':', $timeOfDay);

        $now = Carbon::now($timezone);
        $next = Carbon::now($timezone);

        switch ($schedule->frequency) {
            case 'daily':
                $next = $this->calculateDailyNext($next, $hour, $minute, $config);
                break;

            case 'weekly':
                $next = $this->calculateWeeklyNext($next, $hour, $minute, $config);
                break;

            case 'monthly':
                $next = $this->calculateMonthlyNext($next, $hour, $minute, $config);
                break;

            case 'yearly':
                $next = $this->calculateYearlyNext($next, $hour, $minute, $config);
                break;

            default:
                $next = Carbon::now($timezone)->addYears(10);
        }

        return $next;
    }

    private function calculateDailyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $next->setTime($hour, $minute, 0);

        if ($next->isPast()) {
            $next->addDay();
        }

        if (isset($config['days_of_week'])) {
            while (!in_array($next->dayOfWeek, $config['days_of_week'])) {
                $next->addDay();
            }
        }

        return $next;
    }

    private function calculateWeeklyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $dayOfWeek = $config['day_of_week'] ?? 1;

        $next->setTime($hour, $minute, 0);
        $next->next($dayOfWeek);

        if ($next->dayOfWeek === $dayOfWeek && $next->isPast()) {
            $next->addWeek();
        }

        return $next;
    }

    private function calculateMonthlyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $dayOfMonth = $config['day_of_month'] ?? 1;
        $fallbackToLastDay = $config['fallback_to_last_day'] ?? true;

        $next->setTime($hour, $minute, 0);
        $next->day = min($dayOfMonth, $next->daysInMonth);

        if ($next->isPast()) {
            $next->addMonth();
            $next->day = min($dayOfMonth, $next->daysInMonth);
        }

        if ($fallbackToLastDay && $dayOfMonth > $next->daysInMonth) {
            $next->day = $next->daysInMonth;
        }

        return $next;
    }

    private function calculateYearlyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $month = $config['month'] ?? 1;
        $dayOfMonth = $config['day_of_month'] ?? 1;

        $next->setTime($hour, $minute, 0);
        $next->month = $month;
        $next->day = $dayOfMonth;

        if ($next->isPast()) {
            $next->addYear();
        }

        return $next;
    }

    public function previewNextRuns(ReportSchedule $schedule, int $count = 5): array
    {
        $runs = [];
        $tempSchedule = clone $schedule;

        for ($i = 0; $i < $count; $i++) {
            $nextRun = $this->calculateNextRun($tempSchedule);
            $runs[] = $nextRun->toDateTimeString();

            $tempSchedule->next_scheduled_at = $nextRun;
            $tempSchedule->last_generated_at = $nextRun;
        }

        return $runs;
    }
}
