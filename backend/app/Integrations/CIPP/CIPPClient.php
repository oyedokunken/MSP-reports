<?php

declare(strict_types=1);

namespace App\Integrations\CIPP;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\RateLimitExceededException;

final class CIPPClient
{
    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected ?string $accessToken = null;
    
    protected const TOKEN_CACHE_KEY_PREFIX = 'cipp_token_';
    protected const TOKEN_EXPIRY = 3500; // Slightly less than 1 hour

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        $this->baseUrl = rtrim($credentials['base_url'] ?? '', '/');
    }

    /**
     * Get OAuth2 access token from Azure AD
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
            throw new AuthenticationException('Missing CIPP credentials (tenant_id, client_id, or client_secret)');
        }

        $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $scope = "api://{$clientId}/.default";

        Log::info('CIPP: Requesting access token', [
            'integration_id' => $this->integration->integration_id,
            'tenant_id' => $tenantId,
        ]);

        try {
            $response = Http::retry(3, 1000)
                ->timeout(30)
                ->connectTimeout(10)
                ->withOptions(['verify' => config('app.env') === 'production'])
                ->asForm()
                ->post($tokenUrl, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $scope,
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                $error = $response->json('error_description') ?? $response->json('error') ?? 'Unknown error';
                Log::error('CIPP: Token request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new AuthenticationException("Failed to obtain CIPP access token: {$error}");
            }

            $token = $response->json('access_token');
            $expiresIn = $response->json('expires_in', self::TOKEN_EXPIRY);
            
            // Cache token for slightly less than its expiry time
            Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 60));
            
            Log::info('CIPP: Access token obtained successfully', [
                'integration_id' => $this->integration->integration_id,
                'expires_in' => $expiresIn,
            ]);

            return $token;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CIPP: Token request exception', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            throw new AuthenticationException("CIPP authentication failed: {$e->getMessage()}");
        }
    }

    /**
     * Make an authenticated API request to CIPP
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint (e.g., '/api/ListTenants')
     * @param array<string, mixed> $params Query parameters or body data
     * @return array<string, mixed>
     */
    public function request(string $method, string $endpoint, array $params = []): array
    {
        $this->rateLimiter->waitForSlot($this->integration);
        
        $token = $this->getAccessToken();
        $url = $this->baseUrl . $endpoint;

        Log::debug('CIPP: Making API request', [
            'integration_id' => $this->integration->integration_id,
            'method' => $method,
            'endpoint' => $endpoint,
        ]);

        try {
            $request = Http::retry(2, 1000)
                ->timeout(120) // CIPP has 10 min timeout, we use 2 min
                ->connectTimeout(10)
                ->withOptions(['verify' => config('app.env') === 'production'])
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $params),
                'POST' => $request->post($url, $params),
                'PUT' => $request->put($url, $params),
                'PATCH' => $request->patch($url, $params),
                'DELETE' => $request->delete($url, $params),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            $this->rateLimiter->recordRequest($this->integration);

            if ($response->status() === 429) {
                $retryAfter = $response->header('Retry-After', '60');
                throw new RateLimitExceededException("CIPP rate limit exceeded. Retry after {$retryAfter} seconds.");
            }

            if ($response->status() === 401) {
                // Token might be expired, clear cache and retry once
                Cache::forget(self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id);
                throw new AuthenticationException('CIPP authentication failed - token may be expired');
            }

            if (!$response->successful()) {
                $error = $response->json('error') ?? $response->body();
                Log::error('CIPP: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new \RuntimeException("CIPP API error ({$response->status()}): {$error}");
            }

            return $response->json() ?? [];
        } catch (AuthenticationException|RateLimitExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CIPP: API request exception', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Test connection to CIPP API
     */
    public function testConnection(): bool
    {
        try {
            // Try to get token first
            $this->getAccessToken();
            
            // Then try a simple API call
            $this->request('GET', '/api/ListTenants');
            
            return true;
        } catch (\Exception $e) {
            Log::warning('CIPP: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ==================== Tenant Administration ====================

    /**
     * List all managed tenants
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listTenants(): array
    {
        $response = $this->request('GET', '/api/ListTenants');
        return is_array($response) ? $response : [];
    }

    /**
     * List licenses for a tenant
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listLicenses(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListLicenses', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List conditional access policies for a tenant
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listConditionalAccessPolicies(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListConditionalAccessPolicies', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List audit logs
     * 
     * @param string|null $tenantFilter Tenant domain or ID
     * @param string|null $startDate Start date (ISO format)
     * @param string|null $endDate End date (ISO format)
     * @return array<int, array<string, mixed>>
     */
    public function listAuditLogs(?string $tenantFilter = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['TenantFilter'] = $tenantFilter;
        }
        if ($startDate) {
            $params['StartDate'] = $startDate;
        }
        if ($endDate) {
            $params['EndDate'] = $endDate;
        }
        
        $response = $this->request('GET', '/api/ListAuditLogs', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List CIPP system logs
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listLogs(): array
    {
        $response = $this->request('GET', '/api/ListLogs');
        return is_array($response) ? $response : [];
    }

    // ==================== Identity Management ====================

    /**
     * List users with MFA information
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listMFAUsers(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListMFAUsers', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List sign-in events
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @param int $days Number of days to look back
     * @param bool $failedOnly Show only failed logons
     * @return array<int, array<string, mixed>>
     */
    public function listSignIns(string $tenantFilter, int $days = 7, bool $failedOnly = false): array
    {
        $params = [
            'TenantFilter' => $tenantFilter,
            'Days' => $days,
        ];
        if ($failedOnly) {
            $params['failedLogonOnly'] = 'true';
        }
        
        $response = $this->request('GET', '/api/ListSignIns', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List inactive accounts (6+ months)
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listInactiveAccounts(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListInactiveAccounts', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List groups in a tenant
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @param bool $includeMembers Include group members
     * @param bool $includeOwners Include group owners
     * @return array<int, array<string, mixed>>
     */
    public function listGroups(string $tenantFilter, bool $includeMembers = false, bool $includeOwners = false): array
    {
        $params = [
            'TenantFilter' => $tenantFilter,
        ];
        if ($includeMembers) {
            $params['members'] = 'true';
        }
        if ($includeOwners) {
            $params['owners'] = 'true';
        }
        
        $response = $this->request('GET', '/api/ListGroups', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List roles in a tenant
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listRoles(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListRoles', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List SharePoint/Teams sites
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @param string $type Type of sites to list (e.g., All, SPO, Group, Personal)
     * @return array<int, array<string, mixed>>
     */
    public function listSites(string $tenantFilter, string $type = 'All'): array
    {
        $response = $this->request('GET', '/api/ListSites', [
            'TenantFilter' => $tenantFilter,
            'type' => $type,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Azure AD Connect sync status and errors
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listAzureADConnectStatus(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListAzureADConnectStatus', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List recently deleted objects
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listDeletedItems(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListDeletedItems', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Just-In-Time administrative elevations
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listJITAdmins(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ExecJITAdmin', [
            'TenantFilter' => $tenantFilter,
            'Action' => 'List',
        ]);
        return $response['Results'] ?? [];
    }

    /**
     * List group configuration templates
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listGroupTemplates(): array
    {
        $response = $this->request('GET', '/api/ListGroupTemplates');
        return is_array($response) ? $response : [];
    }

    /**
     * List extensions configuration
     * 
     * @return array<string, mixed>
     */
    public function listExtensionsConfig(): array
    {
        $response = $this->request('GET', '/api/ListExtensionsConfig');
        return is_array($response) ? $response : [];
    }

    /**
     * List community repositories
     * 
     * @param bool $writeAccess Only show repos with write access
     * @return array<int, array<string, mixed>>
     */
    public function listCommunityRepos(bool $writeAccess = false): array
    {
        $params = [];
        if ($writeAccess) {
            $params['WriteAccess'] = 'true';
        }

        $response = $this->request('GET', '/api/ListCommunityRepos', $params);
        return $response['Results'] ?? [];
    }

    // ==================== Tenant Administration ====================

    /**
     * List alerts in the queue
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listAlertsQueue(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['TenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListAlertsQueue', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List CSP licenses
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listCSPLicenses(): array
    {
        $response = $this->request('GET', '/api/listCSPLicenses');
        return is_array($response) ? $response : [];
    }

    /**
     * List standards
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listStandards(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['TenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListStandards', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List standard templates
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listStandardTemplates(): array
    {
        $response = $this->request('GET', '/api/listStandardTemplates');
        return is_array($response) ? $response : [];
    }

    /**
     * List named locations
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listNamedLocations(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['TenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListNamedLocations', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List backups for a tenant
     * 
     * @param string $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ExecListBackup', [
            'tenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List CSP SKUs
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listCSPSKUs(): array
    {
        $response = $this->request('GET', '/api/ListCSPsku');
        return is_array($response) ? $response : [];
    }

    /**
     * List GDAP role templates
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listGDAPRoleTemplates(): array
    {
        $response = $this->request('GET', '/api/ExecGDAPRoleTemplate');
        return $response['Results'] ?? [];
    }

    /**
     * List GDAP roles mapped in CIPP
     * 
     * @return array<int, array<string, mixed>>
     */
    public function listGDAPRoles(): array
    {
        $response = $this->request('GET', '/api/ListGDAPRoles');
        return is_array($response) ? $response : [];
    }

    // ==================== Security & Compliance ====================

    /**
     * List security alerts
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listSecurityAlerts(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ExecAlertsList', [
            'TenantFilter' => $tenantFilter,
        ]);
        
        // Handle nested Results -> MSResults structure
        return $response['Results']['MSResults'] ?? [];
    }

    /**
     * List security incidents
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listSecurityIncidents(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ExecIncidentsList', [
            'TenantFilter' => $tenantFilter,
        ]);
        
        // Handle nested Results structure
        return $response['Results'] ?? [];
    }

    // ==================== Intune & Endpoint Management ====================

    /**
     * List applications for a tenant
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listApps(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListApps', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List applications in the queue
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listApplicationQueue(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListApplicationQueue', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Autopilot devices
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listAPDevices(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListAPDevices', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Autopilot configurations
     * 
     * @param string|null $type Configuration type (ESP, ApProfile)
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listAutopilotConfig(?string $type = null, ?string $tenantFilter = null): array
    {
        $params = [];
        if ($type) {
            $params['type'] = $type;
        }
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListAutopilotConfig', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Intune managed devices
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listDevices(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListDevices', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Intune policies
     * 
     * @param string|null $type Policy type (e.g., ESP)
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listIntunePolicies(?string $type = null, ?string $tenantFilter = null): array
    {
        $params = [];
        if ($type) {
            $params['type'] = $type;
        }
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListIntunePolicy', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Intune templates
     * 
     * @param bool $view Whether to view templates
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listIntuneTemplates(bool $view = false, ?string $tenantFilter = null): array
    {
        $params = [
            'View' => $view ? 'true' : 'false',
        ];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListIntuneTemplates', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Intune scripts
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listIntuneScripts(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListIntuneScript', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * List Defender state for devices
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listDefenderState(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListDefenderState', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Defender Threat & Vulnerability Management data
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listDefenderTVM(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListDefenderTVM', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Mailbox usage statistics for a tenant
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listMailboxUsage(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListMailboxUsage', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Mailbox sizes for a tenant
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listMailboxSizes(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListMailboxSizes', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Exchange protection settings (Anti-spam, anti-phish, etc.)
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @return array<int, array<string, mixed>>
     */
    public function listExchangeProtection(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListExchangeProtection', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    // ==================== Generic Graph Request ====================

    /**
     * Make a generic Graph API request through CIPP
     * 
     * @param string $tenantFilter Tenant domain or ID
     * @param string $endpoint Graph API endpoint (e.g., 'users')
     * @param string|null $select Fields to select
     * @param int|null $top Number of records to return
     * @return array<string, mixed>
     */
    public function graphRequest(string $tenantFilter, string $endpoint, ?string $select = null, ?int $top = null): array
    {
        $params = [
            'TenantFilter' => $tenantFilter,
            'Endpoint' => $endpoint,
        ];
        if ($select) {
            $params['$select'] = $select;
        }
        if ($top) {
            $params['$top'] = $top;
        }
        
        return $this->request('GET', '/api/ListGraphRequest', $params);
    }

    // ==================== Collaboration & Voice ====================

    /**
     * List Microsoft Teams for a tenant
     * 
     * @param string $tenantFilter Tenant filter
     * @param string $type Type of Teams list (default 'list')
     * @return array<int, array<string, mixed>>
     */
    public function listTeams(string $tenantFilter, string $type = 'list'): array
    {
        $response = $this->request('GET', '/api/ListTeams', [
            'TenantFilter' => $tenantFilter,
            'type' => $type,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Teams user activity metrics
     * 
     * @param string $tenantFilter Tenant filter
     * @param string $type Activity report type (default 'TeamsUserActivityUser')
     * @return array<int, array<string, mixed>>
     */
    public function listTeamsActivity(string $tenantFilter, string $type = 'TeamsUserActivityUser'): array
    {
        $response = $this->request('GET', '/api/ListTeamsActivity', [
            'TenantFilter' => $tenantFilter,
            'type' => $type,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Teams voice phone numbers
     * 
     * @param string $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listTeamsVoice(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListTeamsVoice', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List Teams emergency locations (LIS)
     * 
     * @param string $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listTeamsEmergencyLocations(string $tenantFilter): array
    {
        $response = $this->request('GET', '/api/ListTeamsLisLocation', [
            'TenantFilter' => $tenantFilter,
        ]);
        return is_array($response) ? $response : [];
    }

    // ==================== Security Tools & Breach Lookup ====================

    /**
     * List breaches for a specific account (email or domain)
     * 
     * @param string $account Email address or domain name
     * @return array<int, array<string, mixed>>
     */
    public function listAccountBreaches(string $account): array
    {
        $response = $this->request('GET', '/api/ListBreachesAccount', [
            'account' => $account,
        ]);
        return is_array($response) ? $response : [];
    }

    /**
     * List breaches found for a specific tenant
     * 
     * @param string|null $tenantFilter Tenant filter
     * @return array<int, array<string, mixed>>
     */
    public function listTenantBreaches(?string $tenantFilter = null): array
    {
        $params = [];
        if ($tenantFilter) {
            $params['tenantFilter'] = $tenantFilter;
        }

        $response = $this->request('GET', '/api/ListBreachesTenant', $params);
        return is_array($response) ? $response : [];
    }

    /**
     * Force a new breach search for a tenant
     * 
     * @param string $tenantFilter Tenant filter
     * @return array<string, mixed>
     */
    public function executeBreachSearch(string $tenantFilter): array
    {
        return $this->request('GET', '/api/ExecBreachSearch', [
            'tenantFilter' => $tenantFilter,
        ]);
    }

    // ==================== Advanced PowerShell & Advanced Requests ====================

    /**
     * List available Exchange cmdlets or execute a specific cmdlet
     * 
     * @param string $tenantFilter Tenant filter
     * @param string|null $cmdlet Cmdlet to execute (null to list available)
     * @param array<string, mixed> $params Parameters for the cmdlet
     * @param bool $compliance Whether to use compliance endpoints
     * @param bool $asApp Whether to execute as app
     * @return array<string, mixed>
     */
    public function listExoRequest(
        string $tenantFilter,
        ?string $cmdlet = null,
        array $params = [],
        bool $compliance = false,
        bool $asApp = false
    ): array {
        $body = [
            'tenantFilter' => $tenantFilter,
            'compliance' => $compliance,
        ];

        if ($cmdlet === null) {
            $body['availableCmdlets'] = true;
            $body['asApp'] = $asApp;
        } else {
            $body['cmdlet'] = $cmdlet;
            $body['cmdParams'] = $params;
            // The API spec doesn't explicitly mention asApp for cmdlet execution in the provided oneOf,
            // but it's often supported in CIPP's underlying logic.
        }

        $response = $this->request('POST', '/api/ListExoRequest', $body);
        return $response['Results'] ?? [];
    }

    /**
     * Clear cached access token
     */
    public function clearTokenCache(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY_PREFIX . $this->integration->integration_id);
    }
}
