<?php

declare(strict_types=1);

namespace App\Integrations\WatchguardAuthPoint;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class WatchguardAuthPointClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected ?string $accessToken = null;

    protected const TOKEN_CACHE_KEY_PREFIX = 'watchguard_token_';

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        // WatchGuard Cloud API base URL varies by region
        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://api.usa.cloud.watchguard.com', '/');
    }

    /**
     * Test connection to WatchGuard AuthPoint API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/rest/authpoint/authentication/v1/resources');
            
            if ($response === null) {
                throw new AuthenticationException('WatchGuard authentication failed - empty response', 401);
            }
            
            Log::info('WatchGuard AuthPoint: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('WatchGuard AuthPoint: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'WatchGuard authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get access token using OAuth2
     */
    protected function getAccessToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id;
        
        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $apiKey = $this->credentials['api_key'] ?? null;
        $apiSecret = $this->credentials['api_secret'] ?? null;

        if (empty($apiKey) || empty($apiSecret)) {
            throw new AuthenticationException('WatchGuard API key and secret are required', 401);
        }

        try {
            $tokenUrl = $this->baseUrl . '/oauth/token';
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $apiKey,
                    'client_secret' => $apiSecret,
                    'scope' => 'api-access',
                ]);

            if (!$response->successful()) {
                throw new AuthenticationException(
                    'WatchGuard OAuth failed: ' . $response->body(),
                    $response->status()
                );
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 3600;

            if (empty($token)) {
                throw new AuthenticationException('WatchGuard OAuth failed - no token returned', 401);
            }

            // Cache token with some buffer before expiry
            Cache::put($cacheKey, $token, $expiresIn - 60);
            
            return $token;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AuthenticationException(
                'WatchGuard OAuth failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all users
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getUsers(): array
    {
        $accountId = $this->credentials['account_id'] ?? null;
        if (empty($accountId)) {
            return [];
        }

        $response = $this->makeRequest("/rest/authpoint/admin/v1/accounts/{$accountId}/users");
        return $response['data'] ?? $response['users'] ?? $response ?? [];
    }

    /**
     * Get authentication events/logs
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getAuthenticationEvents(?Carbon $since = null): array
    {
        $accountId = $this->credentials['account_id'] ?? null;
        if (empty($accountId)) {
            return [];
        }

        $params = ['pageSize' => 500];
        if ($since) {
            $params['startTime'] = $since->toIso8601String();
        }

        $response = $this->makeRequest("/rest/authpoint/admin/v1/accounts/{$accountId}/authentications", $params);
        return $response['data'] ?? $response['authentications'] ?? $response ?? [];
    }

    /**
     * Get user enrollments/tokens
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getUserEnrollments(): array
    {
        $accountId = $this->credentials['account_id'] ?? null;
        if (empty($accountId)) {
            return [];
        }

        $response = $this->makeRequest("/rest/authpoint/admin/v1/accounts/{$accountId}/tokens");
        return $response['data'] ?? $response['tokens'] ?? $response ?? [];
    }

    /**
     * Get resources (protected applications)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getResources(): array
    {
        $accountId = $this->credentials['account_id'] ?? null;
        if (empty($accountId)) {
            return [];
        }

        $response = $this->makeRequest("/rest/authpoint/admin/v1/accounts/{$accountId}/resources");
        return $response['data'] ?? $response['resources'] ?? $response ?? [];
    }

    /**
     * Make authenticated request to WatchGuard API
     * 
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        if (empty($this->accessToken)) {
            $this->accessToken = $this->getAccessToken();
        }

        $url = $this->baseUrl . $endpoint;
        $apiKey = $this->credentials['api_key'] ?? '';

        try {
            Log::debug('WatchGuard API request', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
            ]);

            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'WatchGuard-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get($url, $params);

            if ($response->status() === 401) {
                // Token expired, refresh and retry
                Cache::forget(self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id);
                $this->accessToken = $this->getAccessToken();
                
                $response = Http::timeout(60)
                    ->connectTimeout(15)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'WatchGuard-API-Key' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->get($url, $params);
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw new AuthenticationException(
                    'WatchGuard authentication failed - invalid credentials',
                    $response->status()
                );
            }

            if (!$response->successful()) {
                Log::error('WatchGuard: API request failed', [
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
            Log::error('WatchGuard: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
