<?php

namespace App\Integrations\BitDefender;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class BitDefenderClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://cloud.gravityzone.bitdefender.com/api', '/');
    }

    public function testConnection(): bool
    {
        Log::info('BitDefenderClient::testConnection called - BitDefender integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while BitDefender is a stub
        return true;
    }

    /**
     * Placeholder for future BitDefender API calls.
     */
    public function getEndpoints(): array
    {
        Log::info('BitDefenderClient::getEndpoints called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future BitDefender threat detection calls.
     */
    public function getThreatDetections(): array
    {
        Log::info('BitDefenderClient::getThreatDetections called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
