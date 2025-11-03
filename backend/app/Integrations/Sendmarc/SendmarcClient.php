<?php

namespace App\Integrations\Sendmarc;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\RateLimiter;
use App\Models\Integration;

class SendmarcClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected string $bearerToken;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    protected bool $useMockData = false;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        
        // Enable mock mode if credentials contain 'test_' prefix
        $this->useMockData = str_starts_with($credentials['bearer_token'] ?? '', 'test_');
        
        // Sendmarc API endpoint
        $this->baseUrl = rtrim($credentials['endpoint'] ?? 'https://portal.sendmarc.com/api/v1', '/');
        
        // Bearer Token (required for Sendmarc API)
        $this->bearerToken = $credentials['bearer_token'] ?? '';
    }

    public function testConnection(): bool
    {
        try {
            // Test connection by querying accounts
            $response = $this->makeRequest('GET', '/accounts');
            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Sendmarc connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAccounts(): array
    {
        // Return mock data for testing
        if ($this->useMockData) {
            return $this->getMockAccounts();
        }

        try {
            $response = $this->makeRequest('GET', '/accounts');

            if (!$response->successful()) {
                \Log::warning('Sendmarc API getAccounts failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $accounts = $response->json('data') ?? $response->json() ?? [];
            
            \Log::info('Sendmarc API accounts retrieved', [
                'count' => count($accounts)
            ]);

            return $accounts;

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getAccounts exception', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch accounts: {$e->getMessage()}");
        }
    }

    public function getAccountDomains(string $accountUuid): array
    {
        try {
            $response = $this->makeRequest('GET', "/accounts/{$accountUuid}/domains");

            if (!$response->successful()) {
                \Log::warning('Sendmarc API getAccountDomains failed', [
                    'account_uuid' => $accountUuid,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $domains = $response->json('data') ?? $response->json() ?? [];
            
            \Log::info('Sendmarc API domains retrieved', [
                'account_uuid' => $accountUuid,
                'count' => count($domains)
            ]);

            return $domains;

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getAccountDomains exception', [
                'account_uuid' => $accountUuid,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch domains for account: {$e->getMessage()}");
        }
    }

    public function getDomainSenders(string $accountUuid, string $domainUuid): array
    {
        try {
            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/senders";
            $response = $this->makeRequest('GET', $endpoint);

            if (!$response->successful()) {
                \Log::warning('Sendmarc API getDomainSenders failed', [
                    'account_uuid' => $accountUuid,
                    'domain_uuid' => $domainUuid,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $senders = $response->json() ?? [];
            
            \Log::info('Sendmarc API senders retrieved', [
                'account_uuid' => $accountUuid,
                'domain_uuid' => $domainUuid,
                'count' => count($senders)
            ]);

            return $senders;

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getDomainSenders exception', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getDomainVolumeTotals(string $accountUuid, string $domainUuid): array
    {
        try {
            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/volume/totals";
            $response = $this->makeRequest('GET', $endpoint);

            if (!$response->successful()) {
                return [];
            }

            return $response->json() ?? [];

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getDomainVolumeTotals exception', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getDomainVolumeTimeline(string $accountUuid, string $domainUuid): array
    {
        try {
            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/volume/timeline";
            $response = $this->makeRequest('GET', $endpoint);

            if (!$response->successful()) {
                return [];
            }

            return $response->json() ?? [];

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getDomainVolumeTimeline exception', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getDomainIpAddresses(string $accountUuid, string $domainUuid): array
    {
        try {
            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/ip-addresses";
            $response = $this->makeRequest('GET', $endpoint);

            if (!$response->successful()) {
                return [];
            }

            return $response->json() ?? [];

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getDomainIpAddresses exception', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $params = [])
    {
        // Skip rate limiting for mock data mode
        if (!$this->useMockData) {
            // Check rate limit BEFORE each API call
            if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
                throw new \Exception("Rate limit exceeded for Sendmarc API, could not acquire slot within timeout.");
            }
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

        \Log::info('Sendmarc API Request', [
            'method' => $method,
            'url' => $url,
            'headers' => array_merge($headers, ['Authorization' => 'Bearer ***REDACTED***']),
        ]);

        // Make the actual API call
        if ($method === 'GET') {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($url, $params);
        } else {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->send($method, $url, ['json' => $params]);
        }

        \Log::info('Sendmarc API Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => substr($response->body(), 0, 500)
        ]);

        return $response;
    }

    protected function getMockAccounts(): array
    {
        return [
            [
                'uuid' => 'mock-account-uuid-1',
                'name' => 'Mock Account 1',
            ],
            [
                'uuid' => 'mock-account-uuid-2',
                'name' => 'Mock Account 2',
            ]
        ];
    }
}
