<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
use App\Integrations\VeeamOne\VeeamOneIntegration;

class SyncRecentDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1200; // 20 minutes for data import
    /** @var array<int, int> */
    public array $backoff = [300];

    protected Integration $integration;
    protected int $daysBack;

    public function __construct(Integration $integration, int $daysBack = 7)
    {
        $this->integration = $integration;
        $this->daysBack = $daysBack;
    }

    public function handle(RateLimiter $rateLimiter): void
    {
        try {
            Log::info('SyncRecentDataJob: Starting', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => $this->integration->integration_type,
                'days_back' => $this->daysBack,
            ]);

            $integrationClass = $this->loadIntegrationClass($this->integration);

            if (!$integrationClass) {
                throw new \Exception("Integration type {$this->integration->integration_type} not found.");
            }

            // Wait for rate limiter slot
            if (!$rateLimiter->waitForSlot($this->integration, 120)) {
                throw new \Exception("Rate limit exceeded for recent data sync.");
            }

            $startDate = now()->subDays($this->daysBack);
            $endDate = now();

            Log::info('SyncRecentDataJob: Pulling recent data', [
                'integration_id' => $this->integration->integration_id,
                'date_range' => [$startDate->toDateString(), $endDate->toDateString()],
            ]);

            $result = $integrationClass->getHistoricalData($startDate, $endDate);

            $rateLimiter->recordRequest($this->integration);

            $recordsImported = count($result['data'] ?? []);
            $errors = $result['errors'] ?? [];

            Log::info('SyncRecentDataJob: Completed', [
                'integration_id' => $this->integration->integration_id,
                'records_imported' => $recordsImported,
                'errors' => count($errors),
            ]);

            // Update integration's last historical sync timestamp
            $this->integration->update([
                'last_historical_sync_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('SyncRecentDataJob: Failed', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => $this->integration->integration_type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncRecentDataJob: Failed permanently', [
            'integration_id' => $this->integration->integration_id,
            'integration_type' => $this->integration->integration_type,
            'error' => $exception->getMessage(),
        ]);

        // Send failure notification
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendSyncFailureNotification($this->integration, $exception->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to send sync failure notification', ['error' => $e->getMessage()]);
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
            'VeeamOne' => VeeamOneIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($integration);
        }

        return null;
    }
}
