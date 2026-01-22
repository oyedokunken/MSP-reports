<?php

namespace App\Integrations\Barracuda;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\ApiResponseException;
use App\Integrations\Exceptions\RateLimitExceededException;

class BarracudaClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected bool $useMockData = false;
    protected ?string $accessToken = null;
    protected ?int $tokenExpiresAt = null;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        
        // BarracudaONE API base URL
        $this->baseUrl = $credentials['api_url'] ?? 'https://data.barracudanetworks.com/b1';
        $this->baseUrl = rtrim($this->baseUrl, '/');
        
        // Enable mock mode if no credentials or test credentials
        $this->useMockData = empty($credentials['client_id']) || 
                            empty($credentials['client_secret']) ||
                            str_starts_with($credentials['client_id'] ?? '', 'test_');
    }

    /**
     * Get OAuth2 access token using client credentials
     */
    protected function getAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        // Check cache first
        $cacheKey = "barracuda_token_{$this->integration->integration_id}";
        $cachedToken = Cache::get($cacheKey);
        
        if ($cachedToken) {
            $this->accessToken = $cachedToken['token'];
            $this->tokenExpiresAt = $cachedToken['expires_at'];
            return $this->accessToken;
        }

        try {
            $response = Http::asJson()->post($this->baseUrl . '/v1/authorization/token', [
                'clientId' => $this->credentials['client_id'],
                'clientSecret' => $this->credentials['client_secret'],
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Failed to obtain access token: ' . $response->body());
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'];
            $this->tokenExpiresAt = time() + ($data['expires_in'] - 60); // Refresh 1 min before expiry

            // Cache the token
            Cache::put($cacheKey, [
                'token' => $this->accessToken,
                'expires_at' => $this->tokenExpiresAt,
            ], $data['expires_in'] - 60);

            Log::info('Barracuda: Access token obtained', [
                'integration_id' => $this->integration->integration_id,
                'expires_in' => $data['expires_in'],
            ]);

            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to get access token', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function testConnection(): bool
    {
        // Don't skip authentication check even in mock mode - always validate credentials
        try {
            // Test by getting access token
            $token = $this->getAccessToken();
            
            if (empty($token)) {
                throw new AuthenticationException(
                    'Barracuda authentication failed - unable to obtain access token',
                    401
                );
            }
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Barracuda: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'Barracuda authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get child accounts for a parent account
     * 
     * @param int $parentAccountId Parent/Partner Account ID
     * @return array List of child accounts
     */
    public function getChildAccounts(int $parentAccountId): array
    {
        if ($this->useMockData) {
            return $this->getMockCustomers();
        }
        
        try {
            $response = $this->makeRequest('GET', "/v1/accounts/{$parentAccountId}/child-accounts");
            return $response ?? [];
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to fetch child accounts', [
                'integration_id' => $this->integration->integration_id,
                'parent_account_id' => $parentAccountId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Alias for getChildAccounts - used by integration framework
     */
    public function getCustomers(): array
    {
        if ($this->useMockData) {
            return $this->getMockCustomers();
        }
        
        // Get parent account ID from credentials
        $parentAccountId = $this->credentials['parent_account_id'] ?? null;
        
        if (!$parentAccountId) {
            Log::warning('Barracuda: No parent_account_id configured', [
                'integration_id' => $this->integration->integration_id,
            ]);
            return [];
        }
        
        return $this->getChildAccounts($parentAccountId);
    }

    /**
     * Get product state for an account
     * 
     * @param int $accountId Barracuda Account ID
     * @return array Product status information
     */
    public function getProductState(int $accountId): array
    {
        if ($this->useMockData) {
            return $this->getMockProductState();
        }
        
        try {
            $response = $this->makeRequest('GET', "/v1/product-state/{$accountId}");
            return $response ?? [];
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to fetch product state', [
                'integration_id' => $this->integration->integration_id,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get open alerts for an account
     * 
     * @param string|null $accountId Account ID
     * @param string|null $relativeDateRange Relative date range (ALL_TIME, LAST_24_HOURS, etc.)
     * @param string|null $absoluteDateRangeStart Absolute start date (YYYY-MM-DD)
     * @param string|null $absoluteDateRangeEnd Absolute end date (YYYY-MM-DD)
     * @param int $skip Pagination offset
     * @return array Paginated alerts response
     */
    public function getAlerts(
        ?string $accountId = null,
        ?string $relativeDateRange = 'LAST_7_DAYS',
        ?string $absoluteDateRangeStart = null,
        ?string $absoluteDateRangeEnd = null,
        int $skip = 0
    ): array {
        if ($this->useMockData) {
            return $this->getMockAlerts();
        }
        
        try {
            $params = array_filter([
                'accountId' => $accountId,
                'relativeDateRange' => $relativeDateRange,
                'absoluteDateRangeStart' => $absoluteDateRangeStart,
                'absoluteDateRangeEnd' => $absoluteDateRangeEnd,
                'skip' => $skip,
            ]);
            
            $response = $this->makeRequest('GET', '/beta/alerts', $params);
            return $response ?? [];
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to fetch alerts', [
                'integration_id' => $this->integration->integration_id,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get report data for an account
     * 
     * @param string|null $accountId Account ID
     * @param string $report Report type (currently only 'ValueReport')
     * @param string|null $relativeDateRange Relative date range (LAST_7_DAYS, LAST_30_DAYS, etc.)
     * @param string|null $absoluteDateRangeStart Absolute start date (YYYY-MM-DD)
     * @param string|null $absoluteDateRangeEnd Absolute end date (YYYY-MM-DD)
     * @return array Report data with widgets
     */
    public function getReportData(
        ?string $accountId = null,
        string $report = 'ValueReport',
        ?string $relativeDateRange = 'LAST_30_DAYS',
        ?string $absoluteDateRangeStart = null,
        ?string $absoluteDateRangeEnd = null
    ): array {
        if ($this->useMockData) {
            return $this->getMockReportData();
        }
        
        try {
            $params = array_filter([
                'accountId' => $accountId,
                'report' => $report,
                'relativeDateRange' => $relativeDateRange,
                'absoluteDateRangeStart' => $absoluteDateRangeStart,
                'absoluteDateRangeEnd' => $absoluteDateRangeEnd,
            ]);
            
            $response = $this->makeRequest('GET', '/v1/reports/report-data', $params);
            return $response ?? [];
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to fetch report data', [
                'integration_id' => $this->integration->integration_id,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    public function getSecurityData(Carbon $startDate, Carbon $endDate): array
    {
        if ($this->useMockData) {
            return $this->getMockSecurityData();
        }
        
        // Use getAlerts with absolute date range
        $accountId = $this->credentials['parent_account_id'] ?? null;
        
        return $this->getAlerts(
            $accountId,
            null,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
    }

    /**
     * Make authenticated API request
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array $params Query parameters or request body
     * @return array Response data
     */
    protected function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        // Check rate limit BEFORE each API call
        if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
            throw new RateLimitExceededException('Rate limit exceeded for Barracuda API');
        }

        $url = $this->baseUrl . $endpoint;
        
        try {
            // Get access token
            $token = $this->getAccessToken();
            
            Log::debug('Barracuda API request', [
                'integration_id' => $this->integration->integration_id,
                'method'         => $method,
                'endpoint'       => $endpoint,
                'params'         => $params,
            ]);
            
            // Build request
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ]);

            // Make request based on method
            $response = match(strtoupper($method)) {
                'GET' => $request->get($url, $params),
                'POST' => $request->post($url, $params),
                'PUT' => $request->put($url, $params),
                'DELETE' => $request->delete($url, $params),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
            };

            // Handle rate limiting
            if ($response->status() === 429) {
                Log::warning('Barracuda: Rate limit hit, waiting 60 seconds', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                sleep(60);
                return $this->makeRequest($method, $endpoint, $params);
            }

            if (!$response->successful()) {
                Log::error('Barracuda: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'method'         => $method,
                    'endpoint'       => $endpoint,
                    'status'         => $response->status(),
                    'body'           => $response->body()
                ]);
                throw new ApiResponseException(
                    'Barracuda API request failed',
                    $response->status(),
                    $response->body()
                );
            }

            // Record this API call
            $this->rateLimiter->recordRequest($this->integration);
            
            Log::debug('Barracuda API response', [
                'integration_id' => $this->integration->integration_id,
                'status'         => $response->status(),
                'body_preview'   => substr($response->body(), 0, 500),
            ]);
            
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Barracuda: API request error', [
                'integration_id' => $this->integration->integration_id,
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Return mock customer data for testing
     */
    private function getMockCustomers(): array
    {
        Log::info('Barracuda Client: Using mock data mode');
        
        return [
            [
                'id' => 'BARRACUDA-001',
                'name' => 'ACME Corp',
                'status' => 'active',
                'email_domain' => 'acmecorp.com',
            ],
            [
                'id' => 'BARRACUDA-002',
                'name' => 'Beta Solutions Ltd',
                'status' => 'active',
                'email_domain' => 'betasolutions.com',
            ],
            [
                'id' => 'BARRACUDA-003',
                'name' => 'TechStart Inc',
                'status' => 'active',
                'email_domain' => 'techstart.com',
            ],
        ];
    }

    /**
     * Return mock product state for testing
     */
    private function getMockProductState(): array
    {
        return [
            [
                'bccAccountId' => '123456',
                'productName' => 'EGD',
                'productCategory' => 'EmailProtection',
                'productState' => 'Healthy',
                'isTrial' => false,
                'serial' => 'EGD-ABC123',
                'lastUpdated' => now()->toIso8601String(),
            ],
            [
                'bccAccountId' => '123456',
                'productName' => 'BCAS',
                'productCategory' => 'ApplicationProtection',
                'productState' => 'Healthy',
                'isTrial' => false,
                'serial' => 'BCAS-XYZ789',
                'lastUpdated' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Return mock alerts for testing
     */
    private function getMockAlerts(): array
    {
        return [
            'startedAt' => 0,
            'maxResult' => 50,
            'total' => 3,
            'isLast' => true,
            'alerts' => [
                [
                    'alertTime' => now()->subHours(2)->toIso8601String(),
                    'alertType' => 'Security',
                    'alertStatus' => 'Open',
                    'description' => 'Suspicious email detected and quarantined',
                    'deepLinkUrl' => 'https://app.barracudanetworks.com/alerts/1',
                ],
                [
                    'alertTime' => now()->subHours(5)->toIso8601String(),
                    'alertType' => 'Compliance',
                    'alertStatus' => 'Open',
                    'description' => 'Policy violation detected',
                    'deepLinkUrl' => 'https://app.barracudanetworks.com/alerts/2',
                ],
                [
                    'alertTime' => now()->subDays(1)->toIso8601String(),
                    'alertType' => 'System',
                    'alertStatus' => 'Resolved',
                    'description' => 'Service temporarily unavailable',
                    'deepLinkUrl' => 'https://app.barracudanetworks.com/alerts/3',
                ],
            ],
        ];
    }

    /**
     * Return mock report data for testing
     */
    private function getMockReportData(): array
    {
        return [
            'reportTitle' => 'Value Report - Last 30 Days',
            'widgetsData' => [
                [
                    'widgetName' => 'Email Threats Blocked',
                    'data' => [
                        ['date' => now()->subDays(7)->format('Y-m-d'), 'count' => 145],
                        ['date' => now()->subDays(6)->format('Y-m-d'), 'count' => 132],
                        ['date' => now()->subDays(5)->format('Y-m-d'), 'count' => 158],
                        ['date' => now()->subDays(4)->format('Y-m-d'), 'count' => 167],
                        ['date' => now()->subDays(3)->format('Y-m-d'), 'count' => 143],
                        ['date' => now()->subDays(2)->format('Y-m-d'), 'count' => 129],
                        ['date' => now()->subDays(1)->format('Y-m-d'), 'count' => 151],
                    ],
                ],
                [
                    'widgetName' => 'Spam Filtering Summary',
                    'data' => [
                        ['category' => 'Spam', 'count' => 1245],
                        ['category' => 'Phishing', 'count' => 87],
                        ['category' => 'Malware', 'count' => 34],
                        ['category' => 'Clean', 'count' => 8934],
                    ],
                ],
                [
                    'widgetName' => 'Data Protection Summary',
                    'data' => [
                        ['metric' => 'DLP Violations', 'count' => 12],
                        ['metric' => 'Encrypted Emails', 'count' => 456],
                        ['metric' => 'Quarantined Items', 'count' => 89],
                    ],
                ],
            ],
        ];
    }

    /**
     * Return mock security data for testing (legacy)
     */
    private function getMockSecurityData(): array
    {
        return [
            'startedAt' => 0,
            'maxResult' => 50,
            'total' => 2,
            'isLast' => true,
            'alerts' => [
                [
                    'alertTime' => now()->subDays(1)->toIso8601String(),
                    'alertType' => 'Phishing',
                    'alertStatus' => 'Blocked',
                    'description' => '15 phishing attempts blocked',
                    'deepLinkUrl' => null,
                ],
                [
                    'alertTime' => now()->subDays(1)->toIso8601String(),
                    'alertType' => 'Malware',
                    'alertStatus' => 'Blocked',
                    'description' => '8 malware threats blocked',
                    'deepLinkUrl' => null,
                ],
            ],
        ];
    }
}
