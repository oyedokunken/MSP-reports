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

    public $tries = 3;
    public $timeout = 120; // 2 minutes per batch

    protected Integration $integration;
    protected string $type;
    protected array $items;
    protected ?int $syncLogId;

    /**
     * Create a new job instance.
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
                $batchSuccess = false;
                
                try {
                    $instance->processBatch($this->type, $this->items);
                    $batchSuccess = true;
                    
                    // Only update counters on success
                    if ($this->syncLogId) {
                        $this->updateBatchCounters($instance, true);
                    }
                } catch (\Exception $e) {
                    Log::error("Batch processing failed for Sync #{$this->syncLogId}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'attempt' => $this->attempts()
                    ]);
                    
                    // Track failed batch
                    if ($this->syncLogId) {
                        $this->updateBatchCounters($instance, false);
                    }
                    
                    // Re-throw to trigger retry
                    throw $e;
                }
                
            } else {
                throw new \Exception("Integration {$className} does not support batch processing");
            }

        } catch (\Exception $e) {
            Log::error('Batch processing failed', [
                'integration_id' => $this->integration->integration_id,
                'type' => $this->type,
                'count' => count($this->items),
                'error' => $e->getMessage()
            ]);
            throw $e; // Retry
        }
    }

    /**
     * Update batch counters in Redis (only on success to avoid race conditions)
     */
    private function updateBatchCounters($instance, bool $success): void
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $ttl = 7200; // 2 hours - keys expire if sync hangs
            
            if ($success) {
                $key = "sync_log:{$this->syncLogId}:pending";
                
                // Decrement pending counter only on success
                $remaining = $redis->decr($key);
                $redis->expire($key, $ttl); // Refresh TTL
                
                // Update live record count
                if (method_exists($instance, 'getLastBatchProcessedCount')) {
                    $addedRecords = $instance->getLastBatchProcessedCount();
                    if ($addedRecords > 0) {
                        \App\Models\SyncLog::where('sync_id', $this->syncLogId)
                            ->increment('records_synced', $addedRecords);
                    }
                }

                Log::info("Batch completed successfully. Remaining: {$remaining}", [
                    'sync_id' => $this->syncLogId,
                    'type' => $this->type
                ]);
                
                // Check if all batches complete
                if ($remaining <= 0) {
                    $this->completeSyncIfAllBatchesDone($redis);
                }
            } else {
                // Track failed batch with TTL
                $failedKey = "sync_log:{$this->syncLogId}:failed";
                $redis->incr($failedKey);
                $redis->expire($failedKey, $ttl);
                
                Log::warning("Batch failed, will retry", [
                    'sync_id' => $this->syncLogId,
                    'type' => $this->type,
                    'attempt' => $this->attempts()
                ]);
            }
        } catch (\Exception $redisEx) {
            Log::error("Failed to update batch counters in Redis", [
                'error' => $redisEx->getMessage(),
                'sync_id' => $this->syncLogId
            ]);
        }
    }

    /**
     * Complete sync if all batches are done
     */
    private function completeSyncIfAllBatchesDone($redis): void
    {
        $syncEngine = app(\App\Services\SyncEngine::class);
        $syncLog = \App\Models\SyncLog::find($this->syncLogId);
        
        if (!$syncLog) {
            return;
        }

        $failedCount = (int) $redis->get("sync_log:{$this->syncLogId}:failed") ?: 0;
        
        // Refresh to get latest record count
        $syncLog->refresh();
        
        // Determine status based on failures
        $status = $failedCount > 0 ? 'partial_success' : 'success';
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
