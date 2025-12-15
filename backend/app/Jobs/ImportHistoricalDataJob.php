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
use App\Integrations\BitDefender\BitDefenderIntegration;


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

            // Optimization: Process 30 days at a time
            // This reduces queue overhead significantly
            // Process 1 day at a time to ensure progress bar updates frequently
            // This creates more jobs but provides much better UX
            $daysToProcess = 1;
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
            // Set progress callback if supported
            if (method_exists($integrationClass, 'setProgressCallback')) {
                $integrationClass->setProgressCallback(function(int $count) {
                    $this->importJob->increment('records_imported', $count);
                });
            }

            $result = $integrationClass->getHistoricalData(
                $currentDate,
                $endDate
            );

            $rateLimiter->recordRequest($integration);

            // Get records count from result (supports both old 'data' array and new 'total_processed' count)
            $recordsImported = $result['total_processed'] ?? count($result['data'] ?? []);
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
                // Delay next job by 5 seconds just to be safe
                // The RateLimiter will handle the actual throttling if needed
                ImportHistoricalDataJob::dispatch($this->importJob, $nextDate)->onQueue('low')->delay(now()->addSeconds(5));
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

        } catch (\App\Integrations\Exceptions\RateLimitException $e) {
            Log::warning('Historical import rate limit hit, releasing job', [
                'import_job_id' => $this->importJob->historical_import_job_id,
                'retry_after' => $e->retryAfter,
            ]);
            $this->release($e->retryAfter);
            return;
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

        // Check if any records were imported before marking as failed
        $hasImportedData = $this->importJob->records_imported > 0;
        
        // Mark import job status based on whether data was imported
        $this->importJob->update([
            'status' => $hasImportedData ? 'completed' : 'failed',
        ]);

        // Update integration's historical import status but DON'T mark integration as error
        // The integration can still be active and syncing current data even if historical import failed
        $integration = $this->importJob->integration;
        
        // If data was imported, mark as completed (partial), otherwise failed
        $integration->update([
            'historical_import_status' => $hasImportedData ? 'completed' : 'failed',
            'status' => 'active', // Explicitly keep integration active
        ]);

        $logLevel = $hasImportedData ? 'info' : 'warning';
        $message = $hasImportedData 
            ? 'Historical import completed partially with some data imported'
            : 'Historical import failed with no data imported';

        Log::$logLevel($message, [
            'integration_id' => $integration->integration_id,
            'integration_type' => $integration->integration_type,
            'records_imported' => $this->importJob->records_imported,
            'progress_percent' => $this->importJob->progress_percent,
            'error' => $exception->getMessage(),
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
            'BitDefender' => BitDefenderIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($integration);
        }

        return null;
    }
}
