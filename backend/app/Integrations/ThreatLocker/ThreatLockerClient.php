<?php

namespace App\Integrations\ThreatLocker;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class ThreatLockerClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.threatlocker.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('ThreatLockerClient::testConnection called - ThreatLocker integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while ThreatLocker is a stub
        return true;
    }

    /**
     * Placeholder for future ThreatLocker API calls.
     */
    public function getPolicies(): array
    {
        Log::info('ThreatLockerClient::getPolicies called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future ThreatLocker security events.
     */
    public function getSecurityEvents(): array
    {
        Log::info('ThreatLockerClient::getSecurityEvents called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
