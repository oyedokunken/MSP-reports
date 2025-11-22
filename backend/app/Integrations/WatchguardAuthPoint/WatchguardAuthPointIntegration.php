<?php

namespace App\Integrations\WatchguardAuthPoint;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WatchguardAuthPointIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected WatchguardAuthPointClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new WatchguardAuthPointClient($integration->credentials ?? [], $rateLimiter, $integration);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        Log::info('WatchguardAuthPointIntegration::sync called - not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [
            'records_synced' => 0,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => 0,
            'errors' => ['WatchguardAuthPoint integration not implemented yet'],
        ];
    }

    public function getCustomers(): array
    {
        // No customer concept defined for WatchguardAuthPoint yet
        return [];
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'data' => [],
            'errors' => ['WatchguardAuthPoint historical data not implemented yet'],
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'WatchguardAuthPoint',
            'description' => 'WatchGuard AuthPoint multi-factor authentication integration (placeholder)',
            'data_types' => [
                'watchguardauthpoint_authentication_event',
                'watchguardauthpoint_user_enrollment',
                'mfa_authentication',
                'device_registration',
            ],
            'supports_historical' => false,
            'historical_range_days' => 0,
        ];
    }

    public function disconnect(): void
    {
        Log::info('WatchguardAuthPointIntegration::disconnect called - nothing to do for placeholder integration', [
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
