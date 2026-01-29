<?php

namespace App\Jobs;

use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIntegrationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes per batch

    protected Integration $integration;
    protected string $type;
    /** @var array<int, mixed> */
    protected array $items;
    protected ?int $syncLogId;

    /**
     * Create a new job instance.
     * 
     * @param array<int, mixed> $items
     */
    public function __construct(Integration $integration, string $type, array $items, ?int $syncLogId = null)
    {
        $this->integration = $integration;
        $this->type = $type;
        $this->items = $items;
        $this->syncLogId = $syncLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $className = "App\\Integrations\\{$this->integration->integration_type}\\{$this->integration->integration_type}Integration";
            
            if (!class_exists($className)) {
                throw new \Exception("Integration class {$className} not found");
            }

            $instance = new $className($this->integration);
            
            // Inject Heartbeat Callback
            if (method_exists($instance, 'setHeartbeatCallback') && $this->syncLogId) {
                $instance->setHeartbeatCallback(function() {
                     \Illuminate\Support\Facades\DB::table('sync_logs')
                        ->where('sync_id', $this->syncLogId)
                        ->update(['last_heartbeat_at' => now()]);
                });
            }
            
            if (method_exists($instance, 'processBatch')) {
                try {
                    $instance->processBatch($this->type, $this->items);
                    
                    if ($this->syncLogId) {
                        $this->updateBatchCounters($instance, true);
                    }
                } catch (\Exception $e) {
                    Log::error("Batch processing failed for Sync #{$this->syncLogId}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'attempt' => $this->attempts()
                    ]);
                    
                    if ($this->syncLogId) {
                        $this->updateBatchCounters($instance, false);
                    }
                    
                    throw $e;
                }
                
            } else {
                throw new \Exception("Integration {$className} does not support batch processing");
            }

        } catch (\Exception $e) {
            Log::error('Batch setup/processing failed', [
                'integration_id' => $this->integration->integration_id,
                'type' => $this->type,
                'sync_id' => $this->syncLogId,
                'error' => $e->getMessage()
            ]);
            
            // If we hit an error here (e.g. class not found) and we haven't updated counters yet, 
            // we should still decrement to avoid hanging the sync.
            if ($this->syncLogId && $this->attempts() >= $this->tries) {
                try {
                    $redis = \Illuminate\Support\Facades\Redis::connection();
                    $pendingKey = "sync_log:{$this->syncLogId}:pending";
                    $redis->decr($pendingKey);
                } catch (\Exception $ex) {}
            }
            
            throw $e; // Retry
        }
    }

    /**
     * Update batch counters in Redis
     * 
     * @param object $instance
     */
    private function updateBatchCounters($instance, bool $success): void
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $ttl = 7200; // 2 hours
            $pendingKey = "sync_log:{$this->syncLogId}:pending";
            $failedKey  = "sync_log:{$this->syncLogId}:failed";
            
            if ($success) {
                // Update live record count
                if (method_exists($instance, 'getLastBatchProcessedCount')) {
                    $addedRecords = $instance->getLastBatchProcessedCount();
                    if ($addedRecords > 0) {
                        \App\Models\SyncLog::where('sync_id', $this->syncLogId)
                            ->increment('records_synced', $addedRecords);
                    }
                }

                // Decrement pending counter
                $remaining = $redis->decr($pendingKey);
                $redis->expire($pendingKey, $ttl);

                Log::info("Batch completed successfully. Remaining: {$remaining}", [
                    'sync_id' => $this->syncLogId,
                    'type' => $this->type
                ]);
                
                if ($remaining <= 0) {
                    $this->completeSyncIfAllBatchesDone($redis);
                }
            } else {
                // This is a failure of ONE ATTEMPT. 
                // We only decrement pending if we have run out of tries.
                if ($this->attempts() >= $this->tries) {
                    $redis->incr($failedKey);
                    $redis->expire($failedKey, $ttl);
                    
                    $remaining = $redis->decr($pendingKey);
                    $redis->expire($pendingKey, $ttl);

                    Log::warning("Batch failed permanently after all retries. Remaining: {$remaining}", [
                        'sync_id' => $this->syncLogId,
                        'type' => $this->type
                    ]);

                    if ($remaining <= 0) {
                        $this->completeSyncIfAllBatchesDone($redis);
                    }
                } else {
                    Log::warning("Batch attempt failed, will retry", [
                        'sync_id' => $this->syncLogId,
                        'type' => $this->type,
                        'attempt' => $this->attempts()
                    ]);
                }
            }
        } catch (\Exception $redisEx) {
            Log::error("Failed to update batch counters in Redis", [
                'error' => $redisEx->getMessage(),
                'sync_id' => $this->syncLogId
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // One final safety check to ensure we don't leave the sync stuck
        // if the job system discards this job.
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $pendingKey = "sync_log:{$this->syncLogId}:pending";
            $failedKey  = "sync_log:{$this->syncLogId}:failed";

            $redis->incr($failedKey);
            $remaining = $redis->decr($pendingKey);
            
            Log::error("Batch job failed permanently (failed() hook). Remaining: {$remaining}", [
                'sync_id' => $this->syncLogId,
                'error' => $exception->getMessage()
            ]);

            if ($remaining <= 0) {
                $this->completeSyncIfAllBatchesDone($redis);
            }
        } catch (\Exception $e) {
            // Suppression here to avoid secondary failures
        }
    }

    /**
     * Complete sync if all batches are done
     * 
     * @param \Illuminate\Redis\Connections\Connection $redis
     */
    private function completeSyncIfAllBatchesDone($redis): void
    {
        // Check Master Lock - don't complete if main job is still dispatching
        if ((int) $redis->get("sync_log:{$this->syncLogId}:master_running") === 1) {
            return;
        }

        $syncLog = \App\Models\SyncLog::find($this->syncLogId);
        if (!$syncLog) {
            return;
        }

        $syncEngine = app(\App\Services\SyncEngine::class);
        $failedCount = (int) $redis->get("sync_log:{$this->syncLogId}:failed") ?: 0;
        
        // Refresh to get latest record count
        $syncLog->refresh();
        
        // Determine status based on failures
        $status = $failedCount > 0 ? 'partial' : 'success';
        $errors = [];
        
        if ($failedCount > 0) {
            $errors[] = "{$failedCount} batch(es) failed during processing";
        }
        
        $syncEngine->completeSyncLog(
            $syncLog, 
            $status, 
            $syncLog->records_synced ?? 0,
            $failedCount,
            $errors
        );
        
        Log::info("All batches completed for Sync #{$this->syncLogId}", [
            'status' => $status,
            'failed_batches' => $failedCount
        ]);
        
        // Clean up Redis keys
        $redis->del("sync_log:{$this->syncLogId}:pending");
        $redis->del("sync_log:{$this->syncLogId}:failed");
    }
}
