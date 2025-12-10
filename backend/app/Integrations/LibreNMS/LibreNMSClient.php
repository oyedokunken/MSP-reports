<?php

namespace App\Integrations\LibreNMS;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;

class LibreNMSClient
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
        
        $this->baseUrl = rtrim($credentials['base_url'] ?? 'http://192.168.228.7/api/v0', '/');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/devicegroups');
            return isset($response['status']) && $response['status'] === 'ok';
        } catch (\Exception $e) {
            Log::error('LibreNMS: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getDeviceGroups(): array
    {
        $response = $this->makeRequest('/devicegroups');
        return $response['groups'] ?? [];
    }

    public function getDevicesInGroup(int $groupId): array
    {
        $response = $this->makeRequest("/devicegroups/{$groupId}");
        return $response['devices'] ?? [];
    }

    public function getAllDevices(): array
    {
        $response = $this->makeRequest('/devices');
        return $response['devices'] ?? [];
    }

    public function getDevice(int $deviceId): ?array
    {
        try {
            $response = $this->makeRequest("/devices/{$deviceId}");
            $devices = $response['devices'] ?? [];
            return !empty($devices) ? $devices[0] : null;
        } catch (\Exception $e) {
            Log::error('LibreNMS: Failed to fetch device', [
                'integration_id' => $this->integration->integration_id,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getDeviceAvailability(int $deviceId): array
    {
        $response = $this->makeRequest("/devices/{$deviceId}/availability");
        return $response['availability'] ?? [];
    }

    public function getDeviceGroupsForDevice(int $deviceId): array
    {
        $response = $this->makeRequest("/devices/{$deviceId}/groups");
        return $response['groups'] ?? [];
    }

    protected function makeRequest(string $endpoint, array $params = []): array
    {
        if (!$this->rateLimiter->waitForSlot($this->integration, 60)) {
            throw new \Exception("Rate limit exceeded for LibreNMS API");
        }

        $url = $this->baseUrl . $endpoint;
        
        try {
            // Build HTTP client with headers
            $httpClient = Http::withHeaders([
                'X-Auth-Token' => $this->credentials['api_token'] ?? '',
                'Accept' => 'application/json',
            ]);
            
            // Base options
            $options = [
                'verify' => false, // Disable SSL verification
                'connect_timeout' => 10,
                'timeout' => 30,
            ];
            
            // Add proxy configuration if available (for geolocation bypass)
            if ($proxyHost = config('services.librenms.proxy_host')) {
                $proxyPort = config('services.librenms.proxy_port', 1080);
                $proxyType = config('services.librenms.proxy_type', 'socks5');
                
                $proxyUrl = "{$proxyType}://{$proxyHost}:{$proxyPort}";
                $options['proxy'] = [
                    'http' => $proxyUrl,
                    'https' => $proxyUrl,
                ];
                
                Log::info('LibreNMS: Using proxy for geolocation bypass', [
                    'integration_id' => $this->integration->integration_id,
                    'proxy' => $proxyUrl,
                ]);
            }
            
            // Apply options and make request with retry logic
            $response = $httpClient
                ->withOptions($options)
                ->retry(3, 1000) // Retry 3 times with 1 second delay
                ->get($url, $params);

            if ($response->status() === 429) {
                Log::warning('LibreNMS: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new \RuntimeException("Rate limit exceeded. Please try again later.");
            }

            if ($response->status() === 401) {
                Log::error('LibreNMS: Authentication failed - check API token', [
                    'integration_id' => $this->integration->integration_id,
                    'base_url' => $this->baseUrl,
                ]);
                throw new \RuntimeException("Authentication failed. Check API token.");
            }

            if (!$response->successful()) {
                $errorMessage = $response->body();
                $statusCode = $response->status();
                
                Log::error('LibreNMS: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'status' => $statusCode,
                    'body' => $errorMessage
                ]);
                
                // Provide helpful error messages
                if ($statusCode === 403) {
                    throw new \RuntimeException("Access forbidden. This API may be geolocation-restricted to Australia. Ensure you're connecting from an Australian IP address.");
                }
                
                throw new \RuntimeException("API request failed (HTTP {$statusCode}): " . $errorMessage);
            }

            $this->rateLimiter->recordRequest($this->integration);
            
            return $response->json();
        } catch (\Exception $e) {
            Log::error('LibreNMS: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

}
