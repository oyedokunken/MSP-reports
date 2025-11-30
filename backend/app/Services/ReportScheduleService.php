<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Services\ReportGenerationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReportScheduleService
{
    protected ReportGenerationService $generationService;

    public function __construct(ReportGenerationService $generationService)
    {
        $this->generationService = $generationService;
    }

    /**
     * Process all due report schedules
     */
    public function processDueSchedules(): array
    {
        $dueSchedules = ReportSchedule::where('active', true)
            ->where(function ($query) {
                $query->where('next_scheduled_at', '<=', now())
                    ->orWhereNull('next_scheduled_at');
            })
            ->with(['customer', 'company'])
            ->get();

        Log::info("Processing due report schedules", [
            'count' => $dueSchedules->count(),
            'schedule_ids' => $dueSchedules->pluck('report_schedule_id')->toArray(),
        ]);

        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($dueSchedules as $schedule) {
            $results['processed']++;
            
            try {
                $this->processSchedule($schedule);
                $results['succeeded']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'schedule_id' => $schedule->report_schedule_id,
                    'report_type' => $schedule->report_type,
                    'customer_id' => $schedule->customer_id,
                    'error' => $e->getMessage(),
                ];
                
                Log::error("Failed to process schedule", [
                    'schedule_id' => $schedule->report_schedule_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info("Schedule processing completed", $results);

        return $results;
    }

    /**
     * Process a single schedule
     */
    public function processSchedule(ReportSchedule $schedule): void
    {
        Log::info("Processing schedule", [
            'schedule_id' => $schedule->report_schedule_id,
            'report_type' => $schedule->report_type,
            'customer_id' => $schedule->customer_id,
            'frequency' => $schedule->frequency,
        ]);

        // Generate the report
        $generatedReport = $this->generationService->generateReport($schedule);

        // Update schedule timestamps
        $schedule->update([
            'last_generated_at' => now(),
            'next_scheduled_at' => $this->calculateNextRun($schedule),
        ]);

        Log::info("Schedule processed successfully", [
            'schedule_id' => $schedule->report_schedule_id,
            'generated_report_id' => $generatedReport->generated_report_id,
            'next_scheduled_at' => $schedule->next_scheduled_at,
        ]);
    }

    /**
     * Calculate next run time for a schedule
     */
    public function calculateNextRun(ReportSchedule $schedule): Carbon
    {
        $timezone = $schedule->timezone ?? $schedule->company->settings['reports']['report_timezone'] ?? 'UTC';
        $time = Carbon::parse($schedule->time_of_day ?? '09:00', $timezone);
        $now = Carbon::now($timezone);

        return match($schedule->frequency) {
            'daily' => $this->calculateDailyNext($schedule, $time, $now),
            'weekly' => $this->calculateWeeklyNext($schedule, $time, $now),
            'monthly' => $this->calculateMonthlyNext($schedule, $time, $now),
            'yearly' => $this->calculateYearlyNext($schedule, $time, $now),
            default => $now->copy()->addDay()->setTimeFrom($time),
        };
    }

    /**
     * Calculate next daily run
     */
    protected function calculateDailyNext(ReportSchedule $schedule, Carbon $time, Carbon $now): Carbon
    {
        $next = $now->copy()->setTimeFrom($time);
        
        // If time has passed today, schedule for tomorrow
        if ($next->isPast()) {
            $next->addDay();
        }
        
        // Check if specific days of week are configured
        $daysOfWeek = $schedule->schedule_config['days_of_week'] ?? null;
        if ($daysOfWeek && is_array($daysOfWeek)) {
            // Find next valid day
            $maxAttempts = 7; // Don't loop forever
            $attempts = 0;
            
            while (!in_array($next->dayOfWeek, $daysOfWeek) && $attempts < $maxAttempts) {
                $next->addDay();
                $attempts++;
            }
        }
        
        return $next;
    }

    /**
     * Calculate next weekly run
     */
    protected function calculateWeeklyNext(ReportSchedule $schedule, Carbon $time, Carbon $now): Carbon
    {
        $dayOfWeek = $schedule->schedule_config['day_of_week'] ?? Carbon::MONDAY;
        
        // Get next occurrence of the target day
        $next = $now->copy()->setTimeFrom($time);
        
        // If we're past the target day this week, or it's the target day but time has passed
        if ($next->dayOfWeek > $dayOfWeek || ($next->dayOfWeek == $dayOfWeek && $next->isPast())) {
            $next->next($dayOfWeek);
        } else {
            // Move to the target day this week
            while ($next->dayOfWeek != $dayOfWeek) {
                $next->addDay();
            }
        }
        
        return $next;
    }

    /**
     * Calculate next monthly run
     */
    protected function calculateMonthlyNext(ReportSchedule $schedule, Carbon $time, Carbon $now): Carbon
    {
        $dayOfMonth = $schedule->schedule_config['day_of_month'] ?? 1;
        $fallbackToLastDay = $schedule->schedule_config['fallback_to_last_day'] ?? true;
        
        $next = $now->copy()->setTimeFrom($time);
        
        // Try to set to the target day of current month
        try {
            $next->day($dayOfMonth);
        } catch (\Exception $e) {
            // Day doesn't exist in this month (e.g., Feb 30)
            if ($fallbackToLastDay) {
                $next->endOfMonth();
            } else {
                // Skip to next month
                $next->addMonth()->startOfMonth()->day(min($dayOfMonth, $next->daysInMonth));
            }
        }
        
        // If we've passed this time in the current month, move to next month
        if ($next->isPast()) {
            $next->addMonth();
            try {
                $next->day($dayOfMonth);
            } catch (\Exception $e) {
                if ($fallbackToLastDay) {
                    $next->endOfMonth();
                }
            }
        }
        
        return $next;
    }

    /**
     * Calculate next yearly run
     */
    protected function calculateYearlyNext(ReportSchedule $schedule, Carbon $time, Carbon $now): Carbon
    {
        $month = $schedule->schedule_config['month'] ?? 1;
        $dayOfMonth = $schedule->schedule_config['day_of_month'] ?? 1;
        
        $next = $now->copy()->setTimeFrom($time);
        
        // Set to target month and day
        $next->month($month);
        try {
            $next->day($dayOfMonth);
        } catch (\Exception $e) {
            // Day doesn't exist (e.g., Feb 30)
            $next->endOfMonth();
        }
        
        // If we've passed this date this year, move to next year
        if ($next->isPast()) {
            $next->addYear();
        }
        
        return $next;
    }

    /**
     * Get schedules that are overdue
     */
    public function getOverdueSchedules(): \Illuminate\Database\Eloquent\Collection
    {
        return ReportSchedule::where('active', true)
            ->where('next_scheduled_at', '<', now()->subHours(24))
            ->with(['customer', 'company'])
            ->get();
    }

    /**
     * Get upcoming schedules
     */
    public function getUpcomingSchedules(int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return ReportSchedule::where('active', true)
            ->whereBetween('next_scheduled_at', [now(), now()->addHours($hours)])
            ->with(['customer', 'company'])
            ->orderBy('next_scheduled_at')
            ->get();
    }

    /**
     * Activate a schedule and calculate first run
     */
    public function activateSchedule(ReportSchedule $schedule): void
    {
        $schedule->update([
            'active' => true,
            'next_scheduled_at' => $this->calculateNextRun($schedule),
        ]);

        Log::info("Schedule activated", [
            'schedule_id' => $schedule->report_schedule_id,
            'next_scheduled_at' => $schedule->next_scheduled_at,
        ]);
    }

    /**
     * Deactivate a schedule
     */
    public function deactivateSchedule(ReportSchedule $schedule): void
    {
        $schedule->update([
            'active' => false,
        ]);

        Log::info("Schedule deactivated", [
            'schedule_id' => $schedule->report_schedule_id,
        ]);
    }
}
