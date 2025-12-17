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
use Exception;

class BitDefenderIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
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
        $totalNew = 0;
        $this->mappedDuringSync = [];
        $errors = [];

        try {
            Log::info('BitDefenderIntegration::sync started', [
                'integration_id' => $this->integration->integration_id,
            ]);

            // Fetch companies from BitDefender (these are the customers)
            $companies = $this->client->getEndpoints(); // getEndpoints() actually returns companies
            
            Log::info('BitDefenderIntegration::sync fetched companies', [
                'integration_id' => $this->integration->integration_id,
                'company_count' => count($companies),
            ]);

            // Process each company (customer)
            foreach ($companies as $company) {
                try {
                    $companyId = $company['id'] ?? null;
                    $companyName = $company['name'] ?? 'Unknown';
                    
                    if (!$companyId || empty($companyName)) {
                        continue;
                    }

                    // Store company as IntegrationCustomer with DB transaction
                    DB::transaction(function () use ($company, $companyId, $companyName, &$totalNew, &$errors) {
                        $integrationCustomer = IntegrationCustomer::updateOrCreate(
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
                        $integrationCustomer->refresh();
                        
                        // We track mapped customers, but real mapping happens in the batch job
                    });
                    
                    $totalProcessed++;
                } catch (Exception $e) {
                    $companyId = $company['id'] ?? 'unknown';
                    $errors[] = "Failed to process company {$companyId}: {$e->getMessage()}";
                    Log::error('BitDefenderIntegration::sync company processing failed', [
                        'integration_id' => $this->integration->integration_id,
                        'company_id' => $companyId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // OPTIMIZATION: Dispatch batch job IMMEDIATELY after company processing
            // This ensures customers show up in the UI "Matched" list while the slow endpoint loop runs
            BatchAutoMatchCustomersJob::dispatch($this->integration);

            // Now loop AGAIN to fetch endpoints (The Slow Part)
            // We do this in a separate loop so the DB transaction above stays short and responsive
            foreach ($companies as $company) {
                $companyId = $company['id'] ?? null;
                $companyName = $company['name'] ?? 'Unknown';
                if (!$companyId) continue;

                try {
                    // Fetch endpoints for this company
                    $endpoints = $this->client->getEndpointsForCompany($companyId);
                    
                    Log::info('BitDefenderIntegration::sync fetched endpoints for company', [
                        'integration_id' => $this->integration->integration_id,
                        'company_id' => $companyId,
                        'company_name' => $companyName,
                        'endpoint_count' => count($endpoints),
                    ]);

                    // Store each endpoint as time-series data
                    foreach ($endpoints as $endpoint) {
                        try {
                            $mapped = BitDefenderMapper::mapEndpoint($endpoint);
                            
                            $intCustId = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                                ->where('external_id', $companyId)
                                ->value('integration_customer_id');

                            if (!$intCustId) {
                                $errors[] = "Validation failed: Could not find integration_customer_id for company {$companyId}";
                                continue;
                            }

                            IntegrationData::create([
                                'company_id' => $this->integration->company_id,
                                'integration_id' => $this->integration->integration_id,
                                // Need to re-fetch customer ID as we are in new loop
                                'integration_customer_id' => $intCustId,
                                'data_type' => $mapped['data_type'],
                                'data_timestamp' => $mapped['timestamp'],
                                'data_payload' => $mapped['payload'],
                                'imported_at' => now(),
                            ]);
                            $totalNew++;
                        } catch (Exception $e) {
                            $endpointId = $endpoint['id'] ?? 'unknown';
                            $errors[] = "Failed to process endpoint {$endpointId} for company {$companyName}: {$e->getMessage()}";
                        }
                    }
                    
                    // Also sync threat detections during regular sync
                    // Only fetch threats from last 6 hours (matching sync frequency)
                    try {
                        $threats = $this->client->getThreatDetections(
                            now()->subHours(6), // Start: 6 hours ago
                            now()               // End: now
                        );
                        
                        foreach ($threats as $threat) {
                            $threatCompanyId = $threat['companyId'] ?? null;
                            if ($threatCompanyId && $threatCompanyId === $companyId) {
                                // Need integration_customer_id
                                $intCustId = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                                    ->where('external_id', $companyId)
                                    ->value('integration_customer_id');

                                $mapped = BitDefenderMapper::mapThreatDetection($threat, $intCustId);
                                
                                IntegrationData::create([
                                    'company_id' => $this->integration->company_id,
                                    'integration_id' => $this->integration->integration_id,
                                    'integration_customer_id' => $intCustId,
                                    'data_type' => $mapped['data_type'],
                                    'data_timestamp' => $mapped['timestamp'],
                                    'data_payload' => $mapped['payload'],
                                    'imported_at' => now(),
                                ]);
                                $totalNew++;
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('BitDefenderIntegration::sync threat detection failed', [
                            'integration_id' => $this->integration->integration_id,
                            'company_id' => $companyId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } catch (Exception $e) {
                    $errors[] = "Failed to fetch endpoints for {$companyName}: {$e->getMessage()}";
                }
            }

            // Process any remaining unmapped customers
            $additionalMapped = $this->processUnmappedCustomers();
            $allMappedCustomers = array_merge($this->mappedDuringSync, $additionalMapped);

            // Update cache with customer counts
            $this->updateCustomerCountsCache();

            Log::info('BitDefenderIntegration::sync completed', [
                'integration_id' => $this->integration->integration_id,
                'total_processed' => $totalProcessed,
                'total_new' => $totalNew,
                'customers_mapped' => count($allMappedCustomers),
                'errors' => count($errors),
            ]);

        } catch (Exception $e) {
            // Rethrow rate limit exceptions so the job can be released back to the queue
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }
            
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('BitDefenderIntegration::sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        return [
            'records_synced' => $totalProcessed,
            'records_new' => $unmappedCount,
            'customers_mapped' => $allMappedCustomers ?? [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function processUnmappedCustomers(): array
    {
        // Dispatch single batch job to process all unmapped customers
        BatchAutoMatchCustomersJob::dispatch($this->integration);
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
            foreach ($companies as $company) {
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
            foreach ($companies as $company) {
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
