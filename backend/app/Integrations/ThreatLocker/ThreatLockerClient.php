<?php

declare(strict_types=1);

namespace App\Integrations\ThreatLocker;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Concerns\HttpClientTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class ThreatLockerClient
{
    use HttpClientTrait;

    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;
    protected string $apiSecret;

    protected const CACHE_KEY_PREFIX = 'threatlocker_';
    protected const DEFAULT_TIMEOUT = 60;
    protected const DEFAULT_CONNECT_TIMEOUT = 15;
    protected const DEFAULT_PAGE_SIZE = 100;
    protected const MAX_PAGES = 200;

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        // Resolve the Portal API base URL from the provided host
        // User provides: https://api.g.threatlocker.com/
        // Portal API is: https://portalapi.g.threatlocker.com/portalApi/
        $this->baseUrl = $this->resolvePortalApiUrl($credentials['api_url'] ?? 'https://api.g.threatlocker.com/');
        $this->apiSecret = $credentials['api_secret'] ?? '';
    }

    /**
     * Resolve the Portal API URL from the user-provided API hostname.
     *
     * Extracts the instance letter (e.g. "g") from the provided URL and constructs
     * the correct Portal API base URL.
     *
     * Input examples:
     *   - https://api.g.threatlocker.com/
     *   - https://api.g.threatlocker.com
     *   - api.g.threatlocker.com
     *
     * Output: https://portalapi.g.threatlocker.com/portalApi/
     */
    protected function resolvePortalApiUrl(string $apiUrl): string
    {
        $apiUrl = rtrim(trim($apiUrl), '/');

        // Extract the instance letter from the host
        // Pattern: api.{instance}.threatlocker.com
        $host = parse_url($apiUrl, PHP_URL_HOST);
        if (!$host) {
            // Maybe they provided just the hostname without scheme
            $host = parse_url("https://{$apiUrl}", PHP_URL_HOST);
        }

        $instance = 'g'; // default
        if ($host && preg_match('/^api\.([a-z])\.threatlocker\.com$/i', $host, $matches)) {
            $instance = strtolower($matches[1]);
        }

        return "https://portalapi.{$instance}.threatlocker.com/portalApi";
    }

    /**
     * Test connection to ThreatLocker Portal API
     */
    public function testConnection(): bool
    {
        try {
            // Fetch page 1 of organizations (minimal request)
            $response = $this->makeRequest(
                'POST',
                'Organization/OrganizationGetChildOrganizationsByParameters',
                ['pageSize' => 1, 'pageNumber' => 1]
            );

            if ($response === null) {
                throw new AuthenticationException('ThreatLocker authentication failed - empty response', 401);
            }

            Log::info('ThreatLocker: Connection test successful', [
                'integration_id' => $this->integration->integration_id,
            ]);

            return true;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ThreatLocker: Connection test failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'ThreatLocker authentication failed: ' . $e->getMessage(),
                400
            );
        }
    }

    /**
     * Get all organizations (customers) from ThreatLocker
     *
     * Uses two endpoints and merges results:
     * 1. OrganizationGetChildOrganizationsByParameters (paginated, has computer count)
     * 2. OrganizationGetListForNav (nav tree, has hierarchy)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrganizations(): array
    {
        $allOrganizations = [];
        $seen = [];

        // Primary: paginated org list
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest(
                'POST',
                'Organization/OrganizationGetChildOrganizationsByParameters',
                ['pageSize' => self::DEFAULT_PAGE_SIZE, 'pageNumber' => $page]
            );

            $organizations = $this->extractResponseData($response);

            if (empty($organizations)) {
                break;
            }

            foreach ($organizations as $org) {
                $orgId = $org['organizationId'] ?? $org['id'] ?? null;
                if ($orgId && !isset($seen[$orgId])) {
                    $seen[$orgId] = true;
                    $allOrganizations[] = $org;
                }
            }

            $totalPages = $this->extractTotalPages($response);
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > self::MAX_PAGES) {
                Log::warning('ThreatLocker: Reached page limit while fetching organizations', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                break;
            }
        }

        // Supplementary: nav tree (may contain orgs not in child list)
        try {
            $navResponse = $this->makeRequest(
                'GET',
                'Organization/OrganizationGetListForNav',
                [],
                ['search' => '']
            );

            $navOrgs = $this->flattenNavTree($navResponse);
            foreach ($navOrgs as $org) {
                $orgId = $org['organizationId'] ?? null;
                if ($orgId && !isset($seen[$orgId])) {
                    $seen[$orgId] = true;
                    $allOrganizations[] = $org;
                }
            }
        } catch (\Exception $e) {
            Log::warning('ThreatLocker: Nav tree fetch failed (non-critical)', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('ThreatLocker: Fetched organizations', [
            'integration_id' => $this->integration->integration_id,
            'count' => count($allOrganizations),
        ]);

        return $allOrganizations;
    }

    /**
     * Get computers/endpoints for an organization
     *
     * @return array<int, array<string, mixed>>
     */
    public function getComputers(string $organizationId, bool $includeChildren = false): array
    {
        $allComputers = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest(
                'POST',
                'Computer/ComputerGetByAllParameters',
                [
                    'searchText' => '',
                    'computerGroup' => '00000000-0000-0000-0000-000000000000',
                    'orderBy' => 'computername',
                    'pageSize' => self::DEFAULT_PAGE_SIZE,
                    'pageNumber' => $page,
                    'computerId' => '00000000-0000-0000-0000-000000000000',
                    'childOrganizations' => $includeChildren,
                    'showLastCheckIn' => true,
                    'isAscending' => true,
                    'kindOfAction' => '',
                    'hasComputerPassword' => false,
                ],
                [],
                $organizationId
            );

            $computers = $this->extractResponseData($response);

            if (empty($computers)) {
                break;
            }

            $allComputers = array_merge($allComputers, $computers);

            $totalPages = $this->extractTotalPages($response);
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > self::MAX_PAGES) {
                break;
            }
        }

        Log::info('ThreatLocker: Fetched computers', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'count' => count($allComputers),
        ]);

        return $allComputers;
    }

    /**
     * Get application control policies for an organization + group
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPolicies(string $organizationId, ?string $computerGroupId = null): array
    {
        $body = [
            'activeOnly' => true,
            'filter' => '',
            'isEnabled' => true,
            'searchText' => '',
            'status' => 0,
            'utcOffset' => 0,
            'pageSize' => 500,
            'pageNumber' => 1,
        ];

        if ($computerGroupId) {
            $body['computerGroupId'] = $computerGroupId;
        }

        $response = $this->makeRequest(
            'POST',
            'Policy/PolicyGetByParameters',
            $body,
            [],
            $organizationId
        );

        $policies = $this->extractResponseData($response);

        Log::info('ThreatLocker: Fetched policies', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'count' => count($policies),
        ]);

        return $policies;
    }

    /**
     * Get computer groups and computers (lightweight) for an organization
     *
     * @return array{groups: array<int, array<string, mixed>>, computers: array<int, array<string, mixed>>}
     */
    public function getComputerGroups(string $organizationId): array
    {
        $response = $this->makeRequest(
            'GET',
            'ComputerGroup/ComputerGroupGetGroupAndComputer',
            [],
            [
                'osType' => '0',
                'includeGlobal' => 'true',
                'includeAllPolicies' => 'false',
                'includeOrganizations' => 'false',
                'includeParentGroups' => 'false',
                'includeLoggedInObjects' => 'false',
            ],
            $organizationId
        );

        $data = $this->extractResponseData($response);

        $groups = [];
        $computers = [];

        foreach ($data as $section) {
            $label = $section['label'] ?? '';
            $items = $section['items'] ?? [];

            if (!is_array($items)) {
                continue;
            }

            if (in_array($label, ['Other', 'Computer Groups'], true)) {
                foreach ($items as $item) {
                    $groups[] = [
                        'name' => $item['label'] ?? '',
                        'groupId' => $item['value'] ?? '',
                        'entityType' => $item['entityType'] ?? null,
                    ];
                }
            } elseif ($label === 'Computers') {
                foreach ($items as $item) {
                    $computers[] = [
                        'name' => $item['label'] ?? '',
                        'computerId' => $item['value'] ?? '',
                        'entityType' => $item['entityType'] ?? null,
                    ];
                }
            }
        }

        Log::info('ThreatLocker: Fetched computer groups', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'groups' => count($groups),
            'computers' => count($computers),
        ]);

        return compact('groups', 'computers');
    }

    /**
     * Get application control applications for an organization
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApplications(
        string $organizationId,
        string $search = '',
        bool $includeChildOrgs = false,
        bool $includeUnused = false
    ): array {
        $allApps = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest(
                'POST',
                'Application/ApplicationGetByParameters',
                [
                    'searchText' => $search,
                    'searchBy' => 'app',
                    'orderBy' => 'name',
                    'isAscending' => true,
                    'includeMaster' => true,
                    'permittedApplications' => !$includeUnused,
                    'isBuiltInApplication' => false,
                    'isHidden' => false,
                    'isTemporary' => false,
                    'category' => 1, // Custom
                    'osType' => 0,   // Windows
                    'includeChildOrganizations' => $includeChildOrgs,
                    'countries' => [],
                    'categories' => [],
                    'pageSize' => self::DEFAULT_PAGE_SIZE,
                    'pageNumber' => $page,
                ],
                [],
                $organizationId
            );

            $apps = $this->extractResponseData($response);

            if (empty($apps)) {
                break;
            }

            $allApps = array_merge($allApps, $apps);

            $totalPages = $this->extractTotalPages($response);
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > self::MAX_PAGES) {
                break;
            }
        }

        Log::info('ThreatLocker: Fetched applications', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'count' => count($allApps),
        ]);

        return $allApps;
    }

    /**
     * Get a single application by ID
     *
     * @return array<string, mixed>|null
     */
    public function getApplicationById(string $organizationId, string $applicationId): ?array
    {
        $response = $this->makeRequest(
            'GET',
            'Application/ApplicationGetById',
            [],
            ['applicationId' => $applicationId],
            $organizationId
        );

        return $response;
    }

    /**
     * Get files for a specific application
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApplicationFiles(string $organizationId, string $applicationId, string $search = ''): array
    {
        $allFiles = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest(
                'GET',
                'ApplicationFile/ApplicationFileGetByApplicationId',
                [],
                [
                    'applicationId' => $applicationId,
                    'searchText' => $search,
                    'pageSize' => (string) self::DEFAULT_PAGE_SIZE,
                    'pageNumber' => (string) $page,
                ],
                $organizationId
            );

            $files = $this->extractResponseData($response);

            if (empty($files)) {
                break;
            }

            $allFiles = array_merge($allFiles, $files);

            $totalPages = $this->extractTotalPages($response);
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > self::MAX_PAGES) {
                break;
            }
        }

        Log::info('ThreatLocker: Fetched application files', [
            'integration_id' => $this->integration->integration_id,
            'application_id' => $applicationId,
            'count' => count($allFiles),
        ]);

        return $allFiles;
    }

    /**
     * Get a single AppControl policy by ID
     *
     * @return array<string, mixed>|null
     */
    public function getPolicyById(string $organizationId, string $policyId): ?array
    {
        $response = $this->makeRequest(
            'GET',
            'Policy/PolicyGetById',
            [],
            ['policyId' => $policyId],
            $organizationId
        );

        return $response;
    }

    /**
     * Get storage control policies for an organization + group
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStoragePolicies(string $organizationId, ?string $computerGroupId = null): array
    {
        $body = [
            'activeOnly' => true,
            'filter' => '',
            'isEnabled' => true,
            'searchText' => '',
            'status' => 0,
            'pageSize' => 500,
            'pageNumber' => 1,
        ];

        if ($computerGroupId) {
            $body['computerGroupId'] = $computerGroupId;
        }

        $response = $this->makeRequest(
            'POST',
            'StoragePolicy/StoragePolicyGetByParameters',
            $body,
            [],
            $organizationId
        );

        $policies = $this->extractResponseData($response);

        Log::info('ThreatLocker: Fetched storage policies', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'count' => count($policies),
        ]);

        return $policies;
    }

    /**
     * Get a single storage control policy by ID
     *
     * @return array<string, mixed>|null
     */
    public function getStoragePolicyById(string $organizationId, string $storagePolicyId): ?array
    {
        $response = $this->makeRequest(
            'GET',
            'StoragePolicy/StoragePolicyGetById',
            [],
            ['storagePolicyId' => $storagePolicyId],
            $organizationId
        );

        return $response;
    }

    /**
     * Get storage control devices for an organization
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStorageDevices(string $organizationId): array
    {
        $allDevices = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->makeRequest(
                'POST',
                'StorageDevice/StorageDeviceGetByParameters',
                [
                    'pageSize' => self::DEFAULT_PAGE_SIZE,
                    'pageNumber' => $page,
                ],
                [],
                $organizationId
            );

            $devices = $this->extractResponseData($response);

            if (empty($devices)) {
                break;
            }

            $allDevices = array_merge($allDevices, $devices);

            $totalPages = $this->extractTotalPages($response);
            $hasMore = $page < $totalPages;
            $page++;

            if ($page > self::MAX_PAGES) {
                break;
            }
        }

        Log::info('ThreatLocker: Fetched storage devices', [
            'integration_id' => $this->integration->integration_id,
            'organization_id' => $organizationId,
            'count' => count($allDevices),
        ]);

        return $allDevices;
    }

    /**
     * Clear any cached tokens or data
     */
    public function clearCache(): void
    {
        $prefix = self::CACHE_KEY_PREFIX . $this->integration->integration_id;
        Cache::forget("{$prefix}_orgs");
    }

    /**
     * Make authenticated request to ThreatLocker Portal API
     *
     * @param string $method HTTP method (GET or POST)
     * @param string $endpoint API endpoint path (e.g. 'Organization/OrganizationGetChildOrganizationsByParameters')
     * @param array<string, mixed> $body Request body for POST requests
     * @param array<string, string> $query Query parameters for GET requests
     * @param string|null $orgId Organization ID for org-scoped requests
     * @return array<string, mixed>|null
     */
    protected function makeRequest(
        string $method,
        string $endpoint,
        array $body = [],
        array $query = [],
        ?string $orgId = null
    ): ?array {
        if (empty($this->apiSecret)) {
            Log::error('ThreatLocker: API secret is missing', [
                'integration_id' => $this->integration->integration_id,
                'credentials_keys' => array_keys($this->credentials),
            ]);
            throw new AuthenticationException('ThreatLocker API secret is required', 401);
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        // Append query string for GET requests
        if (!empty($query)) {
            $queryString = http_build_query($query);
            $url .= '?' . $queryString;
        }

        try {
            $headers = [
                'Authorization' => $this->apiSecret,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            // Add org scope header if provided
            if ($orgId) {
                $headers['managedorganizationid'] = $orgId;
            }

            Log::debug('ThreatLocker API request', [
                'integration_id' => $this->integration->integration_id,
                'method' => $method,
                'endpoint' => $endpoint,
                'org_id' => $orgId,
            ]);

            $request = $this->createHttpClient()
                ->timeout(self::DEFAULT_TIMEOUT)
                ->connectTimeout(self::DEFAULT_CONNECT_TIMEOUT)
                ->withHeaders($headers);

            if (strtoupper($method) === 'POST') {
                $response = $request->post($url, $body);
            } else {
                $response = $request->get($url);
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw new AuthenticationException(
                    'ThreatLocker authentication failed - invalid or expired API credentials',
                    $response->status()
                );
            }

            if ($response->status() === 429) {
                Log::warning('ThreatLocker: Rate limit hit', [
                    'integration_id' => $this->integration->integration_id,
                ]);
                throw new \Exception('ThreatLocker API rate limit exceeded');
            }

            if (!$response->successful()) {
                Log::error('ThreatLocker: API request failed', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                throw new \Exception("API request failed ({$response->status()}): " . substr($response->body(), 0, 200));
            }

            $this->rateLimiter->recordRequest($this->integration);

            // The Portal API returns pagination info in a "Pagination" response header
            $data = $response->json();
            $paginationHeader = $response->header('Pagination');
            if ($paginationHeader) {
                $pagination = json_decode($paginationHeader, true);
                if (is_array($pagination)) {
                    // Attach pagination metadata for the caller
                    if (is_array($data)) {
                        $data['_pagination'] = $pagination;
                    }
                }
            }

            Log::debug('ThreatLocker API response received', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'record_count' => is_array($data) ? count($data) : 0,
            ]);

            return $data;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('ThreatLocker: API request error', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract data records from a Portal API response.
     * The response may be a flat array of records or have a nested 'data' key.
     *
     * @param array<string, mixed>|list<mixed>|null $response
     * @return array<int, array<string, mixed>>
     */
    protected function extractResponseData(array|null $response): array
    {
        if ($response === null) {
            return [];
        }

        // Remove our injected pagination metadata
        unset($response['_pagination']);

        // If the response has a 'data' key, use that
        if (isset($response['data']) && is_array($response['data'])) {
            /** @var array<int, array<string, mixed>> */
            return $response['data'];
        }

        // If array is numerically indexed (list of objects), return as-is
        if (array_values($response) === $response) {
            /** @var array<int, array<string, mixed>> */
            return $response;
        }

        // Single object — wrap in array
        /** @var array<int, array<string, mixed>> */
        return [$response];
    }

    /**
     * Extract total pages from the Pagination response header data
     */
    protected function extractTotalPages(?array $response): int
    {
        if ($response === null) {
            return 1;
        }

        return (int) ($response['_pagination']['totalPages'] ?? 1);
    }

    /**
     * Flatten nested organization hierarchy from nav tree response
     *
     * @param array<string, mixed>|null $response
     * @return array<int, array<string, mixed>>
     */
    protected function flattenNavTree(?array $response): array
    {
        if ($response === null) {
            return [];
        }

        $flat = [];
        $items = $this->extractResponseData($response);

        foreach ($items as $item) {
            $flat[] = $item;

            // Recurse into child organizations
            if (isset($item['organizationHierarchyDtos']) && is_array($item['organizationHierarchyDtos'])) {
                $children = $this->flattenNavTree(['data' => $item['organizationHierarchyDtos']]);
                $flat = array_merge($flat, $children);
            }
        }

        return $flat;
    }
}
