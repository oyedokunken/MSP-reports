<?php

namespace App\Integrations\LibreNMS;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\ApiResponseException;
use App\Integrations\Exceptions\RateLimitExceededException;

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
        try {
            $response = $this->makeRequest("/devices/{$deviceId}/availability");
            return $response['availability'] ?? [];
        } catch (\Exception $e) {
            // Handle 404 or missing availability data gracefully
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                Log::debug('LibreNMS: Device availability not found (expected)', [
                    'integration_id' => $this->integration->integration_id,
                    'device_id' => $deviceId,
                ]);
                return [];
            }
            // Re-throw other errors
            throw $e;
        }
    }

    public function getDeviceGroupsForDevice(int $deviceId): array
    {
        try {
            $response = $this->makeRequest("/devices/{$deviceId}/groups");
            return $response['groups'] ?? [];
        } catch (\Exception $e) {
            // 404 is expected when device has no groups - return empty array
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'No device groups found')) {
                Log::debug('LibreNMS: Device has no groups (expected)', [
                    'integration_id' => $this->integration->integration_id,
                    'device_id' => $deviceId,
                ]);
                return [];
            }
            // Re-throw other errors
            throw $e;
        }
    }

    protected function makeRequest(string $endpoint, array $params = []): array
    {
        // LibreNMS is internal server with high rate limit (20,000/hour)
        // Skip rate limiter wait to improve performance
        // Just record the request for tracking
        
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
                ->retry(2, 100) // Retry 2 times with 100ms delay (internal server responds fast)
                ->get($url, $params);

            if ($response->status() === 429) {
                Log::warning('LibreNMS: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new RateLimitExceededException('Rate limit exceeded. Please try again later.');
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
                
                // Don't log 404 as error - it's expected for devices without groups/availability
                if ($statusCode !== 404) {
                    Log::error('LibreNMS: API request failed', [
                        'integration_id' => $this->integration->integration_id,
                        'endpoint' => $endpoint,
                        'url' => $url,
                        'status' => $statusCode,
                        'body' => $errorMessage
                    ]);
                }
                
                // Provide helpful error messages
                if ($statusCode === 403) {
                    throw new ApiResponseException(
                        "Access forbidden. This API may be geolocation-restricted to Australia.",
                        403
                    );
                }
                
                throw new ApiResponseException(
                    "API request failed",
                    $statusCode,
                    $errorMessage
                );
            }

            $this->rateLimiter->recordRequest($this->integration);
            
            return $response->json();
        } catch (\Exception $e) {
            // Don't log 404 errors - they're expected and handled by calling methods
            if (!str_contains($e->getMessage(), '404') && !str_contains($e->getMessage(), 'No device groups found')) {
                Log::error('LibreNMS: API request error', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

}
