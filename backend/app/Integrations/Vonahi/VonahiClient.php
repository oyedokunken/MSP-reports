<?php

declare(strict_types=1);

namespace App\Integrations\Vonahi;

use App\Services\RateLimiter;
use App\Models\Integration;
use App\Integrations\Concerns\HttpClientTrait;
use App\Integrations\Exceptions\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

final class VonahiClient
{
    use HttpClientTrait;

    private const DEFAULT_BASE_URL = 'https://app.vpentest.io/api/v3';
    private const MAX_PER_PAGE = 100;
    private const DEFAULT_TIMEOUT = 60;
    private const CONNECT_TIMEOUT = 15;
    private const MAX_RETRIES = 2;

    /** @var array<string, mixed> */
    protected array $credentials;
    protected string $baseUrl;
    protected RateLimiter $rateLimiter;
    protected Integration $integration;

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(array $credentials, RateLimiter $rateLimiter, Integration $integration)
    {
        $this->credentials = $credentials;
        $this->rateLimiter = $rateLimiter;
        $this->integration = $integration;

        $this->baseUrl = rtrim($credentials['api_url'] ?? self::DEFAULT_BASE_URL, '/');
    }

    /**
     * Test connection by listing companies.
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('/companies', ['per_page' => 1]);

            if ($response === null || !isset($response['data'])) {
                throw new AuthenticationException('Vonahi authentication failed - invalid response', 401);
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
     * Get all companies (paginated).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCompanies(): array
    {
        return $this->fetchAllPages('/companies');
    }

    /**
     * Get all assessments, optionally filtered by company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssessments(?string $companyId = null): array
    {
        $params = [];
        if ($companyId !== null) {
            $params['company_id'] = $companyId;
        }

        return $this->fetchAllPages('/assessments', $params);
    }

    /**
     * Get findings for a specific assessment (paginated).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFindings(string $assessmentId): array
    {
        return $this->fetchAllPages("/assessments/{$assessmentId}/findings");
    }

    /**
     * Get reports, optionally filtered by assessment or company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReports(?string $assessmentId = null, ?string $companyId = null): array
    {
        $params = [];
        if ($assessmentId !== null) {
            $params['assessment_id'] = $assessmentId;
        }
        if ($companyId !== null) {
            $params['company_id'] = $companyId;
        }

        return $this->fetchAllPages('/reports', $params);
    }

    /**
     * Get a single report with download URLs.
     *
     * @return array<string, mixed>|null
     */
    public function getReport(string $reportId): ?array
    {
        $response = $this->makeRequest("/reports/{$reportId}");
        return $response['data'] ?? null;
    }

    /**
     * Get agents, optionally filtered by company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAgents(?string $companyId = null): array
    {
        $params = [];
        if ($companyId !== null) {
            $params['company_id'] = $companyId;
        }

        return $this->fetchAllPages('/agents', $params);
    }

    /**
     * Get schedules, optionally filtered by company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSchedules(?string $companyId = null): array
    {
        $params = [];
        if ($companyId !== null) {
            $params['company_id'] = $companyId;
        }

        return $this->fetchAllPages('/schedules', $params);
    }

    /**
     * Get activities for a specific assessment (paginated).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivities(string $assessmentId): array
    {
        return $this->fetchAllPages("/assessments/{$assessmentId}/activities");
    }

    /**
     * Fetch all pages of a paginated endpoint.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAllPages(string $endpoint, array $params = []): array
    {
        $allItems = [];
        $page = 1;
        $params['per_page'] = self::MAX_PER_PAGE;

        do {
            $params['page'] = $page;
            $response = $this->makeRequest($endpoint, $params);

            $items = $response['data'] ?? [];
            $allItems = array_merge($allItems, $items);

            $totalPages = $response['total_pages'] ?? 1;

            Log::debug('Vonahi: Fetched page', [
                'integration_id' => $this->integration->integration_id,
                'endpoint' => $endpoint,
                'page' => $page,
                'items_count' => count($items),
                'total_pages' => $totalPages,
                'total_count' => $response['total_count'] ?? 'unknown',
            ]);

            $page++;
        } while ($page <= $totalPages);

        return $allItems;
    }

    /**
     * Make authenticated request to Vonahi vPenTest API v3.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $apiKey = $this->credentials['api_key'] ?? null;

        if (empty($apiKey)) {
            throw new AuthenticationException('Vonahi API key is required', 401);
        }

        $url = $this->baseUrl . $endpoint;
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                Log::debug('Vonahi API request', [
                    'integration_id' => $this->integration->integration_id,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt + 1,
                ]);

                $response = $this->createHttpClient()
                    ->timeout(self::DEFAULT_TIMEOUT)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->get($url, $params);

                if ($response->status() === 401 || $response->status() === 403) {
                    throw new AuthenticationException(
                        'Vonahi authentication failed - invalid or expired API key',
                        $response->status()
                    );
                }

                if ($response->status() === 429) {
                    Log::warning('Vonahi: Rate limit hit', [
                        'integration_id' => $this->integration->integration_id,
                        'endpoint' => $endpoint,
                    ]);
                    $attempt++;
                    if ($attempt >= self::MAX_RETRIES) {
                        throw new \Exception('Vonahi API rate limit exceeded after retries');
                    }
                    sleep(2 * $attempt);
                    continue;
                }

                if (!$response->successful()) {
                    Log::error('Vonahi: API request failed', [
                        'integration_id' => $this->integration->integration_id,
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception("API request failed with status {$response->status()}: {$response->body()}");
                }

                $this->rateLimiter->recordRequest($this->integration);

                return $response->json();

            } catch (AuthenticationException $e) {
                throw $e;
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt > self::MAX_RETRIES) {
                    Log::error('Vonahi: API request error after retries', [
                        'integration_id' => $this->integration->integration_id,
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
                sleep(2 * $attempt);
            }
        }

        throw new \Exception('Vonahi API: Max retries reached');
    }
}
