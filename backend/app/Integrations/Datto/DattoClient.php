<?php

namespace App\Integrations\Datto;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\RateLimiter;
use App\Models\Integration;

class DattoClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected bool $useMockData = false;

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        $this->baseUrl = $credentials['base_url'] ?? 'https://api.datto.com';
        
        // Enable mock mode if credentials contain 'test_' prefix
        $this->useMockData = str_starts_with($credentials['api_key'] ?? '', 'test_');
    }

    public function testConnection(): bool
    {
        if ($this->useMockData) {
            return true;
        }
        
        try {
            $response = $this->makeRequest('GET', '/v1/account');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getCustomers(): array
    {
        // Return mock data for testing
        if ($this->useMockData) {
            return $this->getMockCustomers();
        }
        
        $response = $this->makeRequest('GET', '/v1/customers');

        if ($response->successful()) {
            return $response->json('data', []);
        }

        throw new \Exception("Failed to fetch customers: " . $response->body());
    }

    public function getBackupHistory(Carbon $startDate, Carbon $endDate): array
    {
        $response = $this->makeRequest('GET', '/v1/backups/history', [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
        ]);

        if ($response->successful()) {
            return $response->json('data', []);
        }

        throw new \Exception("Failed to fetch backup history: " . $response->body());
    }

    protected function makeRequest(string $method, string $endpoint, array $params = [])
    {
        // Skip rate limiting for mock data mode
        if (!$this->useMockData) {
            // Check rate limit BEFORE each API call
            if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
                throw new \Exception("Rate limit exceeded for Datto API, could not acquire slot within timeout.");
            }
        }

        $url = $this->baseUrl . $endpoint;

        // Make the actual API call
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->credentials['api_key'],
            'Accept' => 'application/json',
        ])->$method($url, $params);

        // Record this API call (skip for mock data)
        if (!$this->useMockData) {
            $this->rateLimiter->recordRequest($this->integration);
        }

        return $response;
    }

    /**
     * Return mock customer data matching seeder (for testing)
     */
    private function getMockCustomers(): array
    {
        \Log::info('Datto Client: Using mock data mode');
        
        // Return the same customers that exist in the seeder
        // This simulates the Datto API returning these customers
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
