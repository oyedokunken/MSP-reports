<?php

namespace App\Integrations\Autotask;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\RateLimiter;
use App\Models\Integration;

class AutotaskClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected string $apiIntegrationCode;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    protected bool $useMockData = false;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        
        // Enable mock mode if credentials contain 'test_' prefix
        $this->useMockData = str_starts_with($credentials['username'] ?? '', 'test_');
        
        // Autotask API endpoint (use provided endpoint or default)
        $this->baseUrl = rtrim($credentials['endpoint'] ?? 'https://webservices26.autotask.net/atservicesrest/v1.0', '/');
        
        // API Integration Code (required for Autotask API)
        $this->apiIntegrationCode = $credentials['api_integration_code'] ?? '';
    }

    public function testConnection(): bool
    {
        try {
            // Test connection by querying companies with minimal filter
            $response = $this->makeRequest('POST', '/Companies/query', [
                'filter' => [
                    ['field' => 'id', 'op' => 'gt', 'value' => 0]
                ],
                'MaxRecords' => 1
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Autotask connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getCompanies(): array
    {
        // Return mock data for testing
        if ($this->useMockData) {
            return $this->getMockCompanies();
        }

        $allCompanies = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 100;

        try {
            while ($hasMore) {
                $response = $this->makeRequest('POST', '/Companies/query', [
                    'filter' => [
                        ['field' => 'id', 'op' => 'gt', 'value' => $lastId]
                    ],
                    'MaxRecords' => $pageSize
                ]);

                if (!$response->successful()) {
                    \Log::warning('Autotask API getCompanies failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allCompanies = array_merge($allCompanies, $items);
                $lastId = end($items)['id'];

                \Log::info('Autotask API companies page retrieved', [
                    'page_count' => count($items),
                    'total_so_far' => count($allCompanies),
                    'last_id' => $lastId
                ]);
            }

            \Log::info('Autotask API companies retrieval complete', ['total_count' => count($allCompanies)]);
            return $allCompanies;

        } catch (\Exception $e) {
            \Log::error('Autotask API getCompanies exception', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch companies: {$e->getMessage()}");
        }
    }

    public function getTickets(Carbon $startDate, Carbon $endDate): array
    {
        $allTickets = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 100;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                    ['field' => 'createDate', 'op' => 'gte', 'value' => $startDate->format('Y-m-d\TH:i:s\Z')],
                    ['field' => 'createDate', 'op' => 'lt', 'value' => $endDate->format('Y-m-d\TH:i:s\Z')]
                ];

                $response = $this->makeRequest('POST', '/Tickets/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize,
                    'SortBy' => 'createDate',
                    'SortOrder' => 'ASC'
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allTickets = array_merge($allTickets, $items);
                $lastId = end($items)['id'];
            }

            return $allTickets;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch tickets: {$e->getMessage()}");
        }
    }

    public function getTimeEntries(Carbon $startDate, Carbon $endDate): array
    {
        $allEntries = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 100;

        try {
            while ($hasMore) {
                $filters = [
                    ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                    ['field' => 'dateWorked', 'op' => 'gte', 'value' => $startDate->format('Y-m-d\TH:i:s\Z')],
                    ['field' => 'dateWorked', 'op' => 'lt', 'value' => $endDate->format('Y-m-d\TH:i:s\Z')]
                ];

                $response = $this->makeRequest('POST', '/TimeEntries/query', [
                    'filter' => $filters,
                    'MaxRecords' => $pageSize
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allEntries = array_merge($allEntries, $items);
                $lastId = end($items)['id'];
            }

            return $allEntries;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch time entries: {$e->getMessage()}");
        }
    }

    public function getContracts(): array
    {
        $allContracts = [];
        $lastId = 0;
        $hasMore = true;
        $pageSize = 100;

        try {
            while ($hasMore) {
                $response = $this->makeRequest('POST', '/Contracts/query', [
                    'filter' => [
                        ['field' => 'id', 'op' => 'gt', 'value' => $lastId],
                        ['field' => 'status', 'op' => 'eq', 'value' => 1] // Active contracts
                    ],
                    'MaxRecords' => $pageSize
                ]);

                if (!$response->successful()) {
                    break;
                }

                $items = $response->json('items') ?? [];
                
                if (empty($items)) {
                    $hasMore = false;
                    break;
                }

                $allContracts = array_merge($allContracts, $items);
                $lastId = end($items)['id'];
            }

            return $allContracts;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch contracts: {$e->getMessage()}");
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $params = [])
    {
        // Skip rate limiting for mock data mode
        if (!$this->useMockData) {
            // Check rate limit BEFORE each API call
            if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
                throw new \Exception("Rate limit exceeded for Autotask API, could not acquire slot within timeout.");
            }
        }

        $headers = [
            'Username' => $this->credentials['username'] ?? '',
            'Secret' => $this->credentials['secret'] ?? '',
            'APIIntegrationcode' => $this->apiIntegrationCode,
            'Content-Type' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

        \Log::info('Autotask API Request', [
            'method' => $method,
            'url' => $url,
            'headers' => array_merge($headers, ['Secret' => '***REDACTED***']),
            'body' => $params
        ]);

        // Make the actual API call
        if ($method === 'GET') {
            $response = Http::withHeaders($headers)->timeout(120)->get($url);
        } else {
            $response = Http::withHeaders($headers)->timeout(120)->post($url, $params);
        }

        \Log::info('Autotask API Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => substr($response->body(), 0, 500)
        ]);

        // Record this API call (skip for mock data)
        if (!$this->useMockData) {
            $this->rateLimiter->recordRequest($this->integration);
        }

        return $response;
    }

    /**
     * Return mock company data matching seeder (for testing)
     * This simulates what the real Autotask API would return
     */
    private function getMockCompanies(): array
    {
        \Log::info('Autotask Client: Using mock data mode');
        
        // Return the same companies that exist in the seeder
        // This simulates the Autotask API returning these customers
        return [
            [
                'id' => 'AT-1001',
                'companyName' => 'ACME Corporation',
                'isActive' => 1,
                'city' => 'Sydney',
                'state' => 'NSW',
                'country' => 'Australia',
                'phone' => '+61 2 9999 0001',
            ],
            [
                'id' => 'AT-1002',
                'companyName' => 'Beta Solutions Pty Ltd',
                'isActive' => 1,
                'city' => 'Melbourne',
                'state' => 'VIC',
                'country' => 'Australia',
                'phone' => '+61 3 9999 0002',
            ],
            [
                'id' => 'AT-1003',
                'companyName' => 'Gamma Tech Group',
                'isActive' => 1,
                'city' => 'Brisbane',
                'state' => 'QLD',
                'country' => 'Australia',
                'phone' => '+61 7 9999 0003',
            ],
            [
                'id' => 'AT-1004',
                'companyName' => 'TechStart Innovations Pty Ltd',
                'isActive' => 1,
                'city' => 'Brisbane',
                'state' => 'QLD',
                'country' => 'Australia',
                'phone' => '+61 7 9999 0004',
            ],
            [
                'id' => 'AT-1005',
                'companyName' => 'Sigma Networks Australia',
                'isActive' => 1,
                'city' => 'Perth',
                'state' => 'WA',
                'country' => 'Australia',
                'phone' => '+61 8 9999 0005',
            ],
            [
                'id' => 'AT-1006',
                'companyName' => 'Quantum IT Solutions',
                'isActive' => 1,
                'city' => 'Adelaide',
                'state' => 'SA',
                'country' => 'Australia',
                'phone' => '+61 8 9999 0006',
            ],
        ];
    }
}
