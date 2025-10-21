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

class ImportHistoricalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    protected HistoricalImportJobModel $importJob;
    protected Carbon $chunkDate;

    /**
     * Create a new job instance.
     */
    public function __construct(HistoricalImportJobModel $importJob, Carbon $chunkDate)
    {
        $this->importJob = $importJob;
        $this->chunkDate = $chunkDate;
    }

    /**
     * Execute the job.
     */
    public function handle(RateLimiter $rateLimiter): void
    {
        try {
            $integration = $this->importJob->integration;

            // Update job status
            if ($this->importJob->status === 'queued') {
                $this->importJob->update([
                    'status' => 'running',
                    'started_at' => now(),
                ]);
            }

            // Load integration class
            $integrationClass = $this->loadIntegrationClass($integration);

            if (!$integrationClass) {
                throw new \Exception("Integration type {$integration->integration_type} not found.");
            }

            // Check rate limits (use 50% of normal limit for historical imports)
            if (!$rateLimiter->waitForSlot($integration, 120)) {
                throw new \Exception("Rate limit exceeded for historical import.");
            }

            // Import data for this chunk (single day)
            $result = $integrationClass->getHistoricalData(
                $this->chunkDate,
                $this->chunkDate->copy()->endOfDay()
            );

            // Record rate limit usage
            $rateLimiter->recordRequest($integration);

            // Update progress
            $recordsImported = count($result['data'] ?? []);
            $this->importJob->records_imported += $recordsImported;
            $this->importJob->current_position = $this->chunkDate;

            // Calculate progress percentage
            $totalDays = $this->importJob->import_start_date->diffInDays($this->importJob->import_end_date);
            $completedDays = $this->importJob->import_start_date->diffInDays($this->chunkDate);
            $this->importJob->progress_percent = min(100, round(($completedDays / $totalDays) * 100));

            $this->importJob->save();

            // Queue next chunk if not complete
            $nextDate = $this->chunkDate->copy()->addDay();
            if ($nextDate->lte($this->importJob->import_end_date)) {
                ImportHistoricalDataJob::dispatch($this->importJob, $nextDate);
            } else {
                // Import complete
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

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Historical import job failed permanently', [
            'import_job_id' => $this->importJob->historical_import_job_id,
            'chunk_date' => $this->chunkDate->toDateString(),
            'error' => $exception->getMessage(),
        ]);

        $this->importJob->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Load integration class.
     */
    private function loadIntegrationClass(Integration $integration)
    {
        $integrationClasses = [
            'Datto' => \App\Integrations\Datto\DattoIntegration::class,
            'Autotask' => \App\Integrations\Autotask\AutotaskIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($integration);
        }

        return null;
    }
}
