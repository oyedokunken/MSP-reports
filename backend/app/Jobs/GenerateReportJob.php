<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ReportSchedule;
use App\Services\ReportGenerationService;
use App\Jobs\DeliverReportJob;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600; // 10 minutes

    protected ReportSchedule $schedule;

    /**
     * Create a new job instance.
     */
    public function __construct(ReportSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Execute the job.
     */
    public function handle(ReportGenerationService $reportService): void
    {
        try {
            Log::info('Starting report generation', [
                'schedule_id' => $this->schedule->report_schedule_id,
                'customer_id' => $this->schedule->customer_id,
                'report_type' => $this->schedule->report_type,
            ]);

            // Generate report
            $generatedReport = $reportService->generateReport($this->schedule);

            Log::info('Report generated successfully', [
                'generated_report_id' => $generatedReport->generated_report_id,
            ]);

            // Queue delivery job
            DeliverReportJob::dispatch($generatedReport);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'schedule_id' => $this->schedule->report_schedule_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job failed permanently', [
            'schedule_id' => $this->schedule->report_schedule_id,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Notify company admins of failure
    }
}
