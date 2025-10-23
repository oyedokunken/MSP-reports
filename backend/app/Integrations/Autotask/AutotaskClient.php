<?php

namespace App\Integrations\Autotask;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class AutotaskClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected string $apiIntegrationCode;

    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        
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
        try {
            $response = $this->makeRequest('GET', '/Companies/query', [
                'search' => json_encode([
                    'filter' => [
                        ['field' => 'isActive', 'op' => 'eq', 'value' => true]
                    ]
                ])
            ]);

            if ($response->successful()) {
                return $response->json('items') ?? [];
            }

            return [];
        } catch (\Exception $e) {
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
}
