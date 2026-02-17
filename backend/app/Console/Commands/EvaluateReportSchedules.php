<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportSchedule;
use App\Jobs\GenerateReportJob;
use App\Services\ReportScheduleService;
use Carbon\Carbon;

class EvaluateReportSchedules extends Command
{
    protected $signature = 'reports:evaluate-schedules';
    protected $description = 'Evaluate report schedules and queue reports that are due';

    public function handle(ReportScheduleService $scheduleService): int
    {
        $this->info('Evaluating report schedules...');

        $now = Carbon::now();
        $schedulesProcessed = 0;
        $reportsQueued = 0;

        $schedules = ReportSchedule::where('active', true)
            ->with(['customer', 'company'])
            ->get();

        foreach ($schedules as $schedule) {
            if (!$schedule->next_scheduled_at) {
                $schedule->next_scheduled_at = $scheduleService->calculateNextRun($schedule);
                $schedule->save();
                $this->info("Set next schedule for {$schedule->report_type}: {$schedule->next_scheduled_at}");
            }

            if ($now->gte($schedule->next_scheduled_at)) {
                GenerateReportJob::dispatch($schedule);
                $reportsQueued++;

                $schedule->next_scheduled_at = $scheduleService->calculateNextRun($schedule);
                $schedule->save();

                $this->info("Queued report: {$schedule->report_type} for customer {$schedule->customer_id}");
            }

            $schedulesProcessed++;
        }

        $this->info("Processed {$schedulesProcessed} schedules, queued {$reportsQueued} reports");

        return 0;
    }
}
