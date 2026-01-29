<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportSchedule;
use App\Jobs\GenerateReportJob;
use Carbon\Carbon;

class EvaluateReportSchedules extends Command
{
    protected $signature = 'reports:evaluate-schedules';
    protected $description = 'Evaluate report schedules and queue reports that are due';

    public function handle(): int
    {
        $this->info('Evaluating report schedules...');

        $now = Carbon::now();
        $schedulesProcessed = 0;
        $reportsQueued = 0;

        $schedules = ReportSchedule::where('active', true)->get();

        foreach ($schedules as $schedule) {
            if (!$schedule->next_scheduled_at) {
                $schedule->next_scheduled_at = $this->calculateNextScheduledTime($schedule);
                $schedule->save();
                $this->info("Set next schedule for {$schedule->report_type}: {$schedule->next_scheduled_at}");
            }

            if ($now->gte($schedule->next_scheduled_at)) {
                GenerateReportJob::dispatch($schedule);
                $reportsQueued++;

                $schedule->next_scheduled_at = $this->calculateNextScheduledTime($schedule);
                $schedule->save();

                $this->info("Queued report: {$schedule->report_type} for customer {$schedule->customer_id}");
            }

            $schedulesProcessed++;
        }

        $this->info("Processed {$schedulesProcessed} schedules, queued {$reportsQueued} reports");

        return 0;
    }

    private function calculateNextScheduledTime(ReportSchedule $schedule): Carbon
    {
        $timezone = $schedule->timezone ?? 'UTC';
        $now = Carbon::now($timezone);
        
        $timeParts = explode(':', $schedule->time_of_day ?? '09:00');
        $hour = (int)($timeParts[0]);
        $minute = (int)($timeParts[1] ?? 0);

        switch ($schedule->frequency) {
            case 'daily':
                $next = $now->copy()->setTime($hour, $minute, 0);
                if ($next->lte($now)) {
                    $next->addDay();
                }
                
                if ($schedule->schedule_config && isset($schedule->schedule_config['days_of_week'])) {
                    $daysOfWeek = $schedule->schedule_config['days_of_week'];
                    while (!in_array($next->dayOfWeek, $daysOfWeek)) {
                        $next->addDay();
                    }
                }
                return $next;

            case 'weekly':
                $dayOfWeek = $schedule->schedule_config['day_of_week'] ?? 1;
                $next = $now->copy()->setTime($hour, $minute, 0);
                
                while ($next->dayOfWeek !== $dayOfWeek) {
                    $next->addDay();
                }
                
                
                if ($next->lte($now)) {
                    $next->addWeek();
                }
                return $next;

            case 'monthly':
                $dayOfMonth = $schedule->schedule_config['day_of_month'] ?? 1;
                $next = $now->copy()->setTime($hour, $minute, 0)->day($dayOfMonth);
                
                
                if ($next->lte($now)) {
                    $next->addMonth();
                }
                
                
                if ($schedule->schedule_config['fallback_to_last_day'] ?? false) {
                    if ($dayOfMonth > $next->daysInMonth) {
                        $next->day($next->daysInMonth);
                    }
                }
                return $next;

            case 'yearly':
                $month = $schedule->schedule_config['month'] ?? 1;
                $dayOfMonth = $schedule->schedule_config['day_of_month'] ?? 1;
                $next = $now->copy()->setTime($hour, $minute, 0)->month($month)->day($dayOfMonth);
                
                
                if ($next->lte($now)) {
                    $next->addYear();
                }
                return $next;

            case 'on-demand':
            default:
                
                return $now->copy()->addYears(100); 
        }
    }
}
