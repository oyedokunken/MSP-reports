<?php

namespace App\Integrations\ITGlue;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\AutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ITGlueIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected ITGlueClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new ITGlueClient($integration->credentials, $rateLimiter, $integration);
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
                'placeholder' => 'ITG.xxxxxxxx...',
                'help' => 'Your IT Glue API key from Account > Settings > API Keys',
            ],
            [
                'name' => 'base_url',
                'label' => 'Region',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'https://api.au.itglue.com' => 'Australia',
                    'https://api.itglue.com' => 'United States',
                    'https://api.eu.itglue.com' => 'Europe',
                ],
                'default' => 'https://api.au.itglue.com',
                'help' => 'Select your IT Glue region',
            ],
        ];
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    protected $mappedDuringSync = [];

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            $organizations = $this->client->getOrganizations();
            
            Log::info('ITGlue Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'organization_count' => count($organizations),
            ]);

            $totalOrganizations = count($organizations);
            collect($organizations)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed, $totalOrganizations) {
                foreach ($chunk as $organization) {
                    try {
                        $standardized = ITGlueMapper::mapCustomer($organization);

                        $this->storeCustomer($standardized);
                        $totalProcessed++;

                    } catch (\Exception $e) {
                        $orgId = $organization['id'] ?? 'unknown';
                        $errors[] = "Failed to process organization {$orgId}: {$e->getMessage()}";
                        Log::error('ITGlue Integration: Failed to process organization', [
                            'integration_id' => $this->integration->integration_id,
                            'organization_id' => $orgId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                Log::info('ITGlue Integration: Processed chunk', [
                    'integration_id' => $this->integration->integration_id,
                    'processed' => $totalProcessed,
                    'total' => $totalOrganizations,
                ]);
            });

            $this->syncAdditionalData($errors);

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('ITGlue Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        $additionalMapped = $this->processUnmappedCustomers();
        $mappedCustomers = array_merge($this->mappedDuringSync, $additionalMapped);

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
        
        Log::info('ITGlue Integration: Sync completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $mappedCount,
            'records_new' => $unmappedCount,
            'customers_mapped' => $mappedCustomers,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    protected function syncAdditionalData(array &$errors): void
    {
        try {
            $sslCerts = $this->client->getSslCertificates('next90days');
            foreach ($sslCerts as $cert) {
                try {
                    $data = ITGlueMapper::mapSslCertificate($cert);
                    // Convert IT Glue organization_id to our integration_customer_id
                    $data['integration_customer_id'] = $this->resolveIntegrationCustomerId($data['integration_customer_id']);
                    $this->storeHistoricalData($data);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process SSL certificate: {$e->getMessage()}";
                }
            }

            $domains = $this->client->getDomains('next90days');
            foreach ($domains as $domain) {
                try {
                    $data = ITGlueMapper::mapDomain($domain);
                    // Convert IT Glue organization_id to our integration_customer_id
                    $data['integration_customer_id'] = $this->resolveIntegrationCustomerId($data['integration_customer_id']);
                    $this->storeHistoricalData($data);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process domain: {$e->getMessage()}";
                }
            }

            // Fetch flexible assets by type (IT Glue requires type ID filter)
            $flexibleAssetTypes = $this->client->getFlexibleAssetTypes();
            $totalFlexibleAssets = 0;
            
            foreach ($flexibleAssetTypes as $assetType) {
                try {
                    $typeId = $assetType['id'] ?? null;
                    if (!$typeId) {
                        continue;
                    }
                    
                    $flexibleAssets = $this->client->getFlexibleAssets((int)$typeId);
                    foreach ($flexibleAssets as $asset) {
                        try {
                            $data = ITGlueMapper::mapFlexibleAsset($asset);
                            // Convert IT Glue organization_id to our integration_customer_id
                            $data['integration_customer_id'] = $this->resolveIntegrationCustomerId($data['integration_customer_id']);
                            $this->storeHistoricalData($data);
                            $totalFlexibleAssets++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to process flexible asset: {$e->getMessage()}";
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to fetch flexible assets for type {$typeId}: {$e->getMessage()}";
                }
            }

            Log::info('ITGlue Integration: Additional data synced', [
                'integration_id' => $this->integration->integration_id,
                'ssl_certs' => count($sslCerts),
                'domains' => count($domains),
                'flexible_asset_types' => count($flexibleAssetTypes ?? []),
                'flexible_assets' => $totalFlexibleAssets,
            ]);

        } catch (\Exception $e) {
            $errors[] = "Failed to sync additional data: {$e->getMessage()}";
            Log::error('ITGlue Integration: Failed to sync additional data', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processUnmappedCustomers(): array
    {
        $unmappedCustomers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->get();

        $mappedList = [];

        foreach ($unmappedCustomers as $integrationCustomer) {
            if ($this->integration->is_source_of_truth) {
                $mappedList[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'ITGlue');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }

        return $mappedList;
    }

    public function getCustomers(): array
    {
        $organizations = $this->client->getOrganizations();
        return array_map(fn($org) => ITGlueMapper::mapCustomer($org), $organizations);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // ITGlue doesn't have traditional historical data with date ranges
            // Instead, we sync current state of organizations, SSL certs, domains, and flexible assets
            // This captures a snapshot of the current state for historical tracking
            
            Log::info('ITGlue Integration: Starting historical data import', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // 1. Sync organization data
            $organizations = $this->client->getOrganizations();
            foreach ($organizations as $org) {
                try {
                    $standardized = ITGlueMapper::mapOrganizationData($org);
                    // Resolve organization ID to integration_customer_id
                    $standardized['integration_customer_id'] = $this->resolveIntegrationCustomerId($standardized['integration_customer_id']);
                    if ($standardized['integration_customer_id']) {
                        $data[] = $standardized;
                        $this->storeHistoricalData($standardized);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to process organization data: {$e->getMessage()}";
                }
            }

            // 2. Sync SSL certificates (expiring in next 90 days)
            try {
                $sslCerts = $this->client->getSslCertificates('next90days');
                foreach ($sslCerts as $cert) {
                    try {
                        $certData = ITGlueMapper::mapSslCertificate($cert);
                        $certData['integration_customer_id'] = $this->resolveIntegrationCustomerId($certData['integration_customer_id']);
                        if ($certData['integration_customer_id']) {
                            $data[] = $certData;
                            $this->storeHistoricalData($certData);
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process SSL certificate: {$e->getMessage()}";
                    }
                }
                Log::info('ITGlue Integration: SSL certificates synced', [
                    'integration_id' => $this->integration->integration_id,
                    'count' => count($sslCerts),
                ]);
            } catch (\Exception $e) {
                $errors[] = "Failed to fetch SSL certificates: {$e->getMessage()}";
            }

            // 3. Sync domains (expiring in next 90 days)
            try {
                $domains = $this->client->getDomains('next90days');
                foreach ($domains as $domain) {
                    try {
                        $domainData = ITGlueMapper::mapDomain($domain);
                        $domainData['integration_customer_id'] = $this->resolveIntegrationCustomerId($domainData['integration_customer_id']);
                        if ($domainData['integration_customer_id']) {
                            $data[] = $domainData;
                            $this->storeHistoricalData($domainData);
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process domain: {$e->getMessage()}";
                    }
                }
                Log::info('ITGlue Integration: Domains synced', [
                    'integration_id' => $this->integration->integration_id,
                    'count' => count($domains),
                ]);
            } catch (\Exception $e) {
                $errors[] = "Failed to fetch domains: {$e->getMessage()}";
            }

            // 4. Sync flexible assets
            try {
                $flexibleAssetTypes = $this->client->getFlexibleAssetTypes();
                $totalFlexibleAssets = 0;
                
                foreach ($flexibleAssetTypes as $assetType) {
                    try {
                        $typeId = $assetType['id'] ?? null;
                        if (!$typeId) {
                            continue;
                        }
                        
                        $flexibleAssets = $this->client->getFlexibleAssets((int)$typeId);
                        foreach ($flexibleAssets as $asset) {
                            try {
                                $assetData = ITGlueMapper::mapFlexibleAsset($asset);
                                $assetData['integration_customer_id'] = $this->resolveIntegrationCustomerId($assetData['integration_customer_id']);
                                if ($assetData['integration_customer_id']) {
                                    $data[] = $assetData;
                                    $this->storeHistoricalData($assetData);
                                    $totalFlexibleAssets++;
                                }
                            } catch (\Exception $e) {
                                $errors[] = "Failed to process flexible asset: {$e->getMessage()}";
                            }
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Failed to fetch flexible assets for type {$typeId}: {$e->getMessage()}";
                    }
                }
                
                Log::info('ITGlue Integration: Flexible assets synced', [
                    'integration_id' => $this->integration->integration_id,
                    'asset_types' => count($flexibleAssetTypes),
                    'total_assets' => $totalFlexibleAssets,
                ]);
            } catch (\Exception $e) {
                $errors[] = "Failed to fetch flexible assets: {$e->getMessage()}";
            }

            Log::info('ITGlue Integration: Historical data import completed', [
                'integration_id' => $this->integration->integration_id,
                'total_records' => count($data),
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            Log::error('ITGlue Integration: Historical data import failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        // ITGlue provides current state snapshots, not historical time-series data
        // Return current date to allow historical import (captures current state)
        return Carbon::now();
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'ITGlue',
            'description' => 'IT Glue Documentation and Asset Management',
            'data_types' => [
                'organization_info',
                'ssl_certificate',
                'domain',
                'flexible_asset',
            ],
            'supports_historical' => true,
            'historical_range_days' => 1, // Captures current state snapshot
        ];
    }

    public function disconnect(): void
    {
        // ITGlue uses API key authentication, no tokens to clear
        Log::info('ITGlue Integration: Disconnected', [
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

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    private function storeCustomer(array $customer): void
    {
        $integrationCustomer = IntegrationCustomer::updateOrCreate(
            [
                'integration_id' => $this->integration->integration_id,
                'external_id' => $customer['external_id'],
            ],
            [
                'name' => $customer['name'],
                'metadata' => $customer['metadata'],
                'active' => $customer['active'],
                'last_seen_at' => now(),
            ]
        );

        $integrationCustomer->refresh();

        if (!$integrationCustomer->customer_id) {
            if ($this->integration->is_source_of_truth) {
                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'ITGlue');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }
    }

    private function storeHistoricalData(array $data): void
    {
        // Only store if we have a valid integration customer ID
        if (empty($data['integration_customer_id'])) {
            return;
        }

        // For ITGlue snapshot data, use upsert logic to avoid duplicates
        // Identify unique records by: integration_customer_id + data_type + unique identifier from payload
        $uniqueId = $this->getUniqueIdentifier($data);
        
        if ($uniqueId) {
            // Try to find existing record
            $existing = IntegrationData::where('company_id', $this->integration->company_id)
                ->where('integration_id', $this->integration->integration_id)
                ->where('integration_customer_id', $data['integration_customer_id'])
                ->where('data_type', $data['data_type'])
                ->where('data_payload->certificate_id', $uniqueId)
                ->orWhere('data_payload->domain_id', $uniqueId)
                ->orWhere('data_payload->asset_id', $uniqueId)
                ->first();
            
            if ($existing) {
                // Update existing record with new data
                $existing->update([
                    'data_payload' => $data['payload'],
                    'data_timestamp' => $data['timestamp'],
                    'imported_at' => now(),
                ]);
                return;
            }
        }

        // Create new record if not found
        IntegrationData::create([
            'company_id' => $this->integration->company_id,
            'integration_id' => $this->integration->integration_id,
            'integration_customer_id' => $data['integration_customer_id'],
            'data_type' => $data['data_type'],
            'data_timestamp' => $data['timestamp'],
            'data_payload' => $data['payload'],
            'imported_at' => now(),
            'data_version' => 1,
        ]);
    }
    
    private function getUniqueIdentifier(array $data): ?string
    {
        $payload = $data['payload'] ?? [];
        
        // Return the appropriate unique ID based on data type
        return $payload['certificate_id'] 
            ?? $payload['domain_id'] 
            ?? $payload['asset_id'] 
            ?? null;
    }

    /**
     * Resolve IT Glue organization ID to our integration_customer_id
     * 
     * @param string|int|null $itGlueOrgId The IT Glue organization ID
     * @return int|null The integration_customer_id or null if not found
     */
    private function resolveIntegrationCustomerId($itGlueOrgId): ?int
    {
        if (empty($itGlueOrgId)) {
            return null;
        }

        // Look up the integration_customer_id by external_id (IT Glue organization ID)
        $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('external_id', (string)$itGlueOrgId)
            ->first();

        return $integrationCustomer?->integration_customer_id;
    }
}
