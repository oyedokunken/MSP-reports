<?php

namespace App\Integrations\Kemp;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KempClient
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

        // Kemp LoadMaster API URL
        $this->baseUrl = rtrim($credentials['api_url'] ?? '', '/');
    }

    /**
     * Test connection to Kemp LoadMaster API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/accessv2', ['cmd' => 'stats']);
            
            if ($response === null) {
                throw new AuthenticationException('Kemp authentication failed - empty response', 401);
            }
            
            Log::info('Kemp: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Kemp: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'Kemp authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get LoadMaster statistics
     */
    public function getStats(): array
    {
        $response = $this->makeRequest('/accessv2', ['cmd' => 'stats']);
        return $response['Response']['Success']['Data'] ?? $response ?? [];
    }

    /**
     * Get all Virtual Services
     */
    public function getVirtualServices(): array
    {
        $response = $this->makeRequest('/accessv2', ['cmd' => 'listvs']);
        $services = $response['Response']['Success']['Data']['VS'] ?? [];
        
        // Ensure it's always an array
        if (!is_array($services)) {
            return [];
        }
        
        // Handle single VS returned as object instead of array
        if (isset($services['Index'])) {
            return [$services];
        }
        
        return $services;
    }

    /**
     * Get Virtual Service details
     */
    public function getVirtualService(string $vsIndex): array
    {
        $response = $this->makeRequest('/accessv2', [
            'cmd' => 'showvs',
            'vs' => $vsIndex,
        ]);
        return $response['Response']['Success']['Data']['VS'] ?? [];
    }

    /**
     * Get all Real Servers
     */
    public function getRealServers(): array
    {
        $response = $this->makeRequest('/accessv2', ['cmd' => 'listrs']);
        $servers = $response['Response']['Success']['Data']['RS'] ?? [];
        
        if (!is_array($servers)) {
            return [];
        }
        
        if (isset($servers['RSIndex'])) {
            return [$servers];
        }
        
        return $servers;
    }

    /**
     * Get system information
     */
    public function getSystemInfo(): array
    {
        $response = $this->makeRequest('/accessv2', ['cmd' => 'getall']);
        return $response['Response']['Success']['Data'] ?? [];
    }

    /**
     * Get license information
     */
    public function getLicenseInfo(): array
    {
        $response = $this->makeRequest('/accessv2', ['cmd' => 'licenseinfo']);
        return $response['Response']['Success']['Data'] ?? [];
    }

    /**
     * Make authenticated request to Kemp LoadMaster API
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        if (empty($this->baseUrl)) {
            throw new AuthenticationException('Kemp LoadMaster URL is required', 401);
        }

        $apiKey = $this->credentials['api_key'] ?? null;
        $username = $this->credentials['username'] ?? null;
        $password = $this->credentials['password'] ?? null;

        $url = $this->baseUrl . $endpoint;

        try {
            Log::debug('Kemp API request', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'params' => $params,
            ]);

            $request = Http::timeout(60)
                ->connectTimeout(15)
                ->withOptions(['verify' => false]) // LoadMasters often use self-signed certs
                ->withHeaders([
                    'Accept' => 'application/json',
                ]);

            // Use API key if available, otherwise basic auth
            if ($apiKey) {
                $params['apikey'] = $apiKey;
            } elseif ($username && $password) {
                $request = $request->withBasicAuth($username, $password);
            } else {
                throw new AuthenticationException('Kemp API key or username/password required', 401);
            }

            $response = $request->get($url, $params);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new AuthenticationException(
                    'Kemp authentication failed - invalid credentials',
                    $response->status()
                );
            }

            if (!$response->successful()) {
                Log::error('Kemp: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('API request failed: ' . $response->body());
            }

            $this->rateLimiter->recordRequest($this->integration);

            return $response->json();

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Kemp: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
