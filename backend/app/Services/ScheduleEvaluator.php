<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Jobs\GenerateReportJob;
use Carbon\Carbon;

class ScheduleEvaluator
{
    /**
     * Evaluate all report schedules and trigger generation for those that are due.
     * This is called by a scheduled command (e.g., every hour).
     * 
     * @return array
     */
    public function evaluateDueReports(): array
    {
        $results = [
            'evaluated' => 0,
            'triggered' => 0,
            'skipped' => 0,
        ];

        // Get all active schedules
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

    /**
     * Check if a report schedule is due for generation.
     * 
     * @param ReportSchedule $schedule
     * @return bool
     */
    public function isScheduleDue(ReportSchedule $schedule): bool
    {
        // If next_scheduled_at is null, calculate it
        if (!$schedule->next_scheduled_at) {
            $nextRun = $this->calculateNextRun($schedule);
            $schedule->next_scheduled_at = $nextRun;
            $schedule->save();
            return false;
        }

        // Check if current time is past the scheduled time
        return now()->gte($schedule->next_scheduled_at);
    }

    /**
     * Trigger report generation for a schedule.
     * 
     * @param ReportSchedule $schedule
     * @return void
     */
    public function triggerReportGeneration(ReportSchedule $schedule): void
    {
        // Dispatch report generation job
        GenerateReportJob::dispatch($schedule);

        // Update schedule
        $schedule->last_generated_at = now();
        $schedule->next_scheduled_at = $this->calculateNextRun($schedule);
        $schedule->save();
    }

    /**
     * Calculate the next run time for a schedule.
     * 
     * @param ReportSchedule $schedule
     * @return Carbon
     */
    public function calculateNextRun(ReportSchedule $schedule): Carbon
    {
        $timezone = $schedule->timezone ?? $schedule->company->settings['reports']['report_timezone'] ?? 'UTC';
        $config = $schedule->schedule_config;
        $timeOfDay = $schedule->time_of_day ?? '09:00';

        // Parse time
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
                // On-demand, set far in future
                $next = Carbon::now($timezone)->addYears(10);
        }

        return $next;
    }

    /**
     * Calculate next daily run.
     */
    private function calculateDailyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $next->setTime($hour, $minute, 0);

        // If time has passed today, move to tomorrow
        if ($next->isPast()) {
            $next->addDay();
        }

        // Check days of week restriction
        if (isset($config['days_of_week'])) {
            while (!in_array($next->dayOfWeek, $config['days_of_week'])) {
                $next->addDay();
            }
        }

        return $next;
    }

    /**
     * Calculate next weekly run.
     */
    private function calculateWeeklyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $dayOfWeek = $config['day_of_week'] ?? 1; // Default Monday

        $next->setTime($hour, $minute, 0);
        $next->next($dayOfWeek);

        // If we're on the target day but time has passed, move to next week
        if ($next->dayOfWeek === $dayOfWeek && $next->isPast()) {
            $next->addWeek();
        }

        return $next;
    }

    /**
     * Calculate next monthly run.
     */
    private function calculateMonthlyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $dayOfMonth = $config['day_of_month'] ?? 1;
        $fallbackToLastDay = $config['fallback_to_last_day'] ?? true;

        $next->setTime($hour, $minute, 0);
        $next->day = min($dayOfMonth, $next->daysInMonth);

        // If time has passed this month, move to next month
        if ($next->isPast()) {
            $next->addMonth();
            $next->day = min($dayOfMonth, $next->daysInMonth);
        }

        // Handle months with fewer days
        if ($fallbackToLastDay && $dayOfMonth > $next->daysInMonth) {
            $next->day = $next->daysInMonth;
        }

        return $next;
    }

    /**
     * Calculate next yearly run.
     */
    private function calculateYearlyNext(Carbon $next, int $hour, int $minute, array $config): Carbon
    {
        $month = $config['month'] ?? 1;
        $dayOfMonth = $config['day_of_month'] ?? 1;

        $next->setTime($hour, $minute, 0);
        $next->month = $month;
        $next->day = $dayOfMonth;

        // If time has passed this year, move to next year
        if ($next->isPast()) {
            $next->addYear();
        }

        return $next;
    }

    /**
     * Preview next N scheduled runs for a schedule.
     * 
     * @param ReportSchedule $schedule
     * @param int $count
     * @return array
     */
    public function previewNextRuns(ReportSchedule $schedule, int $count = 5): array
    {
        $runs = [];
        $tempSchedule = clone $schedule;

        for ($i = 0; $i < $count; $i++) {
            $nextRun = $this->calculateNextRun($tempSchedule);
            $runs[] = $nextRun->toDateTimeString();

            // Update temp schedule for next iteration
            $tempSchedule->next_scheduled_at = $nextRun;
            $tempSchedule->last_generated_at = $nextRun;
        }

        return $runs;
    }
}
