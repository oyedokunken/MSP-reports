<?php

namespace App\Integrations\RocketCyber;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RocketCyberClient
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

        // RocketCyber API base URL
        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.rocketcyber.com', '/');
    }

    /**
     * Test connection to RocketCyber API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/v3/account', ['pageSize' => 1]);
            
            if ($response === null) {
                throw new AuthenticationException('RocketCyber authentication failed - empty response', 401);
            }
            
            Log::info('RocketCyber: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('RocketCyber: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'RocketCyber authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all accounts/customers from RocketCyber
     */
    public function getAccounts(): array
    {
        $allAccounts = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest('/v3/accounts', [
                'pageSize' => 100,
                'page' => $page,
            ]);

            $accounts = $response['data'] ?? $response['accounts'] ?? $response ?? [];
            
            if (empty($accounts) || !is_array($accounts)) {
                break;
            }

            $allAccounts = array_merge($allAccounts, $accounts);

            $totalPages = $response['totalPages'] ?? $response['pagination']['totalPages'] ?? 1;
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > 100) {
                break;
            }
        }

        Log::info('RocketCyber: Fetched accounts', [
            'integration_id' => $this->integration->integration_id,
            'count' => count($allAccounts),
        ]);

        return $allAccounts;
    }

    /**
     * Get security incidents
     */
    public function getSecurityIncidents(?string $accountId = null, ?Carbon $since = null): array
    {
        $params = ['pageSize' => 500];
        
        if ($accountId) {
            $params['accountId'] = $accountId;
        }
        
        if ($since) {
            $params['startDate'] = $since->toIso8601String();
        }

        $response = $this->makeRequest('/v3/incidents', $params);
        
        return $response['data'] ?? $response['incidents'] ?? $response ?? [];
    }

    /**
     * Get threat detections/events
     */
    public function getThreatDetections(?string $accountId = null, ?Carbon $since = null): array
    {
        $params = ['pageSize' => 500];
        
        if ($accountId) {
            $params['accountId'] = $accountId;
        }
        
        if ($since) {
            $params['startDate'] = $since->toIso8601String();
        }

        $response = $this->makeRequest('/v3/events', $params);
        
        return $response['data'] ?? $response['events'] ?? $response ?? [];
    }

    /**
     * Get agents/endpoints
     */
    public function getAgents(?string $accountId = null): array
    {
        $params = ['pageSize' => 500];
        
        if ($accountId) {
            $params['accountId'] = $accountId;
        }

        $response = $this->makeRequest('/v3/agents', $params);
        
        return $response['data'] ?? $response['agents'] ?? $response ?? [];
    }

    /**
     * Make authenticated request to RocketCyber API
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $apiKey = $this->credentials['api_key'] ?? null;
        
        if (empty($apiKey)) {
            Log::error('RocketCyber: API key is missing', [
                'integration_id' => $this->integration->integration_id,
                'credentials_keys' => array_keys($this->credentials),
            ]);
            throw new AuthenticationException('RocketCyber API key is required', 401);
        }

        $url = $this->baseUrl . $endpoint;

        try {
            Log::info('RocketCyber API request', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'url' => $url,
                'params' => $params,
            ]);

            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get($url, $params);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new AuthenticationException(
                    'RocketCyber authentication failed - invalid API key',
                    $response->status()
                );
            }

            if ($response->status() === 429) {
                Log::warning('RocketCyber: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new \Exception('RocketCyber API rate limit exceeded');
            }

            if (!$response->successful()) {
                Log::error('RocketCyber: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('API request failed: ' . $response->body());
            }

            $this->rateLimiter->recordRequest($this->integration);
            
            $data = $response->json();
            Log::info('RocketCyber API response received', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'record_count' => is_array($data) ? (isset($data['data']) ? count($data['data']) : count($data)) : 0,
            ]);

            return $data;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('RocketCyber: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
