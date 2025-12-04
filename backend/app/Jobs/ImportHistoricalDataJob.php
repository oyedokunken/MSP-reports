<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\HistoricalImportJob as HistoricalImportJobModel;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Integrations\Autotask\AutotaskIntegration;
use App\Integrations\Datto\DattoIntegration;
use App\Integrations\Sendmarc\SendmarcIntegration;
use App\Integrations\ITGlue\ITGlueIntegration;
use App\Integrations\LibreNMS\LibreNMSIntegration;
use App\Integrations\SaaS\SaaSIntegration;


class ImportHistoricalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 1800; // 30 minutes to handle rate limit waits
    public $backoff = [120, 300, 600, 1200]; // Exponential backoff: 2min, 5min, 10min, 20min

    protected HistoricalImportJobModel $importJob;
    protected Carbon $chunkDate;

    public function __construct(HistoricalImportJobModel $importJob, Carbon $chunkDate)
    {
        $this->importJob = $importJob;
        $this->chunkDate = $chunkDate;
    }

    public function handle(RateLimiter $rateLimiter): void
    {
        try {
            $integration = $this->importJob->integration;

            if ($this->importJob->status === 'queued') {
                $this->importJob->update([
                    'status' => 'running',
                    'started_at' => now(),
                ]);
            }
            
            $integrationClass = $this->loadIntegrationClass($integration);

            if (!$integrationClass) {
                throw new \Exception("Integration type {$integration->integration_type} not found.");
            }

            // Optimization: Process 7 days at a time instead of 1 day
            // This reduces queue overhead from 90 jobs to ~13 jobs for a 90-day import
            $daysToProcess = 7;
            $currentDate = $this->chunkDate->copy();
            $endDate = $this->chunkDate->copy()->addDays($daysToProcess - 1)->endOfDay();
            
            // Cap at the import job's end date
            if ($endDate->gt($this->importJob->import_end_date)) {
                $endDate = $this->importJob->import_end_date->copy()->endOfDay();
            }

            // Check rate limit WITHOUT blocking
            if (!$rateLimiter->canMakeRequest($integration)) {
                // Release the job back to the queue for 60 seconds
                // This frees up the worker to do other things instead of sleeping
                $this->release(60);
                return;
            }

            // Process the chunk (up to 7 days)
            $result = $integrationClass->getHistoricalData(
                $currentDate,
                $endDate
            );

            $rateLimiter->recordRequest($integration);

            $recordsImported = count($result['data'] ?? []);
            $this->importJob->records_imported += $recordsImported;
            
            // Update position to the end of this chunk
            $this->importJob->current_position = $endDate;

            // Calculate progress
            $totalDays = $this->importJob->import_start_date->diffInDays($this->importJob->import_end_date) ?: 1;
            $completedDays = $this->importJob->import_start_date->diffInDays($endDate);
            $this->importJob->progress_percent = min(100, round(($completedDays / $totalDays) * 100));

            $this->importJob->save();

            // Dispatch next job if there are more days
            $nextDate = $endDate->copy()->addDay();
            
            if ($nextDate->lte($this->importJob->import_end_date)) {
                // Delay next job by 2 minutes to avoid overwhelming rate limits
                // This ensures jobs process sequentially with breathing room
                ImportHistoricalDataJob::dispatch($this->importJob, $nextDate)->delay(now()->addMinutes(2));
            } else {
                // All done!
                $this->importJob->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'progress_percent' => 100,
                ]);

                Log::info('Historical import completed', [
                    'import_job_id' => $this->importJob->historical_import_job_id,
                    'records_imported' => $this->importJob->records_imported,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Historical import chunk failed', [
                'import_job_id' => $this->importJob->historical_import_job_id,
                'chunk_date' => $this->chunkDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            $this->importJob->increment('error_count');
            $this->importJob->save();

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Historical import job failed permanently after all retries', [
            'import_job_id' => $this->importJob->historical_import_job_id,
            'integration_id' => $this->importJob->integration_id,
            'company_id' => $this->importJob->company_id,
            'chunk_date' => $this->chunkDate->toDateString(),
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Mark import job as failed
        $this->importJob->update([
            'status' => 'failed',
        ]);

        // Mark integration as error if historical import fails
        $integration = $this->importJob->integration;
        $integration->update([
            'status' => 'error',
            'historical_import_status' => 'failed',
            'error_message' => "Historical import failed: {$exception->getMessage()}",
        ]);

        // TODO: Send notification email to company admins
        // Will be implemented in future update
        // $company = $integration->company;
        // $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];
    }

    private function loadIntegrationClass(Integration $integration)
    {
        $integrationClasses = [
            'Autotask' => AutotaskIntegration::class,
            'Datto' => DattoIntegration::class,
            'Sendmarc' => SendmarcIntegration::class,
            'ITGlue' => ITGlueIntegration::class,
            'LibreNMS' => LibreNMSIntegration::class,
            'SaaS' => SaaSIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($integration);
        }

        return null;
    }
}
