<?php

namespace App\Integrations\AdminDroid;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class AdminDroidClient
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

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.admindroid.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('AdminDroidClient::testConnection called - AdminDroid integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while AdminDroid is a stub
        return true;
    }

    /**
     * Placeholder for future AdminDroid API calls.
     */
    public function getM365Reports(): array
    {
        Log::info('AdminDroidClient::getM365Reports called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future AdminDroid user activity reports.
     */
    public function getUserActivity(): array
    {
        Log::info('AdminDroidClient::getUserActivity called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
