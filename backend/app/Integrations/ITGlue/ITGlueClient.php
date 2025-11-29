<?php

namespace App\Integrations\ITGlue;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;

class ITGlueClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected bool $useMockData = false;
    
    protected const DELAY_BETWEEN_PAGES_MS = 100;
    protected const MAX_PAGES = 10;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        
        $this->baseUrl = rtrim($credentials['base_url'] ?? 'https://api.au.itglue.com', '/');
        $this->useMockData = str_starts_with($credentials['api_key'] ?? '', 'test_');
    }

    public function testConnection(): bool
    {
        // Don't skip authentication check even in mock mode - always validate credentials
        try {
            $response = $this->makeRequest('/organizations', ['page' => ['size' => 1]]);
            
            if (empty($response)) {
                throw new AuthenticationException(
                    'ITGlue authentication failed - empty response',
                    401
                );
            }
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ITGlue: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            // Convert generic exception to AuthenticationException
            throw new AuthenticationException(
                'ITGlue authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    public function getOrganizations(): array
    {
        if ($this->useMockData) {
            return $this->getMockOrganizations();
        }
        
        return $this->fetchAllPages('/organizations', ['page' => ['size' => 50]]);
    }

    public function getOrganization(string $id): ?array
    {
        if ($this->useMockData) {
            return null;
        }
        
        try {
            $response = $this->makeRequest("/organizations/{$id}");
            return $response['data'] ?? null;
        } catch (\Exception $e) {
            Log::error('ITGlue: Failed to fetch organization', [
                'integration_id' => $this->integration->integration_id,
                'organization_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getFlexibleAssetTypes(): array
    {
        if ($this->useMockData) {
            return [];
        }
        
        return $this->fetchAllPages('/flexible_asset_types', ['page' => ['size' => 50]]);
    }

    public function getFlexibleAssets(?int $typeId = null): array
    {
        if ($this->useMockData) {
            return [];
        }
        
        $params = ['page' => ['size' => 1000], 'include' => 'organization'];
        
        if ($typeId) {
            $params['filter'] = ['flexible_asset_type_id' => $typeId];
        }
        
        return $this->fetchAllPages('/flexible_assets', $params);
    }

    public function getSslCertificates(string $expirationFilter = 'next90days'): array
    {
        if ($this->useMockData) {
            return [];
        }
        
        $params = [
            'filter' => ['expiration' => $expirationFilter],
            'page' => ['size' => 50]
        ];
        
        return $this->fetchAllPages('/ssl_certificates', $params);
    }

    public function getDomains(string $expirationFilter = 'next90days'): array
    {
        if ($this->useMockData) {
            return [];
        }
        
        $params = [
            'filter' => ['expiration' => $expirationFilter],
            'page' => ['size' => 50]
        ];
        
        return $this->fetchAllPages('/domains', $params);
    }

    protected function fetchAllPages(string $endpoint, array $params = []): array
    {
        $allData = [];
        $page = 1;
        $hasMore = true;
        
        while ($hasMore && $page <= self::MAX_PAGES) {
            $params['page']['number'] = $page;
            
            $response = $this->makeRequest($endpoint, $params);
            
            $data = $response['data'] ?? [];
            $allData = array_merge($allData, $data);
            
            $meta = $response['meta'] ?? [];
            $currentPage = $meta['current-page'] ?? $page;
            $totalPages = $meta['total-pages'] ?? 1;
            
            $hasMore = $currentPage < $totalPages;
            $page++;
            
            if ($hasMore) {
                usleep(self::DELAY_BETWEEN_PAGES_MS * 1000);
            }
            
            if ($page > self::MAX_PAGES) {
                Log::warning('ITGlue: Reached page limit while fetching data', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'pages_fetched' => $page - 1,
                ]);
            }
        }
        
        Log::info('ITGlue: Fetched paginated data', [
            'integration_id' => $this->integration->integration_id,
            'endpoint' => $endpoint,
            'total_records' => count($allData),
            'pages' => $page - 1,
        ]);
        
        return $allData;
    }

    protected function makeRequest(string $endpoint, array $params = []): array
    {
        if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
            throw new \Exception("Rate limit exceeded for ITGlue API");
        }

        $url = $this->baseUrl . $endpoint;
        
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->credentials['api_key'],
                'Content-Type' => 'application/vnd.api+json',
                'Accept' => 'application/vnd.api+json',
            ])->get($url, $params);

            if ($response->status() === 429) {
                Log::warning('ITGlue: Rate limit hit, waiting 60 seconds', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                sleep(60);
                return $this->makeRequest($endpoint, $params);
            }

            if ($response->status() === 401) {
                Log::error('ITGlue: Authentication failed - check API key and region', [
                    'integration_id' => $this->integration->integration_id,
                    'base_url' => $this->baseUrl,
                ]);
                throw new \RuntimeException("Authentication failed. Check API key and ensure base URL matches your account region.");
            }

            if (!$response->successful()) {
                Log::error('ITGlue: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \RuntimeException("API request failed: " . $response->body());
            }

            $this->rateLimiter->recordRequest($this->integration);
            
            return $response->json();
        } catch (\Exception $e) {
            Log::error('ITGlue: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getMockOrganizations(): array
    {
        Log::info('ITGlue Client: Using mock data mode');
        
        return [
            [
                'id' => '1',
                'type' => 'organizations',
                'attributes' => [
                    'name' => 'ACME Corporation',
                    'short-name' => 'ACME',
                    'organization-type-name' => 'Customer',
                    'organization-status-name' => 'Active',
                    'created-at' => '2024-01-01T00:00:00.000Z',
                    'updated-at' => '2024-01-01T00:00:00.000Z',
                ],
            ],
            [
                'id' => '2',
                'type' => 'organizations',
                'attributes' => [
                    'name' => 'Beta Solutions',
                    'short-name' => 'Beta',
                    'organization-type-name' => 'Customer',
                    'organization-status-name' => 'Active',
                    'created-at' => '2024-01-01T00:00:00.000Z',
                    'updated-at' => '2024-01-01T00:00:00.000Z',
                ],
            ],
            [
                'id' => '3',
                'type' => 'organizations',
                'attributes' => [
                    'name' => 'TechStart Inc',
                    'short-name' => 'TechStart',
                    'organization-type-name' => 'Customer',
                    'organization-status-name' => 'Active',
                    'created-at' => '2024-01-01T00:00:00.000Z',
                    'updated-at' => '2024-01-01T00:00:00.000Z',
                ],
            ],
        ];
    }
}
