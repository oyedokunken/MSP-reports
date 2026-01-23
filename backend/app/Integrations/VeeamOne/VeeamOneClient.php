<?php

namespace App\Integrations\VeeamOne;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;

/**
 * Veeam ONE REST API Client
 *
 * Implements OAuth2 password grant authentication for Veeam ONE Reporting Server.
 * Based on: https://helpcenter.veeam.com/references/one/13/rest/
 *
 * @see https://github.com/marktellier/veeam-one-restfulapi-powershell-tutorial
 */
class VeeamOneClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected ?string $accessToken = null;

    protected const TOKEN_CACHE_KEY_PREFIX = 'veeam_one_token_';
    protected const TOKEN_EXPIRY_SECONDS = 840; // 14 minutes (tokens valid for 15)

    public function __construct(
        array $credentials,
        RateLimiter $rateLimiter,
        Integration $integration
    ) {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        // Build base URL from server_url (includes protocol and port)
        // Expected format: https://192.168.10.62:1239
        $serverUrl = $credentials['server_url'] ?? '';
        $this->baseUrl = rtrim($serverUrl, '/');
    }

    /**
     * Test connection and authentication
     *
     * @throws AuthenticationException
     */
    public function testConnection(): bool
    {
        try {
            $this->getAccessToken();

            // Verify we can access the API
            $response = $this->makeRequest('GET', '/api/v1/about');

            if (!$response->successful()) {
                throw new AuthenticationException(
                    'Veeam ONE connection test failed: ' . $response->body(),
                    $response->status(),
                    $response->body()
                );
            }

            Log::info('VeeamOne: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
                'server_info' => $response->json('name'),
                'version' => $response->json('version'),
            ]);

            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('VeeamOne: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Veeam ONE connection failed: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get server information
     *
     * @return array{name: string, version: string, machine: string, logPath: string}
     */
    public function getAbout(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/about');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch about info', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get installation info
     */
    public function getInstallationInfo(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/about/installationInfo');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch installation info', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get license information
     */
    public function getLicense(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/license');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch license', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get current license usage
     */
    public function getCurrentUsage(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/license/currentUsage');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch current usage', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get all dashboards
     *
     * @return array<int, array>
     */
    public function getDashboards(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/dashboards');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch dashboards', [
                'status' => $response->status(),
            ]);
            return [];
        }

        $dashboards = $response->json() ?? [];

        Log::info('VeeamOne: Dashboards retrieved', [
            'integration_id' => $this->integration->integration_id,
            'count' => count($dashboards),
        ]);

        return $dashboards;
    }

    /**
     * Get dashboard by ID
     */
    public function getDashboard(int $dashboardId): ?array
    {
        $response = $this->makeRequest('GET', "/api/v1/dashboards/{$dashboardId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get widgets for a dashboard
     *
     * @return array<int, array>
     */
    public function getWidgets(int $dashboardId): array
    {
        $response = $this->makeRequest('GET', "/api/v1/dashboards/{$dashboardId}/widgets");

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch widgets', [
                'dashboard_id' => $dashboardId,
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get widget data sources
     *
     * @return array<int, array>
     */
    public function getWidgetDatasources(int $dashboardId, int $widgetId): array
    {
        $response = $this->makeRequest(
            'GET',
            "/api/v1/dashboards/{$dashboardId}/widgets/{$widgetId}/datasources"
        );

        if (!$response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get widget data from a specific datasource
     */
    public function getWidgetData(
        int $dashboardId,
        int $widgetId,
        int $datasourceId,
        bool $forceRefresh = false
    ): array {
        $response = $this->makeRequest(
            'GET',
            "/api/v1/dashboards/{$dashboardId}/widgets/{$widgetId}/datasources/{$datasourceId}/data",
            ['forceRefresh' => $forceRefresh ? 'true' : 'false']
        );

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch widget data', [
                'dashboard_id' => $dashboardId,
                'widget_id' => $widgetId,
                'datasource_id' => $datasourceId,
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get alarms with optional filtering
     *
     * @param array $params Optional filter params (status, objectType, etc.)
     * @return array<int, array>
     */
    public function getAlarms(array $params = []): array
    {
        $response = $this->makeRequest('GET', '/api/v1/alarms', $params);

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch alarms', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get alarm by ID
     */
    public function getAlarm(int $alarmId): ?array
    {
        $response = $this->makeRequest('GET', "/api/v1/alarms/{$alarmId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get protected workloads/VMs
     *
     * @return array<int, array>
     */
    public function getProtectedWorkloads(array $params = []): array
    {
        $response = $this->makeRequest('GET', '/api/v1/protectedWorkloads', $params);

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch protected workloads', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get backup infrastructure objects
     *
     * @return array<int, array>
     */
    public function getBackupInfrastructure(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/backupInfrastructure');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch backup infrastructure', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get backup servers
     *
     * @return array<int, array>
     */
    public function getBackupServers(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/backupServers');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch backup servers', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get backup jobs
     *
     * @return array<int, array>
     */
    public function getBackupJobs(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/backupJobs');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch backup jobs', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get backup job sessions
     *
     * @param array $params Optional filter params (jobId, startTime, endTime)
     * @return array<int, array>
     */
    public function getBackupSessions(array $params = []): array
    {
        $response = $this->makeRequest('GET', '/api/v1/backupSessions', $params);

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch backup sessions', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get repositories
     *
     * @return array<int, array>
     */
    public function getRepositories(): array
    {
        $response = $this->makeRequest('GET', '/api/v1/repositories');

        if (!$response->successful()) {
            Log::warning('VeeamOne: Failed to fetch repositories', [
                'status' => $response->status(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get OAuth2 access token using password grant
     *
     * @throws AuthenticationException
     */
    protected function getAccessToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id;

        if ($token = Cache::get($cacheKey)) {
            $this->accessToken = $token;
            return $token;
        }

        try {
            $username = $this->credentials['username'] ?? '';
            $password = $this->credentials['password'] ?? '';

            if (empty($username) || empty($password)) {
                throw new AuthenticationException(
                    'Veeam ONE credentials are incomplete',
                    400
                );
            }

            $tokenUrl = $this->baseUrl . '/api/token';

            Log::info('VeeamOne: Requesting access token', [
                'integration_id' => $this->integration->integration_id,
                'url' => $tokenUrl,
            ]);

            // Note: Veeam ONE uses self-signed certs, so we disable SSL verification
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->connectTimeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'password',
                    'username' => $username,
                    'password' => $password,
                    'refresh_token' => ' ',
                    'rememberMe' => ' ',
                ]);

            if (!$response->successful()) {
                Log::error('VeeamOne: Token request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                throw new AuthenticationException(
                    'Veeam ONE authentication failed',
                    $response->status(),
                    $response->body()
                );
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if (empty($token)) {
                throw new AuthenticationException(
                    'Veeam ONE returned invalid token response',
                    401
                );
            }

            // Cache token (14 minutes, token valid for 15)
            Cache::put($cacheKey, $token, self::TOKEN_EXPIRY_SECONDS);
            $this->accessToken = $token;

            Log::info('VeeamOne: Access token obtained', [
                'integration_id' => $this->integration->integration_id,
            ]);

            return $token;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('VeeamOne: Failed to obtain token', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Veeam ONE authentication error: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Refresh access token if expired
     */
    protected function refreshTokenIfNeeded(): void
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id;

        if (!Cache::has($cacheKey)) {
            Cache::forget($cacheKey);
            $this->accessToken = null;
            $this->getAccessToken();
        }
    }

    /**
     * Make authenticated API request
     */
    protected function makeRequest(
        string $method,
        string $endpoint,
        array $params = []
    ): Response {
        // Ensure we have a valid token
        if (empty($this->accessToken)) {
            $this->getAccessToken();
        }

        $url = $this->baseUrl . $endpoint;

        Log::debug('VeeamOne: API request', [
            'integration_id' => $this->integration->integration_id,
            'method' => $method,
            'url' => $url,
            'params' => $params,
        ]);

        // All Veeam ONE requests use self-signed certs
        $request = Http::withoutVerifying()
            ->timeout(120)
            ->connectTimeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ]);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            'PUT' => $request->put($url, $params),
            'DELETE' => $request->delete($url, $params),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        Log::debug('VeeamOne: API response', [
            'integration_id' => $this->integration->integration_id,
            'status' => $response->status(),
            'body_preview' => substr($response->body(), 0, 500),
        ]);

        // Handle token expiry (401) with retry
        if ($response->status() === 401 && empty($params['_retry'])) {
            Log::info('VeeamOne: Token expired, refreshing', [
                'integration_id' => $this->integration->integration_id,
            ]);

            Cache::forget(self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id);
            $this->accessToken = null;
            $this->getAccessToken();

            // Retry with new token
            $params['_retry'] = true;
            return $this->makeRequest($method, $endpoint, $params);
        }

        $this->rateLimiter->recordRequest($this->integration);

        return $response;
    }

    /**
     * Clear cached token (useful for testing or credential changes)
     */
    public function clearTokenCache(): void
    {
        $cacheKey = self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id;
        Cache::forget($cacheKey);
        $this->accessToken = null;
    }
}
