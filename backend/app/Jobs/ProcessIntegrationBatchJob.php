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
                $instance->processBatch($this->type, $this->items);
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
