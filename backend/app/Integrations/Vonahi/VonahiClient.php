<?php

namespace App\Integrations\Vonahi;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class VonahiClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.vonahi.io', '/');
    }

    public function testConnection(): bool
    {
        Log::info('VonahiClient::testConnection called - Vonahi integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while Vonahi is a stub
        return true;
    }

    /**
     * Placeholder for future Vonahi API calls.
     */
    public function getSecurityFindings(): array
    {
        Log::info('VonahiClient::getSecurityFindings called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
