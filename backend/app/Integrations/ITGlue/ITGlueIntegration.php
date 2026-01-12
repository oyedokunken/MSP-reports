<?php

namespace App\Integrations\ITGlue;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Integrations\Concerns\HasHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ITGlueIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
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

    protected function saveSyncState(string $phase, int $offset, int $total): void
    {
        $this->integration->update([
            'sync_state' => json_encode([
                'phase' => $phase,
                'offset' => $offset,
                'total' => $total,
                'timestamp' => now()->timestamp,
            ])
        ]);
    }

    public function processBatch(string $type, array $items): void
    {
        switch ($type) {
            case 'organizations':
                foreach ($items as $organization) {
                    try {
                        $standardized = ITGlueMapper::mapCustomer($organization);
                        $this->storeCustomer($standardized);
                        
                        // Heartbeat
                        if (method_exists($this, 'sendHeartbeat')) {
                            $this->sendHeartbeat();
                        }
                    } catch (\Exception $e) {
                        $orgId = $organization['id'] ?? 'unknown';
                        Log::error('ITGlue Batch: Failed to process organization', ['org_id' => $orgId, 'error' => $e->getMessage()]);
                    }
                }
                break;

            case 'ssl_certs':
                foreach ($items as $cert) {
                    try {
                        $data = ITGlueMapper::mapSslCertificate($cert);
                        $data['integration_customer_id'] = $this->resolveIntegrationCustomerId($data['integration_customer_id']);
                        if ($data['integration_customer_id']) {
                             $this->storeHistoricalData($data);
                        }
                    } catch (\Exception $e) {
                        Log::error('ITGlue Batch: Failed to process SSL cert', ['error' => $e->getMessage()]);
                    }
                }
                break;

            case 'domains':
                 foreach ($items as $domain) {
                    try {
                        $data = ITGlueMapper::mapDomain($domain);
                        $data['integration_customer_id'] = $this->resolveIntegrationCustomerId($data['integration_customer_id']);
                        if ($data['integration_customer_id']) {
                             $this->storeHistoricalData($data);
                        }
                    } catch (\Exception $e) {
                        Log::error('ITGlue Batch: Failed to process domain', ['error' => $e->getMessage()]);
                    }
                }
                break;

            case 'flexible_assets':
                 foreach ($items as $asset) {
                    try {
                        $data = ITGlueMapper::mapFlexibleAsset($asset);
                        $data['integration_customer_id'] = $this->resolveIntegrationCustomerId($data['integration_customer_id']);
                        if ($data['integration_customer_id']) {
                             $this->storeHistoricalData($data);
                        }
                    } catch (\Exception $e) {
                        Log::error('ITGlue Batch: Failed to process flexible asset', ['error' => $e->getMessage()]);
                    }
                }
                break;
        }
    }

    protected $mappedDuringSync = [];

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            // Load state
            $state = json_decode($this->integration->sync_state, true);
            $phase = $state['phase'] ?? 'organizations';
            
            // PHASE 1: ORGANIZATIONS
            if ($phase === 'organizations') {
                $organizations = $this->client->getOrganizations();
                $totalOrgs = count($organizations);
                
                Log::info('ITGlue Integration: Syncing Organizations', ['count' => $totalOrgs]);
                
                collect($organizations)->chunk(50)->each(function ($chunk) use ($totalOrgs) {
                     \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'organizations', $chunk->toArray(), $this->currentSyncLogId);
                });
                
                $totalProcessed += $totalOrgs;
                $this->saveSyncState('ssl_certs', 0, 0); // Move to next phase
                $phase = 'ssl_certs';
            }

            // PHASE 2: SSL CERTS
            if ($phase === 'ssl_certs') {
                try {
                    $sslCerts = $this->client->getSslCertificates('next90days');
                    Log::info('ITGlue Integration: Syncing SSL Certs', ['count' => count($sslCerts)]);
                    
                    collect($sslCerts)->chunk(50)->each(function ($chunk) {
                        \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'ssl_certs', $chunk->toArray(), $this->currentSyncLogId);
                    });
                     $totalProcessed += count($sslCerts);
                } catch (\Exception $e) {
                    $errors[] = "SSL Cert sync failed: " . $e->getMessage();
                }
                
                $this->saveSyncState('domains', 0, 0);
                $phase = 'domains';
            }

            // PHASE 3: DOMAINS
            if ($phase === 'domains') {
                try {
                    $domains = $this->client->getDomains('next90days');
                    Log::info('ITGlue Integration: Syncing Domains', ['count' => count($domains)]);
                    
                    collect($domains)->chunk(50)->each(function ($chunk) {
                        \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'domains', $chunk->toArray(), $this->currentSyncLogId);
                    });
                    $totalProcessed += count($domains);
                } catch (\Exception $e) {
                     $errors[] = "Domain sync failed: " . $e->getMessage();
                }

                $this->saveSyncState('flexible_assets', 0, 0);
                $phase = 'flexible_assets';
            }

            // PHASE 4: FLEXIBLE ASSETS
            if ($phase === 'flexible_assets') {
                try {
                    $flexibleAssetTypes = $this->client->getFlexibleAssetTypes();
                     Log::info('ITGlue Integration: Syncing Flexible Assets', ['types_count' => count($flexibleAssetTypes)]);
                    
                    foreach ($flexibleAssetTypes as $assetType) {
                        try {
                            $typeId = $assetType['id'] ?? null;
                            if (!$typeId) continue;

                            $flexibleAssets = $this->client->getFlexibleAssets((int)$typeId);
                            
                            collect($flexibleAssets)->chunk(50)->each(function ($chunk) {
                                \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'flexible_assets', $chunk->toArray(), $this->currentSyncLogId);
                            });
                             $totalProcessed += count($flexibleAssets);
                             
                        } catch (\Exception $e) {
                            $errors[] = "Flexible Asset Type {$assetType['id']} failed: " . $e->getMessage();
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Flexible Asset sync failed: " . $e->getMessage();
                }
            }

            // COMPLETION
            $this->integration->update(['sync_state' => null]);

        } catch (\Exception $e) {
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('ITGlue Integration: Sync failed', ['error' => $e->getMessage()]);
        }
        
        // Auto-match Logic (via Cache Helper or inline)
        $this->updateStatsCache();

        // Dispatch unmapped matcher - using the existing helper method logic inline or keeping the method?
        // The original sync called processUnmappedCustomers(). We can keep calling it.
        $this->processUnmappedCustomers();

        return [
            'records_synced' => $totalProcessed,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }
    
    protected function updateStatsCache(): void 
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



    private function processUnmappedCustomers(): array
    {
        // Dispatch single batch job to process all unmapped customers
        // This reduces queue load from 397 individual jobs to 1 batch job
        BatchAutoMatchCustomersJob::dispatch($this->integration);

        return []; // Batch job handles mapping asynchronously
    }

    public function getCustomers(): array
    {
        $organizations = $this->client->getOrganizations();
        return array_map(fn($org) => ITGlueMapper::mapCustomer($org), $organizations);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // Historical data is now handled via processBatch dispatching during sync()
        return [
            'success' => true,
            'total_processed' => 0,
            'errors' => [],
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

    /**
     * Override the default display template to customize column names for ITGlue
     */
    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('itglue', 'ITGlue');
        $builder->description('IT Glue Organizations and Assets');
        
        // Customers table with custom "Licenses" column
        $builder->addSection('customers', 'Organizations', 'table')
            ->description('Synced organizations from IT Glue')
            ->icon('Users')
            ->dataPath('customers')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Customer Name', 'sortable' => true, 'filterable' => true, 'width' => '300px'],
                ['key' => 'licenses', 'label' => 'Licenses', 'sortable' => false, 'filterable' => false, 'format' => 'license_button'],
                [
                    'key' => 'active',
                    'label' => 'Status',
                    'sortable' => true,
                    'filterable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'Active' => 'success',
                        'Inactive' => 'error',
                    ],
                ],
                ['key' => 'last_seen', 'label' => 'Last Seen', 'sortable' => true, 'format' => 'datetime'],
            ])
            ->sortable()
            ->filterable()
            ->paginated(25)
            ->selectable()
            ->exportable()
            ->end();
        
        // Overview metrics
        $builder->addSection('metrics', 'Overview', 'grid')
            ->icon('BarChart3')
            ->dataPath('metrics')
            ->gridColumns(3)
            ->addMetric('total_customers', 'Total Organizations', 'total_customers', 'number', 'Users', 'blue')
            ->addMetric('active_customers', 'Active Organizations', 'active_customers', 'number', 'CheckCircle', 'green')
            ->addMetric('last_sync', 'Last Sync', 'last_sync', 'datetime', 'RefreshCw', 'gray')
            ->end();
        
        return $builder->build();
    }

    /**
     * Get licenses for a specific customer (called on-demand when modal opens)
     */
    public function getCustomerLicenses(int $integrationCustomerId): array
    {
        // Get licenses for this customer from IntegrationData
        $licenses = IntegrationData::where('company_id', $this->integration->company_id)
            ->where('integration_id', $this->integration->integration_id)
            ->where('integration_customer_id', $integrationCustomerId)
            ->where('data_type', 'flexible_asset')
            ->get()
            ->filter(function ($data) {
                // Filter for license-type flexible assets
                $typeName = $data->data_payload['type_name'] ?? '';
                return stripos($typeName, 'License') !== false;
            })
            ->map(function ($data) {
                $payload = $data->data_payload;
                $traits = $payload['traits'] ?? [];
                
                // Get license name
                $licenseName = $traits['license-name'] ?? $payload['name'] ?? 'Unknown License';
                if (is_array($licenseName)) {
                    $licenseName = reset($licenseName);
                }
                
                // Get counts
                $active = $this->getLicenseValue($traits, 'active', 'purchased', 'total-seats');
                $consumed = $this->getLicenseValue($traits, 'consumed', 'assigned', 'used-seats');
                $unused = $this->getLicenseValue($traits, 'unused', 'available');
                
                return [
                    'name' => $licenseName,
                    'active' => (int)$active,
                    'consumed' => (int)$consumed,
                    'unused' => (int)$unused,
                    'utilization' => $active > 0 ? round(($consumed / $active) * 100, 1) : 0,
                ];
            })
            ->values()
            ->toArray();
        
        return $licenses;
    }
    
    /**
     * Helper to get license value from traits (handles array or single values)
     */
    private function getLicenseValue(array $traits, ...$keys): float
    {
        foreach ($keys as $key) {
            if (!isset($traits[$key])) {
                continue;
            }
            
            $value = $traits[$key];
            if (is_array($value)) {
                return array_sum(array_map('floatval', $value));
            }
            return (float)$value;
        }
        
        return 0;
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

        // No individual dispatch needed - BatchAutoMatchCustomersJob processes
        // all unmapped customers at end of sync (in processUnmappedCustomers)
    }

    private function storeHistoricalData(array $data): void
    {
        // Only store if we have a valid integration customer ID
        if (empty($data['integration_customer_id'])) {
            return;
        }

        $payload = $data['payload'];
        $assetId = $payload['asset_id'] ?? $payload['id'] ?? null;
        
        if ($assetId) {
            // UPSERT: Use asset_id + data_type + date as unique key
            $dateOnly = Carbon::parse($data['timestamp'])->startOfDay();
            
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $data['data_type'])
                ->where('integration_customer_id', $data['integration_customer_id'])
                ->whereRaw("data_payload->>'id' = ?", [(string)$assetId])
                ->whereDate('data_timestamp', $dateOnly)
                ->first();

            if ($existing) {
                $existing->update([
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
            } else {
                IntegrationData::create([
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $data['integration_customer_id'],
                    'data_type' => $data['data_type'],
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                    'data_version' => 1,
                ]);
            }
        } else {
            // Fallback for data without unique ID
            IntegrationData::create([
                'company_id' => $this->integration->company_id,
                'integration_id' => $this->integration->integration_id,
                'integration_customer_id' => $data['integration_customer_id'],
                'data_type' => $data['data_type'],
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $payload,
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
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
