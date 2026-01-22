<?php

namespace App\Integrations\BitDefender;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\ApiResponseException;
use App\Integrations\Exceptions\RateLimitExceededException;
use Carbon\Carbon;

class BitDefenderClient
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

        $apiUrl = $credentials['api_url'] ?? 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc';
        $this->baseUrl = rtrim($apiUrl, '/');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('getCompaniesList', [], 'network');
            if (!isset($response['result'])) {
                throw new AuthenticationException('Invalid response from BitDefender', 401);
            }
            Log::info('BitDefender connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            return true;
        } catch (Exception $e) {
            Log::error('BitDefender connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            throw new AuthenticationException('Connection failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Fetch all managed companies (customers/organizations).
     * Renamed for clarity — previously getEndpoints().
     */
    public function getCompaniesList(): array
    {
        try {
            $response = $this->makeRequest('getCompaniesList', [], 'network');
            $companies = $response['result'] ?? [];
            Log::info('Retrieved companies', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($companies),
            ]);
            return $companies;
        } catch (Exception $e) {
            Log::error('Failed to fetch companies list', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // Alias for backward compatibility if needed
    public function getEndpoints(): array
    {
        return $this->getCompaniesList();
    }

    /**
     * Fetch ALL endpoints across ALL companies (paginated, no parentId).
     * This is much faster than per-company calls when you have many companies.
     *
     * @param int $perPage Max 100
     * @return array All endpoint items
     */
    public function fetchAllEndpointsPaginated(int $perPage = 100): array
    {
        $allEndpoints = [];
        $page = 1;

        do {
            $this->rateLimiter->waitForSlot($this->integration, 60); // ensure slot

            $response = $this->makeRequest('getEndpointsList', [
                'page'    => $page,
                'perPage' => $perPage,
                'filters' => [
                    'type' => [
                        'computers'       => true,
                        'virtualMachines' => true,
                    ],
                ],
            ], 'network');

            $result = $response['result'] ?? [];
            $items = $result['items'] ?? [];

            // Filter to actual machines (machineType 1=computer, 2=VM)
            $filtered = array_filter($items, fn($item) => in_array($item['machineType'] ?? 0, [1, 2]));

            $allEndpoints = array_merge($allEndpoints, array_values($filtered));

            $pagesCount = $result['pagesCount'] ?? 1;
            Log::info('Fetched endpoints page', [
                'integration_id' => $this->integration->integration_id,
                'page'           => $page,
                'items'          => count($items),
                'filtered'       => count($filtered),
                'total_so_far'   => count($allEndpoints),
            ]);

            $page++;
        } while ($page <= $pagesCount);

        return $allEndpoints;
    }

    /**
     * Fetch endpoints for ONE specific company (if you still need it for incremental sync).
     * Uses pagination + filtering.
     */
    public function getEndpointsForCompany(string $companyId, int $perPage = 100): array
    {
        $allEndpoints = [];
        $page = 1;

        do {
            $response = $this->makeRequest('getEndpointsList', [
                'parentId' => $companyId,
                'page'     => $page,
                'perPage'  => $perPage,
                'filters'  => [
                    'type' => [
                        'computers'       => true,
                        'virtualMachines' => true,
                    ],
                ],
            ], 'network');

            $result = $response['result'] ?? [];
            $items = $result['items'] ?? [];

            $filtered = array_filter($items, fn($item) => in_array($item['machineType'] ?? 0, [1, 2]));
            $allEndpoints = array_merge($allEndpoints, array_values($filtered));

            $pagesCount = $result['pagesCount'] ?? 1;
            $page++;
        } while ($page <= $pagesCount);

        Log::info('Fetched endpoints for company', [
            'company_id' => $companyId,
            'total'      => count($allEndpoints),
        ]);

        return $allEndpoints;
    }

    /**
     * Fetch ALL quarantine items (paginated) with optional date range.
     * Corrected: uses 'filters' for startDate/endDate (ISO 8601).
     *
     * @return array All quarantine/threat items
     */
    public function getThreatDetections(?Carbon $startDate = null, ?Carbon $endDate = null, int $perPage = 100): array
    {
        $allItems = [];
        $page = 1;

        $filters = [];
        if ($startDate) {
            $filters['startDate'] = $startDate->utc()->toIso8601String();
        }
        if ($endDate) {
            $filters['endDate'] = $endDate->utc()->toIso8601String();
        }

        do {
            $params = [
                'page'    => $page,
                'perPage' => $perPage,
            ];
            if (!empty($filters)) {
                $params['filters'] = $filters;
            }

            $response = $this->makeRequest('getQuarantineItemsList', $params, 'quarantine');

            $result = $response['result'] ?? [];
            $items = $result['items'] ?? [];
            $allItems = array_merge($allItems, $items);

            $pagesCount = $result['pagesCount'] ?? 1;

            Log::info('Fetched quarantine page', [
                'integration_id' => $this->integration->integration_id,
                'page'           => $page,
                'items'          => count($items),
                'total_so_far'   => count($allItems),
            ]);

            $page++;
        } while ($page <= $pagesCount);

        Log::info('All quarantine items fetched', [
            'integration_id' => $this->integration->integration_id,
            'total'          => count($allItems),
            'date_range'     => $startDate ? $startDate->toDateTimeString() . ' → ' . ($endDate ? $endDate->toDateTimeString() : 'now') : 'all',
        ]);

        return $allItems;
    }

    /**
     * Core JSON-RPC request method.
     * Added basic retry on rate limit / transient errors.
     */
    protected function makeRequest(string $method, array $params = [], string $category = 'network', int $retries = 2): array
    {
        $attempt = 0;

        while ($attempt <= $retries) {
            if (!$this->rateLimiter->canMakeRequest($this->integration)) {
                if (!$this->rateLimiter->waitForSlot($this->integration, 60)) {
                    throw new RateLimitExceededException('Rate limit timeout');
                }
            }

            $this->rateLimiter->recordRequest($this->integration);

            $payload = [
                'jsonrpc' => '2.0',
                'method'  => $method,
                'params'  => $params,
                'id'      => uniqid(),
            ];

            $url = $this->baseUrl . '/' . $category;

            Log::debug('BitDefender API request', [
                'integration_id' => $this->integration->integration_id,
                'method'         => $method,
                'category'       => $category,
                'params'         => $params,
            ]);

            try {
                $response = Http::withHeaders([
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->credentials['api_key'] . ':'),
                ])
                ->timeout(60) // increased for large responses
                ->post($url, $payload);

                if (!$response->successful()) {
                    throw new Exception("HTTP {$response->status()} - {$response->body()}");
                }

                $data = $response->json();

                if (isset($data['error'])) {
                    throw new ApiResponseException(
                        "JSON-RPC error: {$data['error']['message']}",
                        $data['error']['code'] ?? 500,
                        json_encode($data['error'])
                    );
                }

                Log::debug('BitDefender API response', [
                    'integration_id' => $this->integration->integration_id,
                    'method'         => $method,
                    'has_result'     => isset($data['result']),
                ]);

                return $data;
            } catch (ApiResponseException $e) {
                throw $e;
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt > $retries) {
                    Log::error('BitDefender request failed after retries', [
                        'integration_id' => $this->integration->integration_id,
                        'method'         => $method,
                        'category'       => $category,
                        'error'          => $e->getMessage(),
                    ]);
                    throw $e;
                }
                sleep(2 * $attempt); // exponential backoff
            }
        }

        throw new ApiResponseException('Max retries reached', 500);
    }
}