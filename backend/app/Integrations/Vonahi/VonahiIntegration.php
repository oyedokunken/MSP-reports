<?php

declare(strict_types=1);

namespace App\Integrations\Vonahi;

use App\Integrations\Concerns\HasHeartbeat;
use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Concerns\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class VonahiIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;

    protected Integration $integration;
    protected VonahiClient $client;
    protected CustomerMappingService $mappingService;

    /** @var array<int, mixed> */
    protected array $mappedDuringSync = [];
    protected int $batchSize = 50;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new VonahiClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            // Phase 1: Sync companies as customers
            $companies = $this->client->getCompanies();

            Log::info('Vonahi: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'company_count' => count($companies),
            ]);

            $this->syncCustomers($companies);
            $totalProcessed += count($companies);

            // Auto-match unmapped customers
            if (!$this->integration->is_source_of_truth) {
                $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->whereNull('customer_id')
                    ->count();

                if ($unmappedCount > 0) {
                    BatchAutoMatchCustomersJob::dispatchSync($this->integration);
                }
            }

            // Phase 2: Sync assessments + findings per company
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();

            $assessments = $this->client->getAssessments();

            Log::info('Vonahi: Syncing assessments', [
                'count' => count($assessments),
            ]);

            $assessmentBatch = [];
            foreach ($assessments as $assessment) {
                try {
                    $companyId = $assessment['company_id'] ?? null;
                    $integrationCustomerId = $customerMap[$companyId] ?? null;

                    $mapped = VonahiMapper::mapAssessment($assessment, $integrationCustomerId);
                    $assessmentBatch[] = $mapped;
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process assessment: {$e->getMessage()}";
                }
            }
            $this->bulkStoreHistoricalData('vonahi_assessment', $assessmentBatch);

            // Phase 3: Sync findings per assessment
            $findingsBatch = [];
            foreach ($assessments as $assessment) {
                try {
                    $this->sendHeartbeat();
                    $assessmentId = $assessment['id'] ?? null;
                    if (!$assessmentId) {
                        continue;
                    }

                    $companyId = $assessment['company_id'] ?? null;
                    $integrationCustomerId = $customerMap[$companyId] ?? null;

                    $findings = $this->client->getFindings($assessmentId);
                    foreach ($findings as $finding) {
                        $mapped = VonahiMapper::mapFinding($finding, $integrationCustomerId);
                        $findingsBatch[] = $mapped;
                        $totalProcessed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to fetch findings for assessment {$assessment['id']}: {$e->getMessage()}";
                }
            }
            $this->bulkStoreHistoricalData('vonahi_finding', $findingsBatch);

            // Phase 4: Sync reports
            $reports = $this->client->getReports();
            $reportBatch = [];
            foreach ($reports as $report) {
                try {
                    // Reports link to assessments which link to companies
                    $assessmentId = $report['assessment_id'] ?? null;
                    $companyId = $this->resolveCompanyFromAssessment($assessmentId, $assessments);
                    $integrationCustomerId = $customerMap[$companyId] ?? null;

                    $mapped = VonahiMapper::mapReport($report, $integrationCustomerId);
                    $reportBatch[] = $mapped;
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process report: {$e->getMessage()}";
                }
            }
            $this->bulkStoreHistoricalData('vonahi_report', $reportBatch);

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('Vonahi: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->updateCustomerCountsCache();

        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();

        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Log::info('Vonahi: Sync completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $mappedCount,
            'records_new' => $unmappedCount,
            'customers_mapped' => $this->mappedDuringSync,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $companies = $this->client->getCompanies();
        return array_map(fn($company) => VonahiMapper::mapCompany($company), $companies);
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $totalProcessed = 0;
        $errors = [];

        try {
            // Pre-load customers
            $companies = $this->client->getCompanies();
            $this->syncCustomers($companies);

            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();

            // Fetch all assessments and filter by date range
            $assessments = $this->client->getAssessments();
            $assessmentBatch = [];

            foreach ($assessments as $assessment) {
                $createdAt = isset($assessment['created_at']) ? Carbon::parse($assessment['created_at']) : null;
                if ($createdAt && ($createdAt->lt($startDate) || $createdAt->gt($endDate))) {
                    continue;
                }

                $companyId = $assessment['company_id'] ?? null;
                $integrationCustomerId = $customerMap[$companyId] ?? null;
                $mapped = VonahiMapper::mapAssessment($assessment, $integrationCustomerId);
                $assessmentBatch[] = $mapped;
                $totalProcessed++;
            }

            if (!empty($assessmentBatch)) {
                $this->bulkStoreHistoricalData('vonahi_assessment', $assessmentBatch);
            }

            // Findings for date-range assessments
            $findingsBatch = [];
            foreach ($assessmentBatch as $assessment) {
                $assessmentId = $assessment['payload']['id'] ?? null;
                if (!$assessmentId) {
                    continue;
                }

                try {
                    $findings = $this->client->getFindings($assessmentId);
                    foreach ($findings as $finding) {
                        $mapped = VonahiMapper::mapFinding($finding, $assessment['integration_customer_id']);
                        $findingsBatch[] = $mapped;
                        $totalProcessed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to fetch findings: {$e->getMessage()}";
                }

                $this->sendHeartbeat();
            }

            if (!empty($findingsBatch)) {
                $this->bulkStoreHistoricalData('vonahi_finding', $findingsBatch);
            }

            // Reports
            $reports = $this->client->getReports();
            $reportBatch = [];
            foreach ($reports as $report) {
                $releasedOn = isset($report['released_on']) ? Carbon::parse($report['released_on']) : null;
                $createdAt = isset($report['created_at']) ? Carbon::parse($report['created_at']) : null;
                $reportDate = $releasedOn ?? $createdAt;

                if ($reportDate && ($reportDate->lt($startDate) || $reportDate->gt($endDate))) {
                    continue;
                }

                $assessmentId = $report['assessment_id'] ?? null;
                $companyId = $this->resolveCompanyFromAssessment($assessmentId, $assessments);
                $integrationCustomerId = $customerMap[$companyId] ?? null;

                $mapped = VonahiMapper::mapReport($report, $integrationCustomerId);
                $reportBatch[] = $mapped;
                $totalProcessed++;
            }

            if (!empty($reportBatch)) {
                $this->bulkStoreHistoricalData('vonahi_report', $reportBatch);
            }

            $this->sendHeartbeat();

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
        }

        return [
            'success' => empty($errors),
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): Carbon
    {
        return now()->subDays(365);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Vonahi vPenTest',
            'description' => 'Automated network penetration testing and vulnerability assessment',
            'data_types' => [
                'vonahi_assessment',
                'vonahi_finding',
                'vonahi_report',
                'vonahi_agent',
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        Log::info('Vonahi: Disconnected', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return array<string, int>
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your API Key',
                'help' => 'Generate from your vPenTest profile → Generate API Key',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('vonahi', 'Vonahi vPenTest');
        $builder->description('Automated network penetration testing and vulnerability assessment');

        $builder->addSection('metrics', 'Security Overview', 'grid')
            ->icon('Shield')
            ->dataPath('metrics')
            ->gridColumns(4)
            ->addMetric('total_assessments', 'Assessments', 'total_assessments', 'number', 'Search', 'blue')
            ->addMetric('critical_findings', 'Critical', 'critical_findings', 'number', 'AlertTriangle', 'red')
            ->addMetric('high_findings', 'High', 'high_findings', 'number', 'AlertCircle', 'orange')
            ->addMetric('total_findings', 'Total Findings', 'total_findings', 'number', 'FileWarning', 'purple')
            ->end();

        $builder->addSection('findings', 'Security Findings', 'table')
            ->description('Vulnerabilities discovered during penetration tests')
            ->icon('Bug')
            ->dataPath('findings')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Finding', 'sortable' => true, 'filterable' => true],
                ['key' => 'affected_hosts_count', 'label' => 'Hosts', 'sortable' => true],
                [
                    'key' => 'severity',
                    'label' => 'Severity',
                    'sortable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'Critical' => 'error',
                        'High' => 'warning',
                        'Medium' => 'info',
                        'Low' => 'success',
                        'Informational' => 'default',
                    ],
                ],
                ['key' => 'recommendation', 'label' => 'Recommendation', 'sortable' => false],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->end();

        $builder->addSection('assessments', 'Assessments', 'table')
            ->description('Penetration test assessments')
            ->icon('Search')
            ->dataPath('assessments')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Assessment', 'sortable' => true, 'filterable' => true],
                [
                    'key' => 'severity',
                    'label' => 'Severity',
                    'sortable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'critical' => 'error',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'success',
                        'informational' => 'default',
                    ],
                ],
                ['key' => 'created_at', 'label' => 'Date', 'sortable' => true, 'format' => 'date'],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->end();

        return $builder->build();
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Bulk upsert companies as IntegrationCustomers.
     *
     * @param array<int, array<string, mixed>> $companies
     */
    private function syncCustomers(array $companies): void
    {
        $customerRows = collect($companies)
            ->filter(fn($c) => !empty($c['id']) && !empty($c['full_name'] ?? $c['short_name'] ?? null))
            ->map(fn($company) => [
                'integration_id' => $this->integration->integration_id,
                'external_id' => $company['id'],
                'name' => $company['full_name'] ?? $company['short_name'],
                'metadata' => json_encode($company),
                'active' => true,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        $this->bulkUpsertCustomers($customerRows);

        // Source-of-truth mapping
        if ($this->integration->is_source_of_truth) {
            $unmapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->whereNull('customer_id')
                ->get();

            foreach ($unmapped as $ic) {
                /** @var IntegrationCustomer $ic */
                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($ic, 'Vonahi');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $customerRows
     * @param positive-int $chunkSize
     */
    private function bulkUpsertCustomers(array $customerRows, int $chunkSize = 500): void
    {
        if (empty($customerRows)) {
            return;
        }

        foreach (array_chunk($customerRows, $chunkSize) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $row) {
                    DB::table('integration_customers')->updateOrInsert(
                        [
                            'integration_id' => $row['integration_id'],
                            'external_id' => $row['external_id'],
                        ],
                        [
                            'name' => $row['name'],
                            'metadata' => $row['metadata'],
                            'active' => $row['active'],
                            'last_seen_at' => $row['last_seen_at'],
                            'updated_at' => now(),
                        ]
                    );
                }
            });
        }
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function bulkStoreHistoricalData(string $dataType, array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        $externalIds = array_filter(array_map(fn($item) => $item['payload']['id'] ?? null, $batch));
        $now = now();

        DB::transaction(function () use ($dataType, $batch, $externalIds, $now) {
            if (!empty($externalIds)) {
                DB::table('integration_data')
                    ->where('integration_id', $this->integration->integration_id)
                    ->where('data_type', $dataType)
                    ->whereIn('external_id', $externalIds)
                    ->delete();
            }

            $inserts = [];
            foreach ($batch as $item) {
                $inserts[] = [
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $item['integration_customer_id'],
                    'external_id' => $item['payload']['id'] ?? null,
                    'data_type' => $dataType,
                    'data_timestamp' => $item['timestamp'] ?? $now,
                    'data_payload' => json_encode($item['payload']),
                    'imported_at' => $now,
                    'data_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('integration_data')->insert($inserts);
        });
    }

    private function updateCustomerCountsCache(): void
    {
        $cacheKey = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($cacheKey);

        $mapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')->count();

        $unmapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')->count();

        Cache::put($cacheKey, [
            'mapped' => $mapped,
            'unmapped' => $unmapped,
            'last_sync' => now(),
        ], now()->addMinutes(10));
    }

    /**
     * Resolve company_id from an assessment_id using the preloaded assessments.
     *
     * @param array<int, array<string, mixed>> $assessments
     */
    private function resolveCompanyFromAssessment(?string $assessmentId, array $assessments): ?string
    {
        if (!$assessmentId) {
            return null;
        }

        foreach ($assessments as $assessment) {
            if (($assessment['id'] ?? null) === $assessmentId) {
                return $assessment['company_id'] ?? null;
            }
        }

        return null;
    }
}
