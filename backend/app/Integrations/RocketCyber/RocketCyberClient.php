<?php

namespace App\Integrations\RocketCyber;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class RocketCyberClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.rocketcyber.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('RocketCyberClient::testConnection called - RocketCyber integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while RocketCyber is a stub
        return true;
    }

    /**
     * Placeholder for future RocketCyber API calls.
     */
    public function getSecurityIncidents(): array
    {
        Log::info('RocketCyberClient::getSecurityIncidents called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future RocketCyber threat detections.
     */
    public function getThreatDetections(): array
    {
        Log::info('RocketCyberClient::getThreatDetections called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
