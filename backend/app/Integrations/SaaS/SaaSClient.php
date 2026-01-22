<?php

namespace App\Integrations\SaaS;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\ApiResponseException;
use App\Integrations\Exceptions\RateLimitExceededException;

class SaaSClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    public function __construct(
        array $credentials,
        RateLimiter $rateLimiter,
        Integration $integration
    ) {
        $this->credentials  = $credentials;
        $this->rateLimiter  = $rateLimiter;
        $this->integration  = $integration;
        $this->baseUrl      = rtrim($credentials['base_url'] ?? 'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1', '/');
    }

    /**
     * Test if the API credentials are valid.
     *
     * @throws AuthenticationException
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/reports/partners/profile');

            if (!$response->successful()) {
                throw new ApiResponseException(
                    'SaaS Alerts authentication check failed',
                    $response->status(),
                    $response->body()
                );
            }

            return true;
        } catch (ApiResponseException $e) {
            Log::error('SaaS Alerts connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'status'         => $e->status,
                'body_preview'   => substr($e->body, 0, 500),
            ]);

            throw new AuthenticationException(
                'SaaS Alerts authentication failed: ' . $e->getMessage(),
                $e->status,
                $e->body
            );
        } catch (\Throwable $e) {
            Log::error('Unexpected error during SaaS Alerts connection test', [
                'integration_id' => $this->integration->integration_id,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            throw new AuthenticationException('SaaS Alerts connection test failed unexpectedly');
        }
    }

    /**
     * Get all customers (companies) associated with the partner.
     *
     * @return array<int, array<string, mixed>>
     * @throws \Exception
     */
    public function getCustomers(): array
    {
        $response = $this->makeRequest('GET', '/customers');

        if (!$response->successful()) {
            Log::warning('Failed to fetch SaaS Alerts customers', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            throw new ApiResponseException('Failed to retrieve customers', $response->status(), $response->body());
        }

        $customers = $response->json() ?? [];

        Log::info('SaaS Alerts customers retrieved', [
            'integration_id' => $this->integration->integration_id,
            'count'          => count($customers),
        ]);

        return $customers;
    }

    /**
     * Get details for a specific customer.
     */
    public function getCustomer(string $customerId): ?array
    {
        $response = $this->makeRequest('GET', "/customers/{$customerId}");

        if (!$response->successful()) {
            Log::warning('Failed to fetch SaaS Alerts customer', [
                'customer_id' => $customerId,
                'status'      => $response->status(),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Get users/accounts for a specific customer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUsersForCustomer(string $customerId): array
    {
        $response = $this->makeRequest('GET', '/reports/users', ['customerId' => $customerId]);

        if (!$response->successful()) {
            Log::warning('Failed to fetch users for customer', [
                'customer_id' => $customerId,
                'status'      => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get security events/alerts.
     * Supports all documented query parameters.
     *
     * @param array<string, mixed> $params Available: customerId, userEmail, alertStatus, eventType,
     *                                     start, end, from, size, timeSort, scroll
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(array $params = []): array
    {
        $allowedParams = ['customerId', 'userEmail', 'alertStatus', 'eventType', 'start', 'end', 'from', 'size', 'timeSort', 'scroll'];

        $query = array_filter($params, fn($key) => in_array($key, $allowedParams, true), ARRAY_FILTER_USE_KEY);

        // Sensible defaults
        $query['size'] = $query['size'] ?? 100;

        $response = $this->makeRequest('GET', '/reports/events', $query);

        if (!$response->successful()) {
            Log::warning('SaaS Alerts events fetch failed', [
                'status' => $response->status(),
                'params' => $query,
                'body'   => substr($response->body(), 0, 500),
            ]);
            return [];
        }

        $events = $response->json() ?? [];

        Log::info('SaaS Alerts events retrieved', [
            'integration_id' => $this->integration->integration_id,
            'count'          => count($events),
            'params'         => $query,
        ]);

        return $events;
    }

    /**
     * Get total count of events matching the criteria (without fetching full list).
     */
    public function getEventCount(array $params = []): int
    {
        $allowed = ['customerId', 'userEmail', 'alertStatus', 'eventType', 'start', 'end'];

        $query = array_intersect_key($params, array_flip($allowed));

        $response = $this->makeRequest('GET', '/reports/events/count', $query);

        if (!$response->successful()) {
            return 0;
        }

        return (int) ($response->json()['total'] ?? 0);
    }

    /**
     * Core method to make authenticated requests.
     */
    protected function makeRequest(string $method, string $endpoint, array $query = [], array $body = []): Response
    {
        if (!$this->rateLimiter->waitForSlot($this->integration, 30)) {
            throw new RateLimitExceededException('Rate limit exceeded for SaaS Alerts API');
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $headers = [
            'api_key'      => $this->credentials['api_key'] ?? '',
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $loggableHeaders = $headers;
        $loggableHeaders['api_key'] = '***';

        Log::debug('SaaS Alerts API request', [
            'integration_id' => $this->integration->integration_id,
            'method'         => $method,
            'url'            => $url,
            'query'          => $query,
            'headers'        => $loggableHeaders,
        ]);

        $request = Http::withHeaders($headers)
            ->timeout(120)
            ->retry(2, 800); // retry twice on 5xx / connection errors with 800ms delay

        $response = match (strtoupper($method)) {
            'GET'  => $request->get($url, $query),
            'POST' => $request->post($url, $body),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        Log::debug('SaaS Alerts API response', [
            'integration_id' => $this->integration->integration_id,
            'status'         => $response->status(),
            'body_preview'   => substr($response->body(), 0, 800),
        ]);

        $this->rateLimiter->recordRequest($this->integration);

        return $response;
    }

    // Optional: future complex event queries
    /*
    public function queryEvents(array $esQueryBody, string $scroll = '5s'): array
    {
        $payload = [
            'body'   => $esQueryBody,
            'scroll' => $scroll,
        ];

        $response = $this->makeRequest('POST', '/reports/events/query', [], $payload);

        if (!$response->successful()) {
            throw new ApiResponseException('Event query failed', $response->status(), $response->body());
        }

        return $response->json() ?? [];
    }
    */
}