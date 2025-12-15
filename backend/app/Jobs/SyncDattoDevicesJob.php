<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Integration;
use App\Integrations\Datto\DattoIntegration;
use Illuminate\Support\Facades\Log;

class SyncDattoDevicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 20; // Allow multiple retries/releases for rate limits
    public $timeout = 1800; // 30 minutes - fetching all devices can take time due to rate limits

    protected Integration $integration;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
    }

    public function handle(): void
    {
        Log::info('SyncDattoDevicesJob: Starting device sync', [
            'integration_id' => $this->integration->integration_id,
        ]);

        try {
            $dattoIntegration = new DattoIntegration($this->integration);
            
            // We call a public method on DattoIntegration to perform the sync
            // This method needs to be public in DattoIntegration
            $dattoIntegration->syncDevices();

            Log::info('SyncDattoDevicesJob: Device sync completed', [
                'integration_id' => $this->integration->integration_id,
            ]);

        } catch (\App\Integrations\Exceptions\RateLimitException $e) {
            Log::warning('SyncDattoDevicesJob: Rate limit hit, releasing job back to queue', [
                'integration_id' => $this->integration->integration_id,
                'delay' => $e->retryAfter,
            ]);
            $this->release($e->retryAfter);
            return;
        } catch (\Exception $e) {
            Log::error('SyncDattoDevicesJob: Device sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            // We don't re-throw here to avoid clogging the queue with retries
            // The error is logged, and the next scheduled sync will try again
        }
    }
}
