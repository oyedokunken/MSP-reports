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

                        // Handle customer mapping
                        if (!$integrationCustomer->customer_id) {
                            if ($this->integration->is_source_of_truth) {
                                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'BitDefender');
                            } else {
                                AutoMatchCustomersJob::dispatch($integrationCustomer);
                            }
                        }

                        // Now fetch endpoints for this company
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
                                
                                IntegrationData::create([
                                    'company_id' => $this->integration->company_id,
                                    'integration_id' => $this->integration->integration_id,
                                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
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
            'records_synced' => $mappedCount,
            'records_new' => $unmappedCount,
            'customers_mapped' => $allMappedCustomers ?? [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function processUnmappedCustomers(): array
    {
        $unmappedCustomers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->get();

        $mappedList = [];

        foreach ($unmappedCustomers as $integrationCustomer) {
            if ($this->integration->is_source_of_truth) {
                $mappedList[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'BitDefender');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }

        return $mappedList;
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
        return [
            'data' => [],
            'errors' => ['BitDefender historical data not implemented yet'],
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return null;
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
            'supports_historical' => false,
            'historical_range_days' => 0,
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
