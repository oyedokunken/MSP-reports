<?php

declare(strict_types=1);

namespace App\Integrations\AdminDroid;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class AdminDroidClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected ?string $accessToken = null;

    protected const TOKEN_CACHE_KEY_PREFIX = 'admindroid_token_';

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        // AdminDroid uses Microsoft Graph API
        $this->baseUrl = rtrim($credentials['api_url'] ?? 'https://graph.microsoft.com/v1.0', '/');
    }

    /**
     * Test connection to Microsoft Graph API (used by AdminDroid)
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/organization');
            
            if ($response === null) {
                throw new AuthenticationException('AdminDroid authentication failed - empty response', 401);
            }
            
            Log::info('AdminDroid: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);
            
            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('AdminDroid: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthenticationException(
                'AdminDroid authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get access token using OAuth2 client credentials
     */
    protected function getAccessToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id;
        
        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $tenantId = $this->credentials['tenant_id'] ?? null;
        $clientId = $this->credentials['client_id'] ?? null;
        $clientSecret = $this->credentials['client_secret'] ?? null;

        if (empty($tenantId) || empty($clientId) || empty($clientSecret)) {
            throw new AuthenticationException('AdminDroid requires tenant_id, client_id, and client_secret', 401);
        }

        try {
            $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
            
            $response = Http::timeout(30)
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);

            if (!$response->successful()) {
                throw new AuthenticationException(
                    'AdminDroid OAuth failed: ' . $response->body(),
                    $response->status()
                );
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 3600;

            if (empty($token)) {
                throw new AuthenticationException('AdminDroid OAuth failed - no token returned', 401);
            }

            Cache::put($cacheKey, $token, $expiresIn - 60);
            
            return $token;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AuthenticationException(
                'AdminDroid OAuth failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get organization info
     * 
     * @return array<int|string, array<string, mixed>>
     */
    public function getOrganization(): array
    {
        $response = $this->makeRequest('/organization');
        return $response['value'] ?? $response ?? [];
    }

    /**
     * Get users
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getUsers(): array
    {
        $allUsers = [];
        $nextLink = '/users?$top=999&$select=id,displayName,mail,userPrincipalName,accountEnabled,createdDateTime,lastSignInDateTime';

        while ($nextLink) {
            if (!is_string($nextLink)) {
                break;
            }
            $response = $this->makeRequest($nextLink);
            $users = $response['value'] ?? [];
            $allUsers = array_merge($allUsers, $users);
            $nextLink = $response['@odata.nextLink'] ?? null;
            
            if ($nextLink) {
                $nextLink = str_replace($this->baseUrl, '', $nextLink);
            }
        }

        return $allUsers;
    }

    /**
     * Get M365 reports - user activity
     * 
     * @return array<string, mixed>
     */
    public function getM365Reports(): array
    {
        $response = $this->makeRequest('/reports/getOffice365ActiveUserDetail(period=\'D30\')');
        return $response ?? [];
    }

    /**
     * Get sign-in activity
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getSignInActivity(?Carbon $since = null): array
    {
        $filter = $since ? "&\$filter=createdDateTime ge {$since->toIso8601String()}" : '';
        $response = $this->makeRequest("/auditLogs/signIns?\$top=500{$filter}");
        return $response['value'] ?? [];
    }

    /**
     * Get user activity reports
     * 
     * @return array<string, mixed>
     */
    public function getUserActivity(): array
    {
        $response = $this->makeRequest('/reports/getOffice365ActiveUserCounts(period=\'D30\')');
        return $response ?? [];
    }

    /**
     * Get mailbox usage
     * 
     * @return array<string, mixed>
     */
    public function getMailboxUsage(): array
    {
        $response = $this->makeRequest('/reports/getMailboxUsageDetail(period=\'D30\')');
        return $response ?? [];
    }

    /**
     * Get OneDrive usage
     * 
     * @return array<string, mixed>
     */
    public function getOneDriveUsage(): array
    {
        $response = $this->makeRequest('/reports/getOneDriveUsageAccountDetail(period=\'D30\')');
        return $response ?? [];
    }

    /**
     * Get SharePoint usage
     * 
     * @return array<string, mixed>
     */
    public function getSharePointUsage(): array
    {
        $response = $this->makeRequest('/reports/getSharePointSiteUsageDetail(period=\'D30\')');
        return $response ?? [];
    }

    /**
     * Get Teams usage
     * 
     * @return array<string, mixed>
     */
    public function getTeamsUsage(): array
    {
        $response = $this->makeRequest('/reports/getTeamsUserActivityUserDetail(period=\'D30\')');
        return $response ?? [];
    }

    /**
     * Make authenticated request to Microsoft Graph API
     * 
     * @return array<string, mixed>|null
     */
    protected function makeRequest(string $endpoint): ?array
    {
        if (empty($this->accessToken)) {
            $this->accessToken = $this->getAccessToken();
        }

        $url = str_starts_with($endpoint, 'http') ? $endpoint : $this->baseUrl . $endpoint;

        try {
            Log::debug('AdminDroid API request', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
            ]);

            $response = Http::timeout(60)
                ->connectTimeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id);
                $this->accessToken = $this->getAccessToken();
                
                $response = Http::timeout(60)
                    ->connectTimeout(15)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Accept' => 'application/json',
                    ])
                    ->get($url);
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw new AuthenticationException(
                    'AdminDroid authentication failed - invalid credentials or insufficient permissions',
                    $response->status()
                );
            }

            if ($response->status() === 429) {
                Log::warning('AdminDroid: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new \Exception('Microsoft Graph API rate limit exceeded');
            }

            if (!$response->successful()) {
                Log::error('AdminDroid: API request failed', [
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
            Log::error('AdminDroid: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
