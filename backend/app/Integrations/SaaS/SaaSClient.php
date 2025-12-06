<?php

namespace App\Integrations\SaaS;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;

class SaaSClient
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

        $this->baseUrl = rtrim($credentials['base_url'] ?? 'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1', '/');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/reports/partners/profile');
            
            if (!$response->successful()) {
                $statusCode = $response->status();
                $body = $response->body();
                
                Log::error('SaaS Alerts connection test failed', [
                    'integration_id' => $this->integration->integration_id,
                    'status' => $statusCode,
                    'body' => $body
                ]);
                
                throw new AuthenticationException(
                    'SaaS Alerts authentication failed',
                    $statusCode,
                    $body
                );
            }
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('SaaS Alerts connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage()
            ]);
            
            throw new AuthenticationException(
                'SaaS Alerts authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all customers for the authenticated partner.
     */
    public function getCustomers(): array
    {
        try {
            $response = $this->makeRequest('GET', '/customers');

            if (!$response->successful()) {
                Log::warning('SaaS Alerts API getCustomers failed', [
                    'integration_id' => $this->integration->integration_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $customers = $response->json();

            Log::info('SaaS Alerts API customers retrieval complete', [
                'integration_id' => $this->integration->integration_id,
                'total_count' => count($customers)
            ]);

            return $customers;

        } catch (\Exception $e) {
            Log::error('SaaS Alerts API getCustomers exception', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to fetch customers: {$e->getMessage()}");
        }
    }

    /**
     * Get events for a specific customer.
     */
    public function getEvents(array $params = []): array
    {
        try {
            $partnerId = $this->credentials['partner_id'] ?? null;
            
            if (!$partnerId) {
                throw new \Exception('Partner ID is required to fetch events');
            }

            // Build query parameters
            $queryParams = [
                'partnerId' => $partnerId,
            ];

            if (isset($params['customerId'])) {
                $queryParams['customerId'] = $params['customerId'];
            }
            if (isset($params['start'])) {
                $queryParams['start'] = $params['start'];
            }
            if (isset($params['end'])) {
                $queryParams['end'] = $params['end'];
            }
            if (isset($params['size'])) {
                $queryParams['size'] = $params['size'];
            }

            $response = $this->makeRequest('GET', '/reports/events', $queryParams);

            if (!$response->successful()) {
                Log::warning('SaaS Alerts API getEvents failed', [
                    'integration_id' => $this->integration->integration_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'params' => $queryParams,
                ]);
                return [];
            }

            $events = $response->json();

            Log::info('SaaS Alerts API events retrieval complete', [
                'integration_id' => $this->integration->integration_id,
                'total_count' => count($events),
                'params' => $queryParams,
            ]);

            return $events;

        } catch (\Exception $e) {
            Log::error('SaaS Alerts API getEvents exception', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            throw new \Exception("Failed to fetch events: {$e->getMessage()}");
        }
    }

    /**
     * Make an authenticated request to the SaaS Alerts API.
     */
    protected function makeRequest(string $method, string $endpoint, array $params = [])
    {
        // Wait for rate limiter slot
        if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
            throw new \Exception("Rate limit exceeded for SaaS Alerts API, could not acquire slot within timeout.");
        }

        $headers = [
            'api_key' => $this->credentials['api_key'] ?? '',
            'Content-Type' => 'application/json',
        ];

        $url = $this->baseUrl . $endpoint;

        Log::info('SaaS Alerts API Request', [
            'integration_id' => $this->integration->integration_id,
            'method' => $method,
            'url' => $url,
            'headers' => array_merge($headers, ['api_key' => '***REDACTED***']),
            'params' => $params
        ]);

        if ($method === 'GET') {
            $response = Http::withHeaders($headers)->timeout(120)->get($url, $params);
        } else {
            $response = Http::withHeaders($headers)->timeout(120)->post($url, $params);
        }

        Log::info('SaaS Alerts API Response', [
            'integration_id' => $this->integration->integration_id,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => substr($response->body(), 0, 500)
        ]);

        // Record request for rate limiting
        $this->rateLimiter->recordRequest($this->integration);

        return $response;
    }
}
