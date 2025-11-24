<?php

namespace App\Integrations\SAT;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class SATClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.securityawareness.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('SATClient::testConnection called - SAT integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while SAT is a stub
        return true;
    }

    /**
     * Placeholder for future SAT API calls.
     */
    public function getTrainingCompletions(): array
    {
        Log::info('SATClient::getTrainingCompletions called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future SAT phishing simulation results.
     */
    public function getPhishingSimulations(): array
    {
        Log::info('SATClient::getPhishingSimulations called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
