<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\RateLimiter;
use App\Services\SyncEngine;
use Illuminate\Support\Facades\Log;

class SyncIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    protected Integration $integration;
    protected SyncLog $syncLog;

    /**
     * Create a new job instance.
     */
    public function __construct(Integration $integration, SyncLog $syncLog)
    {
        $this->integration = $integration;
        $this->syncLog = $syncLog;
    }

    /**
     * Execute the job.
     */
    public function handle(RateLimiter $rateLimiter, SyncEngine $syncEngine): void
    {
        try {
            // Update sync log status
            $this->syncLog->update(['status' => 'running']);

            // Load integration implementation
            $integrationClass = $this->loadIntegrationClass();

            if (!$integrationClass) {
                throw new \Exception("Integration type {$this->integration->integration_type} not found.");
            }

            // Check rate limits
            if (!$rateLimiter->waitForSlot($this->integration, 60)) {
                throw new \Exception("Rate limit exceeded, could not acquire slot within timeout.");
            }

            // Perform sync
            $result = $integrationClass->sync();

            // Record rate limit usage
            $rateLimiter->recordRequest($this->integration);

            // Process sync results
            $recordsSynced = count($result['customers'] ?? []);
            $recordsFailed = count($result['errors'] ?? []);

            // Complete sync log
            $syncEngine->completeSyncLog(
                $this->syncLog,
                'success',
                $recordsSynced,
                $recordsFailed,
                $result['errors'] ?? []
            );

            Log::info('Integration sync completed', [
                'integration_id' => $this->integration->integration_id,
                'records_synced' => $recordsSynced,
            ]);

        } catch (\Exception $e) {
            Log::error('Integration sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            $syncEngine->completeSyncLog(
                $this->syncLog,
                'failed',
                0,
                0,
                [$e->getMessage()]
            );

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Integration sync job failed permanently', [
            'integration_id' => $this->integration->integration_id,
            'error' => $exception->getMessage(),
        ]);

        $this->syncLog->update([
            'status' => 'failed',
            'error_messages' => [$exception->getMessage()],
        ]);
    }

    /**
     * Load integration class.
     */
    private function loadIntegrationClass()
    {
        $integrationClasses = [
            'Datto' => \App\Integrations\Datto\DattoIntegration::class,
            'Autotask' => \App\Integrations\Autotask\AutotaskIntegration::class,
        ];

        $className = $integrationClasses[$this->integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($this->integration);
        }

        return null;
    }
}
