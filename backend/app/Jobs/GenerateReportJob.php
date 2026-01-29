<?php

declare(strict_types=1);

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

final class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; 

    protected ReportSchedule $schedule;

    public function __construct(ReportSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    public function handle(ReportGenerationService $reportService): void
    {
        try {
            Log::info('Starting report generation', [
                'schedule_id' => $this->schedule->report_schedule_id,
                'customer_id' => $this->schedule->customer_id,
                'report_type' => $this->schedule->report_type,
            ]);
            
            $generatedReport = $reportService->generateReport($this->schedule);

            Log::info('Report generated successfully', [
                'generated_report_id' => $generatedReport->generated_report_id,
            ]);
            
            DeliverReportJob::dispatch($generatedReport);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'schedule_id' => $this->schedule->report_schedule_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job failed permanently', [
            'schedule_id' => $this->schedule->report_schedule_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
