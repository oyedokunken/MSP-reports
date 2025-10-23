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
    public $timeout = 300; 

    protected Integration $integration;
    protected SyncLog $syncLog;

    public function __construct(Integration $integration, SyncLog $syncLog)
    {
        $this->integration = $integration;
        $this->syncLog = $syncLog;
    }

    public function handle(RateLimiter $rateLimiter, SyncEngine $syncEngine): void
    {
        try {

            $this->syncLog->update(['status' => 'running']);
            
            $integrationClass = $this->loadIntegrationClass();

            if (!$integrationClass) {
                throw new \Exception("Integration type {$this->integration->integration_type} not found.");
            }
            
            if (!$rateLimiter->waitForSlot($this->integration, 60)) {
                throw new \Exception("Rate limit exceeded, could not acquire slot within timeout.");
            }
            
            $result = $integrationClass->sync();
            
            $rateLimiter->recordRequest($this->integration);
            
            $recordsSynced = count($result['customers'] ?? []);
            $recordsFailed = count($result['errors'] ?? []);
            
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
            
            throw $e;
        }
    }

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

    private function loadIntegrationClass()
    {
        $integrationClasses = [
            'Autotask' => \App\Integrations\Autotask\AutotaskIntegration::class,  // Primary first integration
            'Datto' => \App\Integrations\Datto\DattoIntegration::class,
        ];

        $className = $integrationClasses[$this->integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($this->integration);
        }

        return null;
    }
}
