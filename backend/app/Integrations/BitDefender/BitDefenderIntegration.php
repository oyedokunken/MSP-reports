<?php

namespace App\Integrations\BitDefender;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationData;
use App\Models\IntegrationCustomer;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Integrations\Concerns\HasHeartbeat;
use Exception;

class BitDefenderIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    protected Integration $integration;
    protected BitDefenderClient $client;
    protected CustomerMappingService $mappingService;
    protected array $mappedDuringSync = [];

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
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your BitDefender API Key',
                'help' => 'Found in GravityZone Control Center under My Account > API Keys',
            ],
        ];
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        $totalProcessed = 0;
        $this->mappedDuringSync = [];
        $errors = [];

        try {
            // 1. Checkpoint: Resume
            $state = json_decode($this->integration->sync_state, true);
            $phase = $state['phase'] ?? 'customers';
            $resumeOffset = $state['offset'] ?? 0;
            
            // Fetch all companies from BitDefender
            $companies = $this->client->getEndpoints();
            $totalCompanies = count($companies);
            
            Log::info('BitDefender sync: Starting', [
                'phase' => $phase,
                'total_companies' => $totalCompanies,
                'resume_offset' => $resumeOffset,
            ]);
            
            // PHASE 1: Sync all customers FIRST (fast - bulk upsert)
            if ($phase === 'customers') {
                // Collect all customer rows first (like old BitdefenderSyncClass)
                $customerRows = [];
                $companiesToProcess = array_slice($companies, $resumeOffset);
                
                foreach ($companiesToProcess as $company) {
                    $companyId = $company['id'] ?? null;
                    $companyName = $company['name'] ?? 'Unknown';
                    
                    if (!$companyId || empty($companyName)) continue;
                    
                    $customerRows[] = [
                        'integration_id' => $this->integration->integration_id,
                        'external_id' => $companyId,
                        'name' => $companyName,
                        'metadata' => json_encode($company),
                        'active' => true,
                        'last_seen_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                // Bulk upsert in chunks (single transaction per chunk)
                $this->bulkUpsertCustomers($customerRows);
                $totalProcessed = count($customerRows);
                
                Log::info('BitDefender sync: All customers synced', [
                    'integration_id' => $this->integration->integration_id,
                    'total_customers' => $totalProcessed,
                ]);
                
                // Run auto-match ONLY if there are unmapped customers
                if (!$this->integration->is_source_of_truth) {
                    $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                        ->whereNull('customer_id')
                        ->count();
                    
                    if ($unmappedCount > 0) {
                        BatchAutoMatchCustomersJob::dispatchSync($this->integration);
                        Log::info('BitDefender sync: Dispatched auto-match job', [
                            'integration_id' => $this->integration->integration_id,
                            'unmapped_count' => $unmappedCount,
                        ]);
                    }
                }
                
                $this->saveSyncState('endpoints', 0, $totalCompanies);
            }

            // PHASE 2: Dispatch endpoint/threat sync jobs (slow - separate from customers)
            if ($phase === 'customers' || $phase === 'endpoints') {
                $offset = ($phase === 'endpoints') ? $resumeOffset : 0;
                $companiesToProcess = array_slice($companies, $offset);
                
                collect($companiesToProcess)->chunk(5)->each(function ($chunk, $index) use ($offset, $totalCompanies) {
                    \App\Jobs\ProcessIntegrationBatchJob::dispatch(
                        $this->integration, 
                        'endpoints_threats', 
                        $chunk->toArray(), 
                        $this->currentSyncLogId
                    );
                    
                    $this->saveSyncState('endpoints', $offset + (($index + 1) * 5), $totalCompanies);
                });
            }
             
            $this->integration->update(['sync_state' => null]);
            $this->updateCustomerCountsCache();

        } catch (Exception $e) {
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('BitDefender sync: Failed', ['error' => $e->getMessage()]);
        }
        
        return [
            'records_synced' => $totalProcessed,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }
    
    protected function saveSyncState(string $phase, int $processed, int $total): void
    {
        DB::table('integrations')
            ->where('integration_id', $this->integration->integration_id)
            ->update([
                'sync_state' => json_encode([
                    'phase' => $phase,
                    'offset' => $processed,
                    'total' => $total,
                    'timestamp' => now()->timestamp,
                ])
            ]);
    }
    
    /**
     * Bulk upsert customers using chunked operations (like old BitdefenderSyncClass)
     */
    protected function bulkUpsertCustomers(array $customerRows, int $chunkSize = 500): void
    {
        if (empty($customerRows)) return;
        
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
    
    public function processBatch(string $type, array $items): void
    {
        if ($type === 'companies') {
            // Legacy: Full sync - create customer + fetch endpoints + threats
            foreach ($items as $company) {
                $this->sendHeartbeat();
                
                $companyId = $company['id'] ?? null;
                $companyName = $company['name'] ?? 'Unknown';
                
                if (!$companyId) continue;
                
                try {
                    $integrationCustomer = DB::transaction(function () use ($company, $companyId, $companyName) {
                        return IntegrationCustomer::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'external_id' => $companyId,
                            ],
                            [
                                'name' => $companyName,
                                'metadata' => $company,
                                'active' => true,
                                'last_seen_at' => now(),
                            ]
                        );
                    });
                    
                    $this->fetchEndpointsForCustomer($integrationCustomer, $companyId);
                    $this->fetchThreatsForCustomer($integrationCustomer, $companyId);

                } catch (\Exception $e) {
                    Log::error('BitDefender batch: Failed to process company', [
                        'company' => $companyName,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } elseif ($type === 'endpoints_threats') {
            // Optimized: Pre-fetch data ONCE to avoid N+1 (like old BitdefenderSyncClass)
            $endpointBatch = [];
            $threatBatch = [];
            
            // 1. Pre-load all customer mappings for this batch (single query)
            $companyIds = array_filter(array_map(fn($c) => $c['id'] ?? null, $items));
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->whereIn('external_id', $companyIds)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
            
            // 2. Pre-fetch ALL threats ONCE and group by company (avoids N+1)
            $allThreats = collect($this->client->getThreatDetections(now()->subHours(6), now()))
                ->groupBy('companyId');
            
            $this->sendHeartbeat();
            
            // 3. Process each company - only fetch endpoints per company (required)
            foreach ($items as $company) {
                $companyId = $company['id'] ?? null;
                if (!$companyId) continue;
                
                $integrationCustomerId = $customerMap[$companyId] ?? null;
                if (!$integrationCustomerId) {
                    Log::warning('BitDefender batch: Customer not found for endpoints', ['company_id' => $companyId]);
                    continue;
                }
                
                try {
                    // Fetch endpoints for this company (must be per-company)
                    $endpoints = $this->client->getEndpointsForCompany($companyId);
                    foreach ($endpoints as $endpoint) {
                        $mapped = BitDefenderMapper::mapEndpoint($endpoint);
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $endpointBatch[] = $mapped;
                    }
                    
                    // Use pre-fetched threats (no API call!)
                    foreach ($allThreats->get($companyId, []) as $threat) {
                        $mapped = BitDefenderMapper::mapThreatDetection($threat, $integrationCustomerId);
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $threatBatch[] = $mapped;
                    }
                } catch (\Exception $e) {
                    Log::error('BitDefender batch: Failed to fetch endpoints', [
                        'company_id' => $companyId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $this->sendHeartbeat();
            }
            
            // Bulk store all endpoints and threats
            $this->bulkStoreHistoricalData('bitdefender_endpoint', $endpointBatch);
            $this->bulkStoreHistoricalData('bitdefender_threat_detection', $threatBatch);
        }
    }




    /**
     * Bulk store historical data (optimized for performance)
     * Uses single transaction with bulk DELETE + INSERT operations
     */
    private function bulkStoreHistoricalData(string $dataType, array $batch): void
    {
        if (empty($batch)) return;

        // Extract external IDs for deletion
        $externalIds = array_filter(array_map(function($item) {
            return $item['payload']['endpoint_id'] ?? $item['payload']['threat_id'] ?? $item['payload']['id'] ?? null;
        }, $batch));
        
        $now = now();

        DB::transaction(function () use ($dataType, $batch, $externalIds, $now) {
            // Delete existing records in one query
            if (!empty($externalIds)) {
                DB::table('integration_data')
                    ->where('integration_id', $this->integration->integration_id)
                    ->where('data_type', $dataType)
                    ->where(function($query) use ($externalIds) {
                        foreach ($externalIds as $externalId) {
                            $query->orWhereRaw("data_payload->>'endpoint_id' = ?", [(string)$externalId])
                                  ->orWhereRaw("data_payload->>'threat_id' = ?", [(string)$externalId])
                                  ->orWhereRaw("data_payload->>'id' = ?", [(string)$externalId]);
                        }
                    })
                    ->delete();
            }

            // Prepare insert data
            $insertData = [];
            foreach ($batch as $item) {
                $insertData[] = [
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $item['integration_customer_id'],
                    'data_type' => $dataType,
                    'data_timestamp' => $item['timestamp'],
                    'data_payload' => json_encode($item['payload']),
                    'imported_at' => $now,
                    'data_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Insert all records in one query
            if (!empty($insertData)) {
                DB::table('integration_data')->insert($insertData);
            }
        });

        $this->lastBatchProcessedCount = count($batch);
    }

    private $lastBatchProcessedCount = 0;

    public function getLastBatchProcessedCount(): int
    {
        return $this->lastBatchProcessedCount;
    }

    private function processUnmappedCustomers(): array
    {
        // Only dispatch if there are actually unmapped customers
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatch($this->integration);
            \Log::info('BitDefender: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
        return []; // Batch job handles mapping asynchronously
    }

    private function updateCustomerCountsCache(): void
    {
        $cacheKey = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($cacheKey);
        
        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Cache::put($cacheKey, [
            'mapped' => $mappedCount,
            'unmapped' => $unmappedCount,
            'last_sync' => now(),
        ], now()->addMinutes(10));
    }

    public function getCustomers(): array
    {
        $companies = $this->client->getEndpoints();
        return array_map(function($company) {
            return [
                'external_id' => $company['id'] ?? null,
                'name' => $company['name'] ?? 'Unknown',
                'metadata' => $company,
                'active' => true,
            ];
        }, $companies);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // NO MORE $data = [] bucket! Just track stats
        $errors = [];
        $totalProcessed = 0;

        try {
            Log::info('BitDefenderIntegration::getHistoricalData started', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // Get all companies
            $companies = $this->client->getEndpoints();
            
            // 1. Bulk sync customers first (like old BitdefenderSyncClass)
            $customerRows = [];
            foreach ($companies as $company) {
                $companyId = $company['id'] ?? null;
                $companyName = $company['name'] ?? 'Unknown';
                
                if (!$companyId || empty($companyName)) continue;
                
                $customerRows[] = [
                    'integration_id' => $this->integration->integration_id,
                    'external_id' => $companyId,
                    'name' => $companyName,
                    'metadata' => json_encode($company),
                    'active' => true,
                    'last_seen_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $this->bulkUpsertCustomers($customerRows);
            $this->sendHeartbeat();
            
            // 2. Pre-load customer map (single query, no N+1)
            $companyIds = array_filter(array_map(fn($c) => $c['id'] ?? null, $companies));
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->whereIn('external_id', $companyIds)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
            
            // 3. Pre-fetch ALL threats ONCE and group by company (avoids N+1)
            $allThreats = collect($this->client->getThreatDetections())
                ->groupBy('companyId');
            $this->sendHeartbeat();
            
            // 4. Collect all endpoint and threat data in memory
            $endpointBatch = [];
            $threatBatch = [];
            
            foreach ($companies as $index => $company) {
                if ($index % 10 === 0) $this->sendHeartbeat();
                
                $companyId = $company['id'] ?? null;
                if (!$companyId) continue;
                
                $integrationCustomerId = $customerMap[$companyId] ?? null;
                if (!$integrationCustomerId) continue;

                try {
                    // Fetch endpoints for this company
                    $endpoints = $this->client->getEndpointsForCompany($companyId);
                    foreach ($endpoints as $endpoint) {
                        $mapped = BitDefenderMapper::mapEndpoint($endpoint);
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $endpointBatch[] = $mapped;
                    }
                    
                    // Use pre-fetched threats (no API call!)
                    foreach ($allThreats->get($companyId, []) as $threat) {
                        $mapped = BitDefenderMapper::mapThreatDetection($threat, $integrationCustomerId);
                        $mapped['integration_customer_id'] = $integrationCustomerId;
                        $threatBatch[] = $mapped;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Failed to process historical data for company {$companyId}: {$e->getMessage()}";
                    Log::error('BitDefenderIntegration::getHistoricalData company failed', [
                        'integration_id' => $this->integration->integration_id,
                        'company_id' => $companyId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // 5. Bulk store all data (single transaction per type)
            $this->bulkStoreHistoricalData('bitdefender_endpoint', $endpointBatch);
            $this->bulkStoreHistoricalData('bitdefender_threat_detection', $threatBatch);
            $totalProcessed = count($endpointBatch) + count($threatBatch);

            Log::info('BitDefenderIntegration::getHistoricalData completed', [
                'integration_id' => $this->integration->integration_id,
                'total_processed' => $totalProcessed,
                'errors' => count($errors),
            ]);

        } catch (Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            Log::error('BitDefenderIntegration::getHistoricalData failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        // BitDefender provides current state data, not historical time-series
        // Return 30 days as a reasonable range for current state snapshots
        return now()->subDays(30);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'BitDefender',
            'description' => 'BitDefender endpoint security and antivirus integration (placeholder)',
            'data_types' => [
                'bitdefender_endpoint',
                'bitdefender_threat_detection',
                'endpoint_protection',
                'malware_incident',
            ],
            'supports_historical' => true,
            'historical_range_days' => 30,
        ];
    }

    public function disconnect(): void
    {
        Log::info('BitDefenderIntegration::disconnect called - nothing to do for placeholder integration', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        return $this->authenticate();
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }
}
