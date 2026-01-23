<?php

namespace App\Integrations\Vonahi;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VonahiClient
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

        // Vonahi vPenTest API base URL
        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.vpentest.io', '/');
    }

    /**
     * Test connection to Vonahi API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/v1/organizations');
            
            if ($response === null) {
                throw new AuthenticationException('Vonahi authentication failed - empty response', 401);
            }
            
            Log::info('Vonahi: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Vonahi: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'Vonahi authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all organizations/tenants
     */
    public function getOrganizations(): array
    {
        $response = $this->makeRequest('/v1/organizations');
        return $response['data'] ?? $response['organizations'] ?? $response ?? [];
    }

    /**
     * Get penetration tests
     */
    public function getPenTests(?string $organizationId = null): array
    {
        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/pentests"
            : '/v1/pentests';

        $response = $this->makeRequest($endpoint);
        return $response['data'] ?? $response['pentests'] ?? $response ?? [];
    }

    /**
     * Get security findings/vulnerabilities
     */
    public function getSecurityFindings(?string $pentestId = null): array
    {
        $endpoint = $pentestId 
            ? "/v1/pentests/{$pentestId}/findings"
            : '/v1/findings';

        $response = $this->makeRequest($endpoint);
        return $response['data'] ?? $response['findings'] ?? $response ?? [];
    }

    /**
     * Get vulnerability scans
     */
    public function getScans(?string $organizationId = null): array
    {
        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/scans"
            : '/v1/scans';

        $response = $this->makeRequest($endpoint);
        return $response['data'] ?? $response['scans'] ?? $response ?? [];
    }

    /**
     * Get assets/targets
     */
    public function getAssets(?string $organizationId = null): array
    {
        $endpoint = $organizationId 
            ? "/v1/organizations/{$organizationId}/assets"
            : '/v1/assets';

        $response = $this->makeRequest($endpoint);
        return $response['data'] ?? $response['assets'] ?? $response ?? [];
    }

    /**
     * Make authenticated request to Vonahi API
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $apiKey = $this->credentials['api_key'] ?? null;
        
        if (empty($apiKey)) {
            throw new AuthenticationException('Vonahi API key is required', 401);
        }

        $url = $this->baseUrl . $endpoint;

        try {
            Log::debug('Vonahi API request', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
            ]);

            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get($url, $params);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new AuthenticationException(
                    'Vonahi authentication failed - invalid API key',
                    $response->status()
                );
            }

            if ($response->status() === 429) {
                Log::warning('Vonahi: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new \Exception('Vonahi API rate limit exceeded');
            }

            if (!$response->successful()) {
                Log::error('Vonahi: API request failed', [
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
            Log::error('Vonahi: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
