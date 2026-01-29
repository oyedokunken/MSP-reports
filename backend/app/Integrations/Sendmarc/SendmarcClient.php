<?php

declare(strict_types=1);

namespace App\Integrations\Sendmarc;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\RateLimitExceededException;

final class SendmarcClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected string $bearerToken;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    protected bool $useMockData = false;

    /**
     * @param array<string, mixed> $credentials
     */
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
            
            if (!$response->successful()) {
                $statusCode = $response->status();
                $body = $response->body();
                
                \Log::error('Sendmarc connection test failed', [
                    'status' => $statusCode,
                    'body' => $body
                ]);
                
                throw new AuthenticationException(
                    'Sendmarc authentication failed',
                    $statusCode,
                    $body
                );
            }
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Sendmarc connection test failed', ['error' => $e->getMessage()]);
            
            throw new AuthenticationException(
                'Sendmarc authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDomainSenders(string $accountUuid, string $domainUuid, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        try {
            $params = [];
            if ($startDate) $params['from'] = $startDate->toDateString();
            if ($endDate) $params['to'] = $endDate->toDateString();

            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/senders";
            $response = $this->makeRequest('GET', $endpoint, $params);

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
                'count' => count($senders),
                'from' => $params['from'] ?? 'default',
                'to' => $params['to'] ?? 'default'
            ]);

            return $senders;

        } catch (\Exception $e) {
            \Log::error('Sendmarc API getDomainSenders exception', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getDomainVolumeTotals(string $accountUuid, string $domainUuid, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        try {
            $params = [];
            if ($startDate) $params['from'] = $startDate->toDateString();
            if ($endDate) $params['to'] = $endDate->toDateString();

            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/volume/totals";
            $response = $this->makeRequest('GET', $endpoint, $params);

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDomainVolumeTimeline(string $accountUuid, string $domainUuid, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        try {
            $params = [];
            if ($startDate) $params['from'] = $startDate->toDateString();
            if ($endDate) $params['to'] = $endDate->toDateString();

            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/volume/timeline";
            $response = $this->makeRequest('GET', $endpoint, $params);

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDomainIpAddresses(string $accountUuid, string $domainUuid, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        try {
            $params = [];
            if ($startDate) $params['from'] = $startDate->toDateString();
            if ($endDate) $params['to'] = $endDate->toDateString();

            $endpoint = "/accounts/{$accountUuid}/domains/{$domainUuid}/statistics/dmarc/ip-addresses";
            $response = $this->makeRequest('GET', $endpoint, $params);

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

    /**
     * @param array<string, mixed> $params
     */
    protected function makeRequest(string $method, string $endpoint, array $params = []): Response
    {
        if (!$this->useMockData) {
            if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
                throw new RateLimitExceededException('Rate limit exceeded for Sendmarc API');
            }
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Accept' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

        $loggableHeaders = $headers;
        $loggableHeaders['Authorization'] = 'Bearer ***';

        \Log::debug('Sendmarc API request', [
            'integration_id' => $this->integration->integration_id,
            'method'         => $method,
            'url'            => $url,
            'headers'        => $loggableHeaders,
        ]);

        $request = Http::withHeaders($headers)
            ->timeout(30)
            ->retry(2, 1000);

        $response = match (strtoupper($method)) {
            'GET'  => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            default => $request->send($method, $url, ['json' => $params]),
        };

        \Log::debug('Sendmarc API response', [
            'integration_id' => $this->integration->integration_id,
            'status'         => $response->status(),
            'body_preview'   => substr($response->body(), 0, 500)
        ]);

        $this->rateLimiter->recordRequest($this->integration);

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
