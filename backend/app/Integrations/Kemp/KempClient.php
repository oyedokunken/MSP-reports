<?php

namespace App\Integrations\Kemp;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class KempClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://kemp.example.com/api', '/');
    }

    public function testConnection(): bool
    {
        Log::info('KempClient::testConnection called - Kemp integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn\'t block while Kemp is a stub
        return true;
    }

    /**
     * Placeholder for future Kemp API calls.
     */
    public function getStatus(): array
    {
        Log::info('KempClient::getStatus called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
