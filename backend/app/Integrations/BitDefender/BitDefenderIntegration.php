<?php

namespace App\Integrations\BitDefender;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Jobs\BatchAutoMatchCustomersJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Integrations\Concerns\HasHeartbeat;
use Exception;
use Illuminate\Support\Collection;

class BitDefenderIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;

    protected Integration $integration;
    protected BitDefenderClient $client;
    protected CustomerMappingService $mappingService;
    protected array $mappedDuringSync = [];

    // Tunable parameters
    protected int $batchSize = 50;      // companies per dispatched batch/job
    protected int $pageSize   = 100;    // max perPage allowed by Bitdefender API
    protected $progressCallback = null;

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new BitDefenderClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name'        => 'api_key',
                'label'       => 'API Key',
                'type'        => 'password',
                'required'    => true,
                'placeholder' => 'Enter your BitDefender API Key',
                'help'        => 'Found in GravityZone Control Center under My Account > API Keys',
            ],
        ];
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        $startTime = microtime(true);
        $totalProcessed = 0;
        $errors = [];

        try {
            $state = json_decode($this->integration->sync_state, true) ?? [];
            $phase = $state['phase'] ?? 'customers';
            $offset = $state['offset'] ?? 0;

            $companies = $this->client->getEndpoints(); // → getCompaniesList()
            $totalCompanies = count($companies);

            Log::info('BitDefender sync started', [
                'integration_id' => $this->integration->integration_id,
                'phase'          => $phase,
                'companies'      => $totalCompanies,
                'offset'         => $offset,
            ]);

            // Phase 1: Customers
            if ($phase === 'customers') {
                $this->syncCustomers($companies);
                $totalProcessed = $totalCompanies;

                if (!$this->integration->is_source_of_truth) {
                    $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                        ->whereNull('customer_id')
                        ->count();

                    if ($unmappedCount > 0) {
                        BatchAutoMatchCustomersJob::dispatchSync($this->integration);
                        Log::info('Dispatched auto-match job', ['unmapped' => $unmappedCount]);
                    }
                }

                $this->saveSyncState('endpoints', 0, $totalCompanies);
                $phase = 'endpoints';
            }

            // Phase 2: Endpoints + Quarantine (threats)
            if ($phase === 'endpoints') {
                $this->syncEndpointsAndQuarantine($companies, $offset, $totalCompanies);
            }

            $this->integration->update(['sync_state' => null]);
            $this->updateCustomerCountsCache();

            $duration = round(microtime(true) - $startTime, 2);
            Log::info('BitDefender full sync completed', ['duration_seconds' => $duration]);
        } catch (Exception $e) {
            Log::error('BitDefender sync failed', ['error' => $e->getMessage()]);
            $errors[] = $e->getMessage();
        }

        return [
            'records_synced'     => $totalProcessed,
            'records_new'        => 0,
            'customers_mapped'   => $this->mappedDuringSync,
            'records_failed'     => count($errors),
            'errors'             => $errors,
        ];
    }

    protected function syncCustomers(array $companies): void
    {
        $customerRows = collect($companies)
            ->filter(fn($c) => !empty($c['id']) && !empty($c['name']))
            ->map(fn($company) => [
                'integration_id' => $this->integration->integration_id,
                'external_id'    => $company['id'],
                'name'           => $company['name'],
                'metadata'       => json_encode($company),
                'active'         => true,
                'last_seen_at'   => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ])->all();

        $this->bulkUpsertCustomers($customerRows);
        Log::info('Customers bulk upsert completed', ['count' => count($customerRows)]);
    }

    protected function syncEndpointsAndQuarantine(array $companies, int $startOffset, int $totalCompanies): void
    {
        // 1. Bulk fetch ALL quarantine items (handle permission 403 gracefully)
        // Quarantine items conveniently include a 'companyId' field.
        $allQuarantine = [];
        try {
            $allQuarantine = $this->client->getThreatDetections(null, null, $this->pageSize);
        } catch (\Exception $e) {
            Log::warning('BitDefender: Quarantine API access denied or failed', ['error' => $e->getMessage()]);
            $errors[] = "Quarantine API error: {$e->getMessage()}";
        }
        $quarantineByCompany = collect($allQuarantine)->groupBy('companyId');

        Log::info('Bulk API quarantine fetch completed', [
            'quarantine_count' => count($allQuarantine),
        ]);

        // 3. Pre-load customer mapping
        $companyIds = array_filter(array_column($companies, 'id'));
        $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereIn('external_id', $companyIds)
            ->pluck('integration_customer_id', 'external_id')
            ->toArray();

        // 4. Process in batches
        collect($companies)
            ->slice($startOffset)
            ->chunk($this->batchSize)
            ->each(function (Collection $chunk, $chunkIndex) use (
                $quarantineByCompany,
                $customerMap,
                $startOffset,
                $totalCompanies
            ) {
                $this->sendHeartbeat();

                $endpointBatch = [];
                $threatBatch = [];

                foreach ($chunk as $company) {
                    $companyId = $company['id'];
                    $integrationCustomerId = $customerMap[$companyId] ?? null;

                    if (!$integrationCustomerId) {
                        Log::warning('Missing customer record for integration', ['company_id' => $companyId]);
                        continue;
                    }

                    // Fetch endpoints FOR THIS COMPANY specifically (accurate mapping)
                    $companyEndpoints = $this->client->getEndpointsForCompany($companyId, $this->pageSize);
                    $companyQuarantine = $quarantineByCompany->get($companyId, collect());
                    foreach ($companyEndpoints as $endpoint) {
                        $mapped = BitDefenderMapper::mapEndpoint($endpoint);
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $endpointBatch[] = $mapped;
                    }

                    // Threats from quarantine
                    $companyQuarantine = $quarantineByCompany->get($companyId, collect());
                    foreach ($companyQuarantine as $item) {
                        $mapped = BitDefenderMapper::mapThreatDetection($item, $integrationCustomerId);
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $threatBatch[] = $mapped;
                    }
                }

                // Bulk store
                $this->bulkStoreHistoricalData('bitdefender_endpoint', $endpointBatch);
                $this->bulkStoreHistoricalData('bitdefender_threat_detection', $threatBatch);

                $newOffset = min($totalCompanies, $startOffset + (($chunkIndex + 1) * $this->batchSize));
                $this->saveSyncState('endpoints', $newOffset, $totalCompanies);

                Log::info('Processed batch', [
                    'batch_index' => $chunkIndex + 1,
                    'companies'   => $chunk->count(),
                    'endpoints'   => count($endpointBatch),
                    'threats'     => count($threatBatch),
                ]);

                // Memory cleanup
                unset($endpointBatch, $threatBatch);
            });

        Log::info('Endpoints & quarantine processing completed');
    }

    protected function bulkUpsertCustomers(array $customerRows, int $chunkSize = 500): void
    {
        if (empty($customerRows)) return;

        foreach (array_chunk($customerRows, $chunkSize) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $row) {
                    DB::table('integration_customers')->updateOrInsert(
                        [
                            'integration_id' => $row['integration_id'],
                            'external_id'    => $row['external_id'],
                        ],
                        [
                            'name'         => $row['name'],
                            'metadata'     => $row['metadata'],
                            'active'       => $row['active'],
                            'last_seen_at' => $row['last_seen_at'],
                            'updated_at'   => now(),
                        ]
                    );
                }
            });
        }
    }

    protected function bulkStoreHistoricalData(string $dataType, array $batch): void
    {
        if (empty($batch)) return;

        $externalIds = array_filter(array_map(function ($item) {
            // Priority: endpoint_id/threat_id, then id
            return $item['payload']['endpoint_id'] ??
                   $item['payload']['threat_id'] ??
                   $item['payload']['id'] ?? null;
        }, $batch));

        $now = now();

        DB::transaction(function () use ($dataType, $batch, $externalIds, $now) {
            // 1. Delete existing records by external_id
            if (!empty($externalIds)) {
                $query = DB::table('integration_data')
                    ->where('integration_id', $this->integration->integration_id)
                    ->where('data_type', $dataType)
                    ->whereIn('external_id', $externalIds);

                // CRITICAL FIX: For endpoint snapshots, we must only delete records for THIS specific timestamp.
                // otherwise, importing Day 2 overwrites Day 1 (same external_id).
                // Endpoint batches are always uniform in timestamp (see getHistoricalData).
                if ($dataType === 'bitdefender_endpoint') {
                    $ts = $batch[0]['timestamp'] ?? null;
                    if ($ts) {
                        $query->where('data_timestamp', $ts);
                    }
                }

                $query->delete();
            }

            // 2. Prepare inserts with external_id
            $inserts = [];
            foreach ($batch as $item) {
                // Extract external_id for the column
                $extId = $item['payload']['endpoint_id'] ??
                        $item['payload']['threat_id'] ??
                        $item['payload']['id'] ?? null;

                $inserts[] = [
                    'company_id'              => $this->integration->company_id,
                    'integration_id'          => $this->integration->integration_id,
                    'integration_customer_id' => $item['integration_customer_id'],
                    'external_id'             => $extId,
                    'data_type'               => $dataType,
                    'data_timestamp'          => $item['timestamp'] ?? $now,
                    'data_payload'            => json_encode($item['payload']),
                    'imported_at'             => $now,
                    'data_version'            => 1,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }

            DB::table('integration_data')->insert($inserts);
        });
    }

    protected function saveSyncState(string $phase, int $processed, int $total): void
    {
        DB::table('integrations')
            ->where('integration_id', $this->integration->integration_id)
            ->update([
                'sync_state' => json_encode([
                    'phase'     => $phase,
                    'offset'    => $processed,
                    'total'     => $total,
                    'timestamp' => now()->timestamp,
                ])
            ]);
    }

    protected function updateCustomerCountsCache(): void
    {
        $cacheKey = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($cacheKey);

        $mapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')->count();

        $unmapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')->count();

        Cache::put($cacheKey, [
            'mapped'    => $mapped,
            'unmapped'  => $unmapped,
            'last_sync' => now(),
        ], now()->addMinutes(10));
    }

    public function getCustomers(): array
    {
        $companies = $this->client->getEndpoints();
        return array_map(function ($company) {
            return [
                'external_id' => $company['id'] ?? null,
                'name'        => $company['name'] ?? 'Unknown',
                'metadata'    => $company,
                'active'      => true,
            ];
        }, $companies);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $totalProcessed = 0;
        $errors = [];

        try {
            Log::info('BitDefender historical import starting', [
                'integration_id' => $this->integration->integration_id,
                'start_date'     => $startDate->toDateTimeString(),
                'end_date'       => $endDate->toDateTimeString(),
            ]);

            // 1. Pre-load customers
            $companies = $this->client->getCompaniesList();
            $this->syncCustomers($companies);

            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();

            Log::debug('BitDefender historical: Mapping data', [
                'customers_count' => count($customerMap),
                'per_page'        => $this->pageSize,
            ]);

            // 2. Fetch threats for the range (optional - may not be available for all API keys)
            try {
                Log::debug('BitDefender historical: Attempting to fetch quarantine data');
                $threats = $this->client->getThreatDetections($startDate, $endDate, $this->pageSize);
                
                $threatBatch = [];
                foreach ($threats as $item) {
                    $companyId = $item['companyId'] ?? null;
                    $integrationCustomerId = $customerMap[$companyId] ?? null;

                    if (!$integrationCustomerId) {
                        // Try case-insensitive fallback if exact match fails
                        $integrationCustomerId = collect($customerMap)->first(fn($v, $k) => strtolower($k) === strtolower($companyId));
                    }

                    if (!$integrationCustomerId) {
                        Log::warning('BitDefender historical: Mapping failed for threat (missing customer)', [
                            'company_id' => $companyId,
                            'threat_id'  => $item['id'] ?? 'unknown'
                        ]);
                        continue;
                    }

                    $mapped = BitDefenderMapper::mapThreatDetection($item, $integrationCustomerId);
                    // Ensure the point-in-time timestamp is accurate to the detection time
                    if (!empty($item['detectionTime'])) {
                        $mapped['timestamp'] = Carbon::parse($item['detectionTime']);
                    }
                    
                    $threatBatch[] = $mapped;
                }

                if (!empty($threatBatch)) {
                    $this->bulkStoreHistoricalData('bitdefender_threat_detection', $threatBatch);
                    $totalProcessed += count($threatBatch);
                    Log::info('BitDefender historical: Threat data imported', ['count' => count($threatBatch)]);
                    
                    // Report progress incrementally
                    if ($this->progressCallback) {
                        ($this->progressCallback)(count($threatBatch));
                    }
                }
            } catch (\Exception $e) {
                Log::warning('BitDefender historical: Quarantine API unavailable or timed out', [
                    'error' => $e->getMessage(),
                    'note' => 'Continuing with endpoint data only'
                ]);
                // Continue processing - quarantine data is optional
            }

            // 3. Backfill endpoint snapshots (since they are current-state only)
            // We store the current endpoint state as if it was that way at the END of the chunk period.
            // This allows historical reports to have endpoint counts even if 30 days ago.
            $totalCompanies = count($companies);
            $processedCompanies = 0;
            
            foreach ($companies as $company) {
                $compExtId = $company['id'];
                $integrationCustomerId = $customerMap[$compExtId] ?? null;
                $processedCompanies++;
                
                // Calculate percentage through this chunk (0-100)
                $chunkSubProgress = ($totalCompanies > 0) ? ($processedCompanies / $totalCompanies) * 100 : 100;

                if (!$integrationCustomerId) {
                    if ($this->progressCallback) {
                        ($this->progressCallback)(0, $chunkSubProgress);
                    }
                    continue;
                }

                $countInBatch = 0;
                try {
                    $endpoints = $this->client->getEndpointsForCompany($compExtId, $this->pageSize);
                    $endpointBatch = [];
                    foreach ($endpoints as $endpoint) {
                        $mapped = BitDefenderMapper::mapEndpoint($endpoint);
                        $mapped['timestamp'] = $endDate; // Map to the end of this historical chunk
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $endpointBatch[] = $mapped;
                    }

                    if (!empty($endpointBatch)) {
                        $this->bulkStoreHistoricalData('bitdefender_endpoint', $endpointBatch);
                        $countInBatch = count($endpointBatch);
                        $totalProcessed += $countInBatch;
                        
                        // Report progress incrementally with sub-progress %
                        if ($this->progressCallback) {
                            ($this->progressCallback)($countInBatch, $chunkSubProgress);
                        }
                    } else if ($this->progressCallback) {
                        // Still update progress even if no endpoints found
                        ($this->progressCallback)(0, $chunkSubProgress);
                    }
                } catch (\Exception $e) {
                    Log::warning('BitDefender historical: Failed to fetch endpoints for company', [
                        'company' => $company['name'],
                        'error'   => $e->getMessage()
                    ]);
                    // Still update progress on failure
                    if ($this->progressCallback) {
                        ($this->progressCallback)(0, $chunkSubProgress);
                    }
                }
                $this->sendHeartbeat();
            }

            $this->sendHeartbeat();

            Log::info('BitDefender historical import chunk completed', [
                'integration_id'  => $this->integration->integration_id,
                'records_count'    => $totalProcessed,
                'start_date'      => $startDate->toDateString(),
                'end_date'        => $endDate->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('BitDefender historical import failed', [
                'integration_id' => $this->integration->integration_id,
                'error'          => $e->getMessage()
            ]);
            $errors[] = $e->getMessage();
        }

        return [
            'success'         => empty($errors),
            'total_processed' => $totalProcessed,
            'errors'          => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(90);
    }

    public function getMetadata(): array
    {
        return [
            'name'              => 'BitDefender GravityZone',
            'description'       => 'Endpoint protection and threat detection',
            'data_types'        => [
                'bitdefender_endpoint',
                'bitdefender_threat_detection',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        // No special cleanup needed
    }

    public function healthCheck(): bool
    {
        return $this->authenticate();
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour'   => 1000,
        ];
    }


}