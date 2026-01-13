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
                
                try {
                    $instance->processBatch($this->type, $this->items);
                } catch (\Exception $e) {
                    Log::error("Batch processing failed for Sync #{$this->syncLogId}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // We arguably should re-throw so the job fails and retries?
                    // But if we re-throw, we DON'T decrement the counter, so the sync hangs.
                    // If we catch and continue, we decrement, sync finishes (maybe partial), but data is lost.
                    // Better approach: Decrement here (mark done), but log error. 
                    // Ideally we would have a 'records_failed' counter in Redis too.
                    throw $e;
                } finally {
                    // ALWAYS decrement the pending counter, even on failure
                    if ($this->syncLogId) {
                        try {
                            $redis = \Illuminate\Support\Facades\Redis::connection();
                            $remaining = $redis->decr("sync_log:{$this->syncLogId}:pending");
                            
                            // UPDATE LIVE RECORD COUNT
                            if (method_exists($instance, 'getLastBatchProcessedCount')) {
                                $addedRecords = $instance->getLastBatchProcessedCount();
                                if ($addedRecords > 0) {
                                    \App\Models\SyncLog::where('sync_id', $this->syncLogId)
                                        ->increment('records_synced', $addedRecords);
                                }
                            }

                            Log::info("Method batch finished. Remaining: {$remaining}", ['sync_id' => $this->syncLogId]);
                            
                            if ($remaining <= 0) {
                                $syncEngine = app(\App\Services\SyncEngine::class);
                                $syncLog = \App\Models\SyncLog::find($this->syncLogId);
                                
                                if ($syncLog) {
                                     // Mark success (or we could check for failures if we tracked them)
                                     // Pass NULL for records_synced so we don't overwrite the incremented value
                                     // Or re-fetch the latest value
                                     $syncLog->refresh();
                                     $syncEngine->completeSyncLog($syncLog, 'success', $syncLog->records_synced ?? 0);
                                     
                                     Log::info("Final batch completed for Sync #{$this->syncLogId}. Marked as success.");
                                     
                                     // Clean up redis key
                                     $redis->del("sync_log:{$this->syncLogId}:pending");
                                }
                            }
                        } catch (\Exception $redisEx) {
                           Log::error("Failed to update sync status in Redis", ['error' => $redisEx->getMessage()]);
                        }
                    }
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
}
