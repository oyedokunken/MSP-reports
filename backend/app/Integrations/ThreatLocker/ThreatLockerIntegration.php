<?php

namespace App\Integrations\ThreatLocker;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ThreatLockerIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected ThreatLockerClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new ThreatLockerClient($integration->credentials ?? [], $rateLimiter, $integration);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        Log::info('ThreatLockerIntegration::sync called - not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [
            'records_synced' => 0,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => 0,
            'errors' => ['ThreatLocker integration not implemented yet'],
        ];
    }

    public function getCustomers(): array
    {
        // No customer concept defined for ThreatLocker yet
        return [];
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'data' => [],
            'errors' => ['ThreatLocker historical data not implemented yet'],
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'ThreatLocker',
            'description' => 'ThreatLocker application allowlisting and ringfencing integration (placeholder)',
            'data_types' => [
                'threatlocker_policy',
                'threatlocker_security_event',
                'allowlist_policy',
                'ringfencing_policy',
            ],
            'supports_historical' => false,
            'historical_range_days' => 0,
        ];
    }

    public function disconnect(): void
    {
        Log::info('ThreatLockerIntegration::disconnect called - nothing to do for placeholder integration', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        return $this->authenticate();
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }
}
