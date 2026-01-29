<?php

namespace App\Integrations\Datto;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\RateLimitExceededException;

class DattoClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected bool $useMockData = false;
    protected ?string $accessToken = null;
    
    protected const TOKEN_CACHE_KEY_PREFIX = 'datto_rmm_token_';
    protected const TOKEN_EXPIRY = 99 * 3600;

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        
        if (!empty($credentials['rmm_url'])) {
            $this->baseUrl = rtrim($credentials['rmm_url'], '/');
            $this->useMockData = str_starts_with($credentials['rmm_api_key'] ?? '', 'test_');
        } elseif (!empty($credentials['base_url'])) {
            $this->baseUrl = rtrim($credentials['base_url'], '/');
            $this->useMockData = str_starts_with($credentials['api_key'] ?? '', 'test_');
        } else {
            $this->baseUrl = 'https://api.datto.com';
            $this->useMockData = true;
        }
        
        // Don't get token in constructor - let it fail in testConnection() instead
        // This allows proper error handling in the authentication flow
    }
    
    protected function hasRmmCredentials(): bool
    {
        return !empty($this->credentials['rmm_url']) 
            && !empty($this->credentials['rmm_api_key']) 
            && !empty($this->credentials['rmm_api_secret']);
    }
    
    protected function hasSaasCredentials(): bool
    {
        return !empty($this->credentials['saas_public_key']) 
            && !empty($this->credentials['saas_private_key']);
    }

    protected function getAccessToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id;
        
        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        try {
            $key = $this->credentials['rmm_api_key'] ?? null;
            $secret = $this->credentials['rmm_api_secret'] ?? null;

            if (empty($key) || empty($secret)) {
                throw new \RuntimeException('RMM API key or secret is empty');
            }

            $tokenUrl = $this->baseUrl . '/auth/oauth/token';
            
            Log::info('Datto RMM: Requesting access token', [
                'integration_id' => $this->integration->integration_id,
                'url' => $tokenUrl,
            ]);
            
            $response = Http::retry(3, 1000)
              ->timeout(30)
              ->connectTimeout(10)
              ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->withBasicAuth('public-client', 'public')
              ->asForm()
              ->post($tokenUrl, [
                'grant_type' => 'password',
                'username' => $key,
                'password' => $secret
            ]);

            $responseData = $response->json();
            
            // Check for error in response body (Datto returns 200 with error object)
            if (isset($responseData['error'])) {
                $errorMessage = $responseData['error_description'] ?? $responseData['error'] ?? 'Authentication failed';
                
                Log::error('Datto RMM: Authentication error', [
                    'integration_id' => $this->integration->integration_id,
                    'error' => $responseData['error'],
                    'error_description' => $responseData['error_description'] ?? null,
                ]);
                
                throw new AuthenticationException(
                    'Datto RMM authentication failed: ' . $errorMessage,
                    401
                );
            }

            // Check HTTP status
            if (!$response->successful()) {
                $statusCode = $response->status();
                $body = $response->body();
                
                Log::error('Datto RMM: Failed to obtain access token', [
                    'integration_id' => $this->integration->integration_id,
                    'status' => $statusCode,
                    'body' => $body
                ]);
                
                throw new AuthenticationException(
                    'Datto RMM authentication failed',
                    $statusCode,
                    $body
                );
            }

            // Check for access token in response
            if (empty($responseData['access_token'])) {
                Log::error('Datto RMM: Invalid token response', [
                    'integration_id' => $this->integration->integration_id,
                    'response' => $responseData
                ]);
                
                throw new AuthenticationException(
                    'Datto RMM authentication failed - invalid token response',
                    401
                );
            }

            $token = $responseData['access_token'];
            Cache::put($cacheKey, $token, self::TOKEN_EXPIRY);
            
            Log::info('Datto RMM: Access token obtained successfully', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return $token;
        } catch (AuthenticationException $e) {
            // Re-throw AuthenticationException as-is
            throw $e;
        } catch (\Exception $e) {
            Log::error('Datto RMM: Error obtaining access token', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'Datto RMM authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }
    
    public function testConnection(): bool
    {
        try {
            if ($this->hasRmmCredentials()) {
                $response = $this->makeRmmRequest('/account/sites', ['max' => 1]);
                
                if (empty($response)) {
                    throw new AuthenticationException(
                        'Datto RMM authentication failed - empty response',
                        401
                    );
                }
                
                return true;
            }
            
            if ($this->hasSaasCredentials()) {
                $response = $this->makeSaasRequest('/saas/domains');
                
                if (empty($response)) {
                    throw new AuthenticationException(
                        'Datto SaaS authentication failed - empty response',
                        401
                    );
                }
                
                return true;
            }
            
            throw new AuthenticationException(
                'Datto authentication failed - no valid credentials provided',
                400
            );
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Datto: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'Datto authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        if ($this->useMockData) {
            return $this->getMockCustomers();
        }
        
        $customers = [];
        
        if ($this->hasRmmCredentials()) {
            $sites = $this->getRmmSites();
            $customers = array_merge($customers, $sites);
        }
        
        if ($this->hasSaasCredentials()) {
            $domains = $this->getSaasDomains();
            $customers = array_merge($customers, $domains);
        }
        
        return $customers;
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRmmSites(): array
    {
        if ($this->useMockData) {
            return $this->getMockCustomers();
        }
        
        $allSites = [];
        $page = 0;
        $hasMore = true;
        
        while ($hasMore) {
            $response = $this->makeRmmRequest('/account/sites', [
                'max' => 250,
                'page' => $page
            ]);
            
            $sites = $response['sites'] ?? [];
            $allSites = array_merge($allSites, $sites);
            
            $pageDetails = $response['pageDetails'] ?? [];
            $hasMore = !empty($pageDetails['nextPageUrl']);
            $page++;
            
            if ($page > 100) {
                Log::warning('Datto RMM: Reached page limit while fetching sites', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                break;
            }
        }
        
        Log::info('Datto RMM: Fetched sites', [
            'integration_id' => $this->integration->integration_id,
            'count' => count($allSites),
        ]);
        
        return $allSites;
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSaasDomains(): array
    {
        $response = $this->makeSaasRequest('/saas/domains');
        // Response could be array of domains or an object with 'items' key
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }
        /** @var array<int, array<string, mixed>> */
        return array_values($response);
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRmmDevices(string $siteUid): array
    {
        $allDevices = [];
        $page = 0;
        $hasMore = true;
        
        while ($hasMore) {
            $response = $this->makeRmmRequest("/site/{$siteUid}/devices", [
                'max' => 250,
                'page' => $page
            ]);
            
            $devices = $response['devices'] ?? [];
            $allDevices = array_merge($allDevices, $devices);
            
            $pageDetails = $response['pageDetails'] ?? [];
            $hasMore = !empty($pageDetails['nextPageUrl']);
            $page++;
            
            if ($page > 100) {
                Log::warning('Datto RMM: Reached page limit while fetching devices', [
                    'integration_id' => $this->integration->integration_id,
                    'site_uid' => $siteUid,
                ]);
                break;
            }
        }
        
        return $allDevices;
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRmmAlerts(string $siteUid, bool $openOnly = true, ?Carbon $since = null): array
    {
        $endpoint = $openOnly 
            ? "/site/{$siteUid}/alerts/open"
            : "/site/{$siteUid}/alerts/resolved";
            
        $allAlerts = [];
        $page = 0;
        $hasMore = true;

        while ($hasMore) {
            $params = [
                'max' => 250,
                'page' => $page
            ];
            
            if (!$openOnly) {
                $sinceDate = $since ?? Carbon::now()->subHour();
                $params['resolvedSince'] = $sinceDate->format('Y-m-d\TH:i:s\Z');
            }
            
            $response = $this->makeRmmRequest($endpoint, $params);
            $alerts = $response['alerts'] ?? [];
            
            if (empty($alerts)) {
                break;
            }
            
            $allAlerts = array_merge($allAlerts, $alerts);
            
            $pageDetails = $response['pageDetails'] ?? [];
            $hasMore = !empty($pageDetails['nextPageUrl']);
            $page++;
            
            if ($page > 100) {
                Log::warning('Datto RMM: Reached page limit while fetching alerts', [
                    'site_uid' => $siteUid,
                    'open_only' => $openOnly
                ]);
                break;
            }
        }
        
        return $allAlerts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackupHistory(Carbon $startDate, Carbon $endDate): array
    {
        if ($this->hasSaasCredentials()) {
            return $this->getSaasDomains();
        }
        
        return [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function makeRmmRequest(string $endpoint, array $params = []): ?array
    {
        if (empty($this->accessToken)) {
            $this->accessToken = $this->getAccessToken();
        }

        $url = $this->baseUrl . '/api/v2' . $endpoint;
        
        $maxRetries = 2;
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                Log::debug('Datto RMM API request', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint'       => $endpoint,
                    'params'         => $params,
                ]);

                $response = Http::timeout(120)
                    ->connectTimeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Accept'        => 'application/json',
                    ])->get($url, $params);

                // Handle response status codes
                if ($response->status() === 429) {
                    // If this is our first attempt, wait and retry once
                    if ($attempt === 0) { // Changed from $attempt === 1 to $attempt === 0 for first attempt
                        Log::warning('Datto RMM: Rate limit hit, waiting 60s before retry', [
                            'integration_id' => $this->integration->integration_id,
                        ]);
                        sleep(60);
                        $attempt++;
                        continue; // Retry the loop
                    }
                    
                    // If we've already retried, throw exception to let caller handle it
                    Log::error('Datto RMM: Rate limit exceeded after retry', [
                        'integration_id' => $this->integration->integration_id,
                    ]);
                    throw new RateLimitExceededException(
                        'Datto RMM API rate limit exceeded'
                    );
                }

                if ($response->status() === 401) {
                    Log::info('Datto RMM: Token expired, refreshing', [
                        'integration_id' => $this->integration->integration_id,
                    ]);
                    Cache::forget(self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id);
                    $this->accessToken = $this->getAccessToken();
                    $attempt++; // Increment attempt counter
                    continue; // Retry the loop
                }

                if (!$response->successful()) {
                    Log::error('Datto RMM: API request failed', [
                        'integration_id' => $this->integration->integration_id,
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    throw new \RuntimeException("API request failed: " . $response->body());
                }

                $this->rateLimiter->recordRequest($this->integration);
                
                Log::debug('Datto RMM API response', [
                    'integration_id' => $this->integration->integration_id,
                    'status'         => $response->status(),
                    'body_preview'   => substr($response->body(), 0, 500),
                ]);
                
                return $response->json();
                
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $attempt++;
                
                if ($attempt > $maxRetries) {
                    Log::error('Datto RMM: Connection timeout after retries', [
                        'integration_id' => $this->integration->integration_id,
                        'endpoint' => $endpoint,
                        'attempts' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    throw new \Exception("Connection timeout after {$attempt} attempts: " . $e->getMessage());
                }
                
                Log::warning('Datto RMM: Connection timeout, retrying', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries
                ]);
                
                sleep(2);
                continue;
            }
        }
        
        throw new \Exception('Unexpected error in makeRmmRequest');
    }
    
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function makeSaasRequest(string $endpoint, array $params = []): array
    {
        if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
            throw new \Exception("Rate limit exceeded for Datto SaaS API");
        }

        $url = 'https://api.datto.com/v1' . $endpoint;
        
        $credentials = base64_encode(
            $this->credentials['saas_public_key'] . ':' . $this->credentials['saas_private_key']
        );
        
        try {
            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->withHeaders([
                    'Authorization' => 'Basic ' . $credentials,
                    'Accept' => 'application/json',
                ])->get($url, $params);

            if (!$response->successful()) {
                Log::error('Datto SaaS: API request failed', [
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
            Log::error('Datto SaaS: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMockCustomers(): array
    {
        \Log::info('Datto Client: Using mock data mode');
        
        return [
            [
                'id' => 'DATTO-001',
                'name' => 'ACME Corp',
                'status' => 'active',
            ],
            [
                'id' => 'DATTO-002',
                'name' => 'Beta Solutions Ltd',
                'status' => 'active',
            ],
            [
                'id' => 'DATTO-003',
                'name' => 'TechStart Inc',
                'status' => 'active',
            ],
            [
                'id' => 'DATTO-004',
                'name' => 'Zeta Systems',
                'status' => 'active',
            ],
            [
                'id' => 'DATTO-005',
                'name' => 'Omega Consulting Group',
                'status' => 'active',
            ],
            [
                'id' => 'DATTO-006',
                'name' => 'Phoenix Digital Services',
                'status' => 'active',
            ],
            [
                'id' => 'DATTO-007',
                'name' => 'Old Legacy Corp (INACTIVE)',
                'status' => 'inactive',
            ],
        ];
    }
}
