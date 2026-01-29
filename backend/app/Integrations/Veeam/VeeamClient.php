<?php

declare(strict_types=1);

namespace App\Integrations\Veeam;

use App\Services\RateLimiter;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

final class VeeamClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.veeam.com', '/');
    }

    public function testConnection(): bool
    {
        Log::info('VeeamClient::testConnection called - Veeam integration not yet implemented', [
            'integration_id' => $this->integration->integration_id,
        ]);

        // Always return true for now so UI doesn't block while Veeam is a stub
        return true;
    }

    /**
     * Placeholder for future Veeam API calls.
     * @return array<int, array<string, mixed>>
     */
    public function getBackupJobs(): array
    {
        Log::info('VeeamClient::getBackupJobs called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future Veeam backup sessions.
     * @return array<int, array<string, mixed>>
     */
    public function getBackupSessions(): array
    {
        Log::info('VeeamClient::getBackupSessions called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }

    /**
     * Placeholder for future Veeam protected VMs.
     * @return array<int, array<string, mixed>>
     */
    public function getProtectedVMs(): array
    {
        Log::info('VeeamClient::getProtectedVMs called - returning placeholder data', [
            'integration_id' => $this->integration->integration_id,
        ]);

        return [];
    }
}
