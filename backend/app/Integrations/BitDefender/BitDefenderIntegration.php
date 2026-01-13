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
            
            // PHASE 1: Sync all customers FIRST (fast - just creates IntegrationCustomer records)
            if ($phase === 'customers') {
                foreach ($companies as $index => $company) {
                    if ($index < $resumeOffset) continue;
                    
                    $companyId = $company['id'] ?? null;
                    $companyName = $company['name'] ?? 'Unknown';
                    
                    if (!$companyId || empty($companyName)) continue;
                    
                    try {
                        DB::transaction(function () use ($company, $companyId, $companyName) {
                            IntegrationCustomer::updateOrCreate(
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
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        Log::warning('BitDefender sync: Failed to store customer', [
                            'company_id' => $companyId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    // Save progress every 10 companies
                    if ($index % 10 === 0) {
                        $this->saveSyncState('customers', $index, $totalCompanies);
                    }
                }
                
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
            // New: Only fetch endpoints + threats (customers already synced in phase 1)
            foreach ($items as $company) {
                $this->sendHeartbeat();
                
                $companyId = $company['id'] ?? null;
                if (!$companyId) continue;
                
                // Find existing integration customer
                $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->where('external_id', $companyId)
                    ->first();
                
                if (!$integrationCustomer) {
                    Log::warning('BitDefender batch: Customer not found for endpoints', ['company_id' => $companyId]);
                    continue;
                }
                
                try {
                    $this->fetchEndpointsForCustomer($integrationCustomer, $companyId);
                    $this->fetchThreatsForCustomer($integrationCustomer, $companyId);
                } catch (\Exception $e) {
                    Log::error('BitDefender batch: Failed to fetch endpoints/threats', [
                        'company_id' => $companyId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
    
    private function fetchEndpointsForCustomer($integrationCustomer, string $companyId): void
    {
        $endpoints = $this->client->getEndpointsForCompany($companyId);
        
        foreach ($endpoints as $endpoint) {
            $mapped = BitDefenderMapper::mapEndpoint($endpoint);
            $endpointId = $endpoint['id'] ?? null;
            
            if ($endpointId) {
                $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                    ->where('data_type', 'bitdefender_endpoint')
                    ->whereRaw("data_payload->>'endpoint_id' = ?", [$endpointId])
                    ->first();
                    
                if ($existing) {
                    $existing->update([
                        'data_timestamp' => $mapped['timestamp'],
                        'data_payload' => $mapped['payload'],
                        'imported_at' => now(),
                    ]);
                } else {
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'integration_customer_id' => $integrationCustomer->integration_customer_id,
                        'data_type' => $mapped['data_type'],
                        'data_timestamp' => $mapped['timestamp'],
                        'data_payload' => $mapped['payload'],
                        'imported_at' => now(),
                    ]);
                }
            }
        }
    }
    
    private function fetchThreatsForCustomer($integrationCustomer, string $companyId): void
    {
        try {
            $threats = $this->client->getThreatDetections(now()->subHours(6), now());
            
            foreach ($threats as $threat) {
                $threatCompanyId = $threat['companyId'] ?? null;
                if ($threatCompanyId && $threatCompanyId === $companyId) {
                    $mapped = BitDefenderMapper::mapThreatDetection($threat, $integrationCustomer->integration_customer_id);
                    $threatId = $threat['id'] ?? null;
                    
                    if ($threatId) {
                        $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                            ->where('data_type', 'bitdefender_threat_detection')
                            ->whereRaw("data_payload->>'threat_id' = ?", [$threatId])
                            ->first();

                        if ($existing) {
                            $existing->update([
                                'data_timestamp' => $mapped['timestamp'],
                                'data_payload' => $mapped['payload'],
                                'imported_at' => now(),
                            ]);
                        } else {
                            IntegrationData::create([
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'data_timestamp' => $mapped['timestamp'],
                                'data_payload' => $mapped['payload'],
                                'imported_at' => now(),
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('BitDefender batch: Threat detection failed', ['company' => $companyId, 'error' => $e->getMessage()]);
        }
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
            
            // FIRST: Sync all companies as IntegrationCustomers
            foreach ($companies as $index => $company) {
                 if ($index % 10 === 0) $this->sendHeartbeat();
                $companyId = $company['id'] ?? null;
                $companyName = $company['name'] ?? 'Unknown';
                
                if (!$companyId || empty($companyName)) continue;

                DB::transaction(function () use ($company, $companyId, $companyName) {
                    IntegrationCustomer::updateOrCreate(
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
            }
            
            // THEN: Fetch endpoint data for each company
            foreach ($companies as $index => $company) {
                 if ($index % 5 === 0) $this->sendHeartbeat();
                $companyId = $company['id'] ?? null;
                if (!$companyId) continue;

                // Now fetch the integration customer (guaranteed to exist)
                $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->where('external_id', $companyId)
                    ->first();

                if (!$integrationCustomer) {
                    Log::warning('BitDefenderIntegration::getHistoricalData - customer still not found', [
                        'company_id' => $companyId,
                        'company_name' => $company['name'] ?? 'Unknown',
                    ]);
                    continue;
                }


                try {
                    // Fetch and process endpoints immediately (memory efficient)
                    $this->client->getEndpointsForCompany($companyId, function($endpoint) use ($integrationCustomer, &$totalProcessed) {
                        $mapped = BitDefenderMapper::mapEndpoint($endpoint);
                        
                        // Store endpoint data immediately
                        IntegrationData::create([
                            'company_id' => $this->integration->company_id,
                            'integration_id' => $this->integration->integration_id,
                            'integration_customer_id' => $integrationCustomer->integration_customer_id,
                            'data_type' => $mapped['data_type'],
                            'data_timestamp' => $mapped['timestamp'],
                            'data_payload' => $mapped['payload'],
                            'imported_at' => now(),
                        ]);
                        
                        $totalProcessed++;
                    });
                    
                    // Fetch threat detections (quarantine items)
                    $threats = $this->client->getThreatDetections();
                    
                    foreach ($threats as $threat) {
                        // Try to match threat to endpoint and customer
                        $endpointId = $threat['endpointId'] ?? null;
                        $companyIdFromThreat = $threat['companyId'] ?? null;
                        
                        // If threat has company ID, find the customer
                        $threatCustomer = $integrationCustomer;
                        if ($companyIdFromThreat && $companyIdFromThreat !== $companyId) {
                            $threatCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                                ->where('external_id', $companyIdFromThreat)
                                ->first();
                        }
                        
                        if ($threatCustomer) {
                            $mapped = BitDefenderMapper::mapThreatDetection($threat, $threatCustomer->integration_customer_id);
                            
                            IntegrationData::create([
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $threatCustomer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'data_timestamp' => $mapped['timestamp'],
                                'data_payload' => $mapped['payload'],
                                'imported_at' => now(),
                            ]);
                            
                            $totalProcessed++;
                        }
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
