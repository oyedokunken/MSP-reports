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
use Illuminate\Support\Facades\DB;
use App\Integrations\Autotask\AutotaskIntegration;
use App\Integrations\Datto\DattoIntegration;
use App\Integrations\Sendmarc\SendmarcIntegration;
use App\Integrations\ITGlue\ITGlueIntegration;
use App\Integrations\LibreNMS\LibreNMSIntegration;
use App\Integrations\SaaS\SaaSIntegration;
use App\Integrations\BitDefender\BitDefenderIntegration;
use App\Integrations\VeeamOne\VeeamOneIntegration;


class ImportHistoricalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 1800; 
    /** @var array<int, int> */
    public array $backoff = [120, 300, 600, 1200];

    protected HistoricalImportJobModel $importJob;
    protected Carbon $chunkDate;

    public function __construct(HistoricalImportJobModel $importJob, Carbon $chunkDate)
    {
        $this->importJob = $importJob;
        $this->chunkDate = $chunkDate;
        $this->onQueue('historical');
    }

    public function handle(RateLimiter $rateLimiter): void
    {
        // Refresh to get latest status/total_jobs from DB
        $this->importJob->refresh();

        Log::debug('ImportHistoricalDataJob started', [
            'import_job_id' => $this->importJob->historical_import_job_id,
            'chunk_date' => $this->chunkDate->toDateString(),
            'current_status' => $this->importJob->status,
        ]);
        
        try {
            $integration = $this->importJob->integration;

            // Only update to running if it's currently queued
            if ($this->importJob->status === 'queued') {
                DB::table('historical_import_jobs')
                    ->where('historical_import_job_id', $this->importJob->historical_import_job_id)
                    ->update([
                        'status' => 'running',
                        'started_at' => now(),
                        'updated_at' => now(),
                    ]);
                $this->importJob->refresh();
            }
            
            $integrationClass = $this->loadIntegrationClass($integration);

            if (!$integrationClass) {
                throw new \Exception("Integration type {$integration->integration_type} not found.");
            }

            // Process 7 days at a time to balance queue efficiency with progress updates
            // 90-day import = ~13 jobs instead of 90 jobs
            // Progress bar still updates reasonably frequently (every ~week)
            $daysToProcess = 7;
            $currentDate = $this->chunkDate->copy();
            $endDate = $this->chunkDate->copy()->addDays($daysToProcess - 1)->endOfDay();
            
            // Cap at the import job's end date
            if ($endDate->gt($this->importJob->import_end_date)) {
                $endDate = $this->importJob->import_end_date->copy()->endOfDay();
            }

            // Process the chunk (up to 7 days)
            // Set progress callback if supported
            if (method_exists($integrationClass, 'setProgressCallback')) {
                $integrationClass->setProgressCallback(function(int $count, ?float $subProgress = null) use ($integration) {
                    $this->importJob->increment('records_imported', $count);
                    
                    if ($subProgress !== null && $this->importJob->total_jobs > 0) {
                        // Calculate total progress: (completed_chunks + this_chunk_fraction) / total_chunks
                        // We use fresh() to get the latest jobs_completed from other parallel workers
                        $job = $this->importJob->fresh();
                        if ($job) {
                            $baseProgress = $job->jobs_completed / $job->total_jobs;
                            $currentChunkContribution = ($subProgress / 100) * (1 / $job->total_jobs);
                            $newProgress = min(99, round(($baseProgress + $currentChunkContribution) * 100));
                            
                            if ($newProgress > $job->progress_percent) {
                                $job->update(['progress_percent' => $newProgress]);
                                $integration->update(['historical_import_progress' => $newProgress]);
                            }
                        }
                    }
                });
            }

            Log::debug('Calling getHistoricalData', [
                'import_job_id' => $this->importJob->historical_import_job_id,
                'start_date' => $currentDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);
            
            $result = $integrationClass->getHistoricalData(
                $currentDate,
                $endDate
            );

            Log::debug('getHistoricalData completed', [
                'import_job_id' => $this->importJob->historical_import_job_id,
                'total_processed' => $result['total_processed'] ?? count($result['data'] ?? []),
            ]);

            $rateLimiter->recordRequest($integration);


            // Get records count from result (supports both old 'data' array and new 'total_processed' count)
            $recordsImported = $result['total_processed'] ?? count($result['data'] ?? []);
            
            // Update position to the end of this chunk
            $this->importJob->current_position = $endDate;


            // Atomically increment both completed jobs counter AND records imported
            // This ensures parallel jobs don't overwrite each other's counts
            // Try-catch handles old jobs created before jobs_completed column was added
            try {
                DB::table('historical_import_jobs')
                    ->where('historical_import_job_id', $this->importJob->historical_import_job_id)
                    ->increment('jobs_completed');
            } catch (\Exception $e) {
                // Column doesn't exist (old job) - fall back to non-atomic update
                Log::info('jobs_completed column not found, using fallback');
            }
            
            if ($recordsImported > 0) {
                DB::table('historical_import_jobs')
                    ->where('historical_import_job_id', $this->importJob->historical_import_job_id)
                    ->increment('records_imported', $recordsImported);
            }
            
            // Refresh to get updated counters
            $this->importJob->refresh();
            
            // Calculate progress from completed jobs
            if (isset($this->importJob->total_jobs) && $this->importJob->total_jobs > 0) {
                $this->importJob->progress_percent = min(100, round(($this->importJob->jobs_completed / $this->importJob->total_jobs) * 100));
            } else {
                // Fallback to date-based if total_jobs wasn't set
                $totalDays = $this->importJob->import_start_date->diffInDays($this->importJob->import_end_date) ?: 1;
                $completedDays = $this->importJob->import_start_date->diffInDays($endDate);
                $this->importJob->progress_percent = min(100, round(($completedDays / $totalDays) * 100));
            }
            
            $this->importJob->save();

            // Sync progress to the integration model for real-time frontend updates
            $integration->update([
                'historical_import_progress' => $this->importJob->progress_percent
            ]);

            // Check if ALL parallel jobs are complete
            // Use fresh() instead of refresh() to avoid ModelNotFoundException if record was deleted
            $freshJob = $this->importJob->fresh();
            
            // If the job was deleted or doesn't exist anymore, another worker already handled completion
            if (!$freshJob) {
                Log::info('Historical import job already completed by another worker', [
                    'import_job_id' => $this->importJob->historical_import_job_id,
                ]);
                return;
            }
            
            // Determine if import is complete
            // For parallel imports: jobs_completed >= total_jobs
            // For single-chunk/sequential: we've reached or passed the end date
            $allJobsComplete = false;
            
            if (isset($freshJob->total_jobs) && $freshJob->total_jobs > 0) {
                // Parallel job tracking
                $allJobsComplete = $freshJob->jobs_completed >= $freshJob->total_jobs;
            } else {
                // Sequential/single-chunk: check if the current chunk's end date >= import end date
                // $endDate is already set above - it's the END of the chunk we just processed
                $importEndDate = Carbon::parse($freshJob->import_end_date);
                $chunkEndDate = Carbon::parse($endDate);
                $allJobsComplete = $chunkEndDate->gte($importEndDate);
                
                Log::debug('Historical import completion check', [
                    'import_job_id' => $freshJob->historical_import_job_id,
                    'chunk_end' => $chunkEndDate->toDateString(),
                    'import_end' => $importEndDate->toDateString(),
                    'is_complete' => $allJobsComplete,
                ]);
            }
            
            // Mark job as completed if all chunks are done
            if ($allJobsComplete && $freshJob->status !== 'completed') {
                $freshJob->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'progress_percent' => 100,
                ]);

                Log::info('Historical import job marked completed', [
                    'import_job_id' => $freshJob->historical_import_job_id,
                    'records_imported' => $freshJob->records_imported,
                ]);
            }

            // ALWAYS update integration status when job is complete 
            // (handles race conditions where another worker marked it complete)
            if ($freshJob->status === 'completed' || $allJobsComplete) {
                // Only update if still in importing state
                if ($integration->status === 'importing_historical') {
                    $integration->update([
                        'status' => 'active',
                        'historical_import_status' => 'completed',
                    ]);
                    Log::info('Integration status updated to active after historical import', [
                        'integration_id' => $integration->integration_id,
                    ]);
                }
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

        // Send failure notification
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendSyncFailureNotification($integration, $exception->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to send import failure notification', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return object|null
     */
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
            'VeeamOne' => VeeamOneIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($integration);
        }

        return null;
    }
}
