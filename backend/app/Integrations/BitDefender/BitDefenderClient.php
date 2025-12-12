<?php

namespace App\Integrations\BitDefender;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Exception;

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

        // BitDefender API base URL (without the category endpoint)
        // Category endpoints like /network, /packages, /policies are added per request
        $apiUrl = $credentials['api_url'] ?? 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc';
        $this->baseUrl = rtrim($apiUrl, '/');
    }

    public function testConnection(): bool
    {
        try {
            // Test connection by fetching companies list
            $response = $this->makeRequest('getCompaniesList', [], 'network');

            if (!isset($response['result'])) {
                throw new AuthenticationException(
                    'BitDefender authentication failed - invalid response',
                    401
                );
            }

            Log::info('BitDefenderClient::testConnection successful', [
                'integration_id' => $this->integration->integration_id,
            ]);

            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('BitDefenderClient::testConnection failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'BitDefender authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all companies from BitDefender GravityZone.
     * In BitDefender, companies represent the managed organizations/customers.
     */
    public function getEndpoints(): array
    {
        try {
            // Get companies list using the network endpoint
            $response = $this->makeRequest('getCompaniesList', [], 'network');

            if (!isset($response['result'])) {
                Log::warning('BitDefenderClient::getEndpoints no result in response', [
                    'integration_id' => $this->integration->integration_id,
                    'response' => $response,
                ]);
                return [];
            }

            $companies = $response['result'];

            Log::info('BitDefenderClient::getEndpoints retrieved companies', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($companies),
            ]);

            return $companies;
        } catch (Exception $e) {
            Log::error('BitDefenderClient::getEndpoints failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get endpoints (devices) for a specific company.
     * Filters out groups and only returns actual computer endpoints.
     */
    public function getEndpointsForCompany(string $companyId): array
    {
        try {
            $allEndpoints = [];
            $page = 1;
            $perPage = 100;
            
            do {
                $response = $this->makeRequest('getEndpointsList', [
                    'parentId' => $companyId,
                    'page' => $page,
                    'perPage' => $perPage,
                    'filters' => [
                        'type' => [
                            'computers' => true,
                            'virtualMachines' => true,
                        ],
                    ],
                ], 'network');

                $items = $response['result']['items'] ?? [];
                
                // Additional filtering: only include items that are actual endpoints (type 1 = computer, type 2 = VM)
                // Exclude groups (type 4) and other organizational items
                $endpoints = array_filter($items, function($item) {
                    $type = $item['type'] ?? null;
                    // Type 1 = Computer, Type 2 = Virtual Machine, Type 4 = Group
                    return in_array($type, [1, 2]);
                });
                
                $allEndpoints = array_merge($allEndpoints, $endpoints);
                
                $total = $response['result']['total'] ?? 0;
                $pagesCount = $response['result']['pagesCount'] ?? 1;
                
                Log::info('BitDefenderClient::getEndpointsForCompany page retrieved', [
                    'integration_id' => $this->integration->integration_id,
                    'company_id' => $companyId,
                    'page' => $page,
                    'items_this_page' => count($items),
                    'endpoints_this_page' => count($endpoints),
                    'total_endpoints_so_far' => count($allEndpoints),
                ]);
                
                $page++;
            } while ($page <= $pagesCount);

            Log::info('BitDefenderClient::getEndpointsForCompany completed', [
                'integration_id' => $this->integration->integration_id,
                'company_id' => $companyId,
                'total_endpoints' => count($allEndpoints),
            ]);

            return $allEndpoints;
        } catch (Exception $e) {
            Log::error('BitDefenderClient::getEndpointsForCompany failed', [
                'integration_id' => $this->integration->integration_id,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get threat detections/quarantine items.
     */
    public function getThreatDetections(): array
    {
        try {
            $response = $this->makeRequest('getQuarantineItemsList', [
                'perPage' => 100,
                'page' => 1,
            ]);

            $items = $response['result']['items'] ?? [];

            Log::info('BitDefenderClient::getThreatDetections retrieved threats', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($items),
            ]);

            return $items;
        } catch (Exception $e) {
            Log::error('BitDefenderClient::getThreatDetections failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Make a JSON-RPC request to BitDefender GravityZone API.
     * 
     * @param string $method The JSON-RPC method to call
     * @param array $params Parameters for the method
     * @param string $category API category (network, packages, policies, etc.)
     */
    protected function makeRequest(string $method, array $params = [], string $category = 'network'): array
    {
        // Check rate limit before making request
        if (!$this->rateLimiter->canMakeRequest($this->integration)) {
            // Wait for a slot to become available
            if (!$this->rateLimiter->waitForSlot($this->integration, 60)) {
                throw new Exception('Rate limit exceeded and timeout waiting for slot');
            }
        }
        
        // Record this request
        $this->rateLimiter->recordRequest($this->integration);

        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => uniqid(),
        ];

        // Build the full URL with category endpoint
        $url = $this->baseUrl . '/' . $category;

        Log::debug('BitDefenderClient::makeRequest', [
            'integration_id' => $this->integration->integration_id,
            'method' => $method,
            'category' => $category,
            'url' => $url,
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->credentials['api_key'] . ':'),
        ])
        ->timeout(30)
        ->post($url, $payload);

        if (!$response->successful()) {
            Log::error('BitDefender API request failed', [
                'integration_id' => $this->integration->integration_id,
                'url' => $this->baseUrl,
                'method' => $method,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            
            throw new Exception(
                "BitDefender API request failed: {$response->status()} - Please verify your API URL is correct. The URL should be specific to your BitDefender account (e.g., https://cloudgz.gravityzone.bitdefender.com/api/v1.0/jsonrpc/YOUR-API-KEY or check your GravityZone Control Center for the correct API endpoint)"
            );
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new Exception(
                "BitDefender API error: {$data['error']['message']} (Code: {$data['error']['code']})"
            );
        }

        return $data;
    }
}
