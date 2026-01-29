<?php

namespace App\Integrations\ThreatLocker;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ThreatLockerClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    protected const CACHE_KEY_PREFIX = 'threatlocker_';

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        // ThreatLocker API base URL
        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.threatlocker.com', '/');
    }

    /**
     * Test connection to ThreatLocker API
     */
    public function testConnection(): bool
    {
        try {
            // Try to get organizations to verify credentials
            $response = $this->makeRequest('/v1/organizations', ['pageSize' => 1]);
            
            if ($response === null) {
                throw new AuthenticationException('ThreatLocker authentication failed - empty response', 401);
            }
            
            Log::info('ThreatLocker: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ThreatLocker: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'ThreatLocker authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all organizations (customers) from ThreatLocker
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getOrganizations(): array
    {
        $allOrganizations = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest('/v1/organizations', [
                'pageSize' => 100,
                'page' => $page,
            ]);

            $organizations = $response['data'] ?? $response['organizations'] ?? $response ?? [];
            
            if (empty($organizations) || !is_array($organizations)) {
                break;
            }

            $allOrganizations = array_merge($allOrganizations, $organizations);

            // Check pagination
            $totalPages = $response['totalPages'] ?? $response['pagination']['totalPages'] ?? 1;
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > 100) {
                Log::warning('ThreatLocker: Reached page limit while fetching organizations', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                break;
            }
        }

        Log::info('ThreatLocker: Fetched organizations', [
            'integration_id' => $this->integration->integration_id,
            'count' => count($allOrganizations),
        ]);

        return $allOrganizations;
    }

    /**
     * Get computers/endpoints for an organization
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getComputers(?string $organizationId = null): array
    {
        $allComputers = [];
        $page = 1;
        $hasMore = true;

        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/computers"
            : '/v1/computers';

        while ($hasMore) {
            $response = $this->makeRequest($endpoint, [
                'pageSize' => 100,
                'page' => $page,
            ]);

            $computers = $response['data'] ?? $response['computers'] ?? $response ?? [];
            
            if (empty($computers) || !is_array($computers)) {
                break;
            }

            $allComputers = array_merge($allComputers, $computers);

            $totalPages = $response['totalPages'] ?? $response['pagination']['totalPages'] ?? 1;
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > 100) {
                break;
            }
        }

        Log::info('ThreatLocker: Fetched computers', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'count' => count($allComputers),
        ]);

        return $allComputers;
    }

    /**
     * Get application policies
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getPolicies(?string $organizationId = null): array
    {
        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/policies"
            : '/v1/policies';

        $response = $this->makeRequest($endpoint, ['pageSize' => 500]);
        
        return $response['data'] ?? $response['policies'] ?? $response ?? [];
    }

    /**
     * Get security events/audit logs
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getSecurityEvents(?string $organizationId = null, ?Carbon $since = null): array
    {
        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/auditlogs"
            : '/v1/auditlogs';

        $params = ['pageSize' => 500];
        
        if ($since) {
            $params['startDate'] = $since->toIso8601String();
        }

        $response = $this->makeRequest($endpoint, $params);
        
        return $response['data'] ?? $response['auditLogs'] ?? $response ?? [];
    }

    /**
     * Get application blocks/denials
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getApplicationBlocks(?string $organizationId = null, ?Carbon $since = null): array
    {
        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/blocks"
            : '/v1/blocks';

        $params = ['pageSize' => 500];
        
        if ($since) {
            $params['startDate'] = $since->toIso8601String();
        }

        $response = $this->makeRequest($endpoint, $params);
        
        return $response['data'] ?? $response['blocks'] ?? $response ?? [];
    }

    /**
     * Make authenticated request to ThreatLocker API
     * 
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $apiKey = $this->credentials['api_key'] ?? null;
        
        if (empty($apiKey)) {
            Log::error('ThreatLocker: API key is missing', [
                'integration_id' => $this->integration->integration_id,
                'credentials_keys' => array_keys($this->credentials),
            ]);
            throw new AuthenticationException('ThreatLocker API key is required', 401);
        }

        $url = $this->baseUrl . $endpoint;

        try {
            Log::info('ThreatLocker API request', [
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
                    'ThreatLocker authentication failed - invalid or expired API key',
                    $response->status()
                );
            }

            if ($response->status() === 429) {
                Log::warning('ThreatLocker: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new \Exception('ThreatLocker API rate limit exceeded');
            }

            if (!$response->successful()) {
                Log::error('ThreatLocker: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('API request failed: ' . $response->body());
            }

            $this->rateLimiter->recordRequest($this->integration);
            
            $data = $response->json();
            Log::info('ThreatLocker API response received', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'record_count' => is_array($data) ? (isset($data['data']) ? count($data['data']) : count($data)) : 0,
            ]);

            return $data;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ThreatLocker: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
