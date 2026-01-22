<?php

namespace App\Integrations\ITGlue;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Exceptions\AuthenticationException;
use App\Integrations\Exceptions\ApiResponseException;
use App\Integrations\Exceptions\RateLimitExceededException;

class ITGlueClient
{
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    protected const DEFAULT_PAGE_SIZE = 50;
    protected const FLEXIBLE_ASSET_PAGE_SIZE = 1000;
    protected const DELAY_BETWEEN_PAGES_MS = 100;
    protected const MAX_PAGES = 50;
    protected const CACHE_TTL_HOURS = 6;
    protected const CACHE_TTL_ASSET_TYPES_HOURS = 24;
    protected const RATE_LIMIT_WAIT_TIMEOUT_SEC = 120;

    protected const ENDPOINT_ORGANIZATIONS        = '/organizations';
    protected const ENDPOINT_FLEXIBLE_ASSET_TYPES = '/flexible_asset_types';
    protected const ENDPOINT_FLEXIBLE_ASSETS      = '/flexible_assets';
    protected const ENDPOINT_SSL_CERTIFICATES     = '/ssl_certificates';
    protected const ENDPOINT_DOMAINS              = '/domains';

    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;
        $this->baseUrl = rtrim($credentials['base_url'] ?? 'https://api.au.itglue.com', '/');
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest(self::ENDPOINT_ORGANIZATIONS, ['page' => ['size' => 1]]);
            return !empty($response['data']);
        } catch (\Exception $e) {
            throw new AuthenticationException("IT Glue connection failed: {$e->getMessage()}", 401);
        }
    }

    public function getOrganizations(): array
    {
        $cacheKey = "itglue_orgs_{$this->integration->integration_id}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_HOURS), function () {
            return $this->fetchAllPages(self::ENDPOINT_ORGANIZATIONS, ['page' => ['size' => self::DEFAULT_PAGE_SIZE]]);
        });
    }

    public function getFlexibleAssetTypes(): array
    {
        $cacheKey = "itglue_asset_types_{$this->integration->integration_id}";

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_TTL_ASSET_TYPES_HOURS), fn () => 
            $this->fetchAllPages(self::ENDPOINT_FLEXIBLE_ASSET_TYPES, ['page' => ['size' => self::DEFAULT_PAGE_SIZE]])
        );
    }

    public function findLicenseAssetTypeIds(): array
    {
        $types = $this->getFlexibleAssetTypes();

        $keywords = ['license', 'licence', 'microsoft', 'office', 'm365', 'o365', '365', 'azure ad', 'licensing'];

        $ids = collect($types)
            ->filter(function ($type) use ($keywords) {
                $name = strtolower($type['attributes']['name'] ?? '');
                foreach ($keywords as $kw) {
                    if (str_contains($name, $kw)) {
                        return true;
                    }
                }
                return false;
            })
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            Log::warning('No license-related flexible asset types found', [
                'integration_id' => $this->integration->integration_id
            ]);
        }

        return $ids;
    }

    public function getFlexibleAssets(int $typeId, ?string $orgId = null, bool $includeOrg = true): array
    {
        $params = [
            'page'   => ['size' => self::FLEXIBLE_ASSET_PAGE_SIZE],
            'filter' => ['flexible_asset_type_id' => $typeId],
        ];

        if ($includeOrg) {
            $params['include'] = 'organization';
        }

        if ($orgId) {
            $params['filter']['organization_id'] = $orgId;
        }

        return $this->fetchAllPages(self::ENDPOINT_FLEXIBLE_ASSETS, $params);
    }

    public function getSslCertificates(string $expirationFilter = 'next90days'): array
    {
        $params = [
            'filter' => ['expiration' => $expirationFilter],
            'page'   => ['size' => self::DEFAULT_PAGE_SIZE],
        ];

        return $this->fetchAllPages(self::ENDPOINT_SSL_CERTIFICATES, $params);
    }

    public function getDomains(string $expirationFilter = 'next90days'): array
    {
        $params = [
            'filter' => ['expiration' => $expirationFilter],
            'page'   => ['size' => self::DEFAULT_PAGE_SIZE],
        ];

        return $this->fetchAllPages(self::ENDPOINT_DOMAINS, $params);
    }

    protected function fetchAllPages(string $endpoint, array $params = []): array
    {
        $allData = [];
        $page = 1;

        do {
            $params['page']['number'] = $page;
            $response = $this->makeRequest($endpoint, $params);
            $data = $response['data'] ?? [];
            $allData = [...$allData, ...$data];

            $meta = $response['meta'] ?? [];
            $current = $meta['current-page'] ?? $page;
            $totalPages = $meta['total-pages'] ?? 1;

            $page++;

            if ($page <= $totalPages) {
                usleep(self::DELAY_BETWEEN_PAGES_MS * 1000);
            }
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);

        if ($page > self::MAX_PAGES) {
            Log::warning('Pagination truncated - exceeded max pages', [
                'integration_id' => $this->integration->integration_id,
                'endpoint'       => $endpoint,
                'fetched'        => count($allData),
            ]);
        }

        return $allData;
    }

    protected function makeRequest(string $endpoint, array $params = [], int $retryCount = 0): array
    {
        if (!$this->rateLimiter->waitForSlot($this->integration, self::RATE_LIMIT_WAIT_TIMEOUT_SEC)) {
            throw new RateLimitExceededException("Rate limit wait timeout exceeded for IT Glue");
        }

        $url = $this->baseUrl . $endpoint;

        Log::debug('IT Glue API request', [
            'integration_id' => $this->integration->integration_id,
            'endpoint'       => $endpoint,
            'params'         => $params,
        ]);

        $response = Http::withHeaders([
            'x-api-key'     => $this->credentials['api_key'] ?? '',
            'Content-Type'  => 'application/vnd.api+json',
            'Accept'        => 'application/vnd.api+json',
            'User-Agent'    => 'YourApp-ITGlueIntegration/1.0',
        ])
            ->timeout(60)
            ->retry(2, 1000)
            ->get($url, $params);

        $this->rateLimiter->recordRequest($this->integration);

        if ($response->status() === 429 && $retryCount < 3) {
            $retryAfter = (int) $response->header('Retry-After', 60);
            $backoff = $retryAfter * pow(2, $retryCount);
            sleep($backoff);
            return $this->makeRequest($endpoint, $params, $retryCount + 1);
        }

        if ($response->status() === 401) {
            throw new AuthenticationException("IT Glue authentication failed - invalid API key or region");
        }

        if (!$response->successful()) {
            Log::error('IT Glue API request failed', [
                'integration_id' => $this->integration->integration_id,
                'endpoint'       => $endpoint,
                'status'         => $response->status(),
                'body_preview'   => substr($response->body(), 0, 500),
            ]);

            throw new ApiResponseException(
                "IT Glue API error",
                $response->status(),
                $response->body()
            );
        }

        Log::debug('IT Glue API response', [
            'integration_id' => $this->integration->integration_id,
            'status'         => $response->status(),
            'body_preview'   => substr($response->body(), 0, 500),
        ]);

        return $response->json() ?? [];
    }
}