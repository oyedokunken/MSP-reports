<?php

namespace App\Integrations\Barracuda;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;

class BarracudaClient
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
        
        // Set base URL
        if (!empty($credentials['api_url'])) {
            $this->baseUrl = rtrim($credentials['api_url'], '/');
            // Enable mock mode if API key starts with 'test_'
            $this->useMockData = str_starts_with($credentials['api_key'] ?? '', 'test_');
        } else {
            $this->baseUrl = 'https://api.barracuda.com';
            $this->useMockData = true;
        }
    }

    public function testConnection(): bool
    {
        if ($this->useMockData) {
            return true;
        }
        
        try {
            $response = $this->makeRequest('/v1/accounts');
            return !empty($response);
        } catch (\Exception $e) {
            Log::error('Barracuda: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getCustomers(): array
    {
        // Return mock data for testing
        if ($this->useMockData) {
            return $this->getMockCustomers();
        }
        
        try {
            $response = $this->makeRequest('/v1/accounts');
            return $response['accounts'] ?? [];
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to fetch customers', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getSecurityData(Carbon $startDate, Carbon $endDate): array
    {
        if ($this->useMockData) {
            return $this->getMockSecurityData();
        }
        
        try {
            $response = $this->makeRequest('/v1/security/threats', [
                'start_date' => $startDate->toIso8601String(),
                'end_date' => $endDate->toIso8601String(),
            ]);
            return $response['threats'] ?? [];
        } catch (\Exception $e) {
            Log::error('Barracuda: Failed to fetch security data', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function makeRequest(string $endpoint, array $params = []): array
    {
        // Check rate limit BEFORE each API call
        if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
            throw new \Exception("Rate limit exceeded for Barracuda API");
        }

        $url = $this->baseUrl . $endpoint;
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ($this->credentials['api_key'] ?? ''),
                'Accept' => 'application/json',
            ])->get($url, $params);

            // Handle rate limiting
            if ($response->status() === 429) {
                Log::warning('Barracuda: Rate limit hit, waiting 60 seconds', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                sleep(60);
                return $this->makeRequest($endpoint, $params);
            }

            if (!$response->successful()) {
                Log::error('Barracuda: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \RuntimeException("API request failed: " . $response->body());
            }

            // Record this API call
            $this->rateLimiter->recordRequest($this->integration);
            
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Barracuda: API request error', [
                'integration_id' => $this->integration->integration_id,
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
     * Return mock security data for testing
     */
    private function getMockSecurityData(): array
    {
        return [
            [
                'account_id' => 'BARRACUDA-001',
                'threat_type' => 'phishing',
                'count' => 15,
                'blocked' => 15,
                'timestamp' => now()->subDays(1),
            ],
            [
                'account_id' => 'BARRACUDA-002',
                'threat_type' => 'malware',
                'count' => 8,
                'blocked' => 8,
                'timestamp' => now()->subDays(1),
            ],
        ];
    }
}
