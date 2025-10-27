<?php

namespace App\Integrations\Autotask;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class AutotaskClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected string $apiIntegrationCode;

    protected bool $useMockData = false;

    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        
        // Enable mock mode if credentials contain 'test_' prefix
        $this->useMockData = str_starts_with($credentials['username'] ?? '', 'test_');
        
        // Autotask API endpoint (zone-specific)
        $zone = $credentials['zone'] ?? 'webservices2';
        $this->baseUrl = "https://{$zone}.autotask.net/ATServicesRest/v1.0";
        
        // API Integration Code (required for Autotask API)
        $this->apiIntegrationCode = $credentials['api_integration_code'] ?? '';
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/CompanyInfo');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getCompanies(): array
    {
        // Return mock data for testing
        if ($this->useMockData) {
            return $this->getMockCompanies();
        }

        try {
            $response = $this->makeRequest('GET', '/Companies/query', [
                'search' => json_encode([
                    'filter' => [
                        ['field' => 'isActive', 'op' => 'eq', 'value' => true]
                    ]
                ])
            ]);

            \Log::info('Autotask API getCompanies response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 500),
            ]);

            if ($response->successful()) {
                $items = $response->json('items') ?? [];
                \Log::info('Autotask API companies retrieved', ['count' => count($items)]);
                return $items;
            }

            \Log::warning('Autotask API getCompanies failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            \Log::error('Autotask API getCompanies exception', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch companies: {$e->getMessage()}");
        }
    }

    public function getTickets(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $response = $this->makeRequest('GET', '/Tickets/query', [
                'search' => json_encode([
                    'filter' => [
                        [
                            'field' => 'createDate',
                            'op' => 'gte',
                            'value' => $startDate->toIso8601String()
                        ],
                        [
                            'field' => 'createDate',
                            'op' => 'lte',
                            'value' => $endDate->toIso8601String()
                        ]
                    ]
                ])
            ]);

            if ($response->successful()) {
                return $response->json('items') ?? [];
            }

            return [];
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch tickets: {$e->getMessage()}");
        }
    }

    public function getTimeEntries(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $response = $this->makeRequest('GET', '/TimeEntries/query', [
                'search' => json_encode([
                    'filter' => [
                        [
                            'field' => 'dateWorked',
                            'op' => 'gte',
                            'value' => $startDate->toIso8601String()
                        ],
                        [
                            'field' => 'dateWorked',
                            'op' => 'lte',
                            'value' => $endDate->toIso8601String()
                        ]
                    ]
                ])
            ]);

            if ($response->successful()) {
                return $response->json('items') ?? [];
            }

            return [];
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch time entries: {$e->getMessage()}");
        }
    }

    public function getContracts(): array
    {
        try {
            $response = $this->makeRequest('GET', '/Contracts/query', [
                'search' => json_encode([
                    'filter' => [
                        ['field' => 'status', 'op' => 'eq', 'value' => 1] // Active contracts
                    ]
                ])
            ]);

            if ($response->successful()) {
                return $response->json('items') ?? [];
            }

            return [];
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch contracts: {$e->getMessage()}");
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $params = [])
    {
        $headers = [
            'ApiIntegrationCode' => $this->apiIntegrationCode,
            'UserName' => $this->credentials['username'] ?? '',
            'Secret' => $this->credentials['secret'] ?? '',
            'Content-Type' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
            return Http::withHeaders($headers)->get($url);
        }

        return Http::withHeaders($headers)->$method($url, $params);
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
