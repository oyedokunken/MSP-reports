<?php

namespace App\Integrations\WatchguardAuthPoint;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class WatchguardAuthPointClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.authpoint.watchguard.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('WatchguardAuthPointClient::testConnection called - WatchguardAuthPoint integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while WatchguardAuthPoint is a stub
        return true;
    }

    /**
     * Placeholder for future WatchGuard AuthPoint API calls.
     */
    public function getAuthenticationEvents(): array
    {
        Log::info('WatchguardAuthPointClient::getAuthenticationEvents called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future WatchGuard AuthPoint user enrollments.
     */
    public function getUserEnrollments(): array
    {
        Log::info('WatchguardAuthPointClient::getUserEnrollments called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
