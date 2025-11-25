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
        
        // Support both 'api_token' and 'bearer_token' for backwards compatibility
        $token = $credentials['api_token'] ?? $credentials['bearer_token'] ?? '';
        $this->useMockData = str_starts_with($token, 'test_');
        
        // Support both 'base_url' and 'endpoint' for backwards compatibility
        $this->baseUrl = rtrim($credentials['base_url'] ?? $credentials['endpoint'] ?? 'https://portal.sendmarc.com/api/v1', '/');
        
        $this->bearerToken = $token;
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/accounts');
            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Sendmarc connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAccounts(): array
    {
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
        if (!$this->useMockData) {
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
