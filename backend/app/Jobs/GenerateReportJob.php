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
use App\Models\Notification;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min exponential backoff 

    protected ReportSchedule $schedule;

    public function __construct(ReportSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    public function handle(ReportGenerationService $reportService): void
    {
        $attemptNumber = $this->attempts();
        
        try {
            Log::info('Starting report generation', [
                'schedule_id' => $this->schedule->report_schedule_id,
                'customer_id' => $this->schedule->customer_id,
                'report_type' => $this->schedule->report_type,
                'attempt' => $attemptNumber,
            ]);
            
            $generatedReport = $reportService->generateReport($this->schedule);

            Log::info('Report generated successfully', [
                'generated_report_id' => $generatedReport->generated_report_id,
                'attempt' => $attemptNumber,
            ]);
            
            // Create success notification if this was a retry
            if ($attemptNumber > 1) {
                $this->createNotification(
                    'success',
                    'Report Generated After Retry',
                    "Report '{$this->schedule->report_type}' generated successfully on attempt {$attemptNumber}."
                );
            }
            
            DeliverReportJob::dispatch($generatedReport);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'schedule_id' => $this->schedule->report_schedule_id,
                'error' => $e->getMessage(),
                'attempt' => $attemptNumber,
            ]);
            
            // If we have more attempts, schedule retry with backoff
            if ($attemptNumber < $this->tries) {
                $backoffSeconds = $this->backoff[$attemptNumber - 1] ?? 60;
                
                Log::info('Scheduling retry for report generation', [
                    'schedule_id' => $this->schedule->report_schedule_id,
                    'next_attempt' => $attemptNumber + 1,
                    'backoff_seconds' => $backoffSeconds,
                ]);
                
                $this->createNotification(
                    'warning',
                    'Report Generation Retry Scheduled',
                    "Report generation failed (attempt {$attemptNumber}). Retrying in " . ($backoffSeconds / 60) . " minutes..."
                );
                
                $this->release($backoffSeconds);
                return;
            }
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job failed permanently', [
            'schedule_id' => $this->schedule->report_schedule_id,
            'error' => $exception->getMessage(),
            'total_attempts' => $this->attempts(),
        ]);
        
        // Create failure notification
        $this->createNotification(
            'error',
            'Report Generation Failed',
            "Report '{$this->schedule->report_type}' failed after {$this->attempts()} attempts: {$exception->getMessage()}"
        );
    }
    
    /**
     * Create a notification for the company
     */
    private function createNotification(string $type, string $title, string $message): void
    {
        try {
            Notification::create([
                'company_id' => $this->schedule->company_id,
                'type' => 'report',
                'severity' => $type,
                'title' => $title,
                'description' => $message,
                'event_data' => [
                    'schedule_id' => $this->schedule->report_schedule_id,
                    'customer_id' => $this->schedule->customer_id,
                    'report_type' => $this->schedule->report_type,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
