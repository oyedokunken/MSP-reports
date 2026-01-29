<?php

namespace App\Integrations\ITGlue;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\CustomerMappingService;
use App\Integrations\Concerns\HasHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use App\Integrations\Contracts\IntegrationDataType;

class ITGlueIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;

    protected Integration $integration;
    protected ITGlueClient $client;
    protected CustomerMappingService $mappingService;
    protected ?int $currentSyncLogId = null;
    private $lastBatchProcessedCount = 0;
    protected $progressCallback = null;

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    protected function notifyProgress(int $count, ?float $subProgress = null): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($count, $subProgress);
        }
    }

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new ITGlueClient(
            $integration->credentials,
            app(\App\Services\RateLimiter::class),
            $integration
        );
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
                'placeholder' => 'ITG.xxxxxxxx...',
                'help'        => 'Your IT Glue API key from Account > Settings > API Keys',
            ],
            [
                'name'    => 'base_url',
                'label'   => 'Region',
                'type'    => 'select',
                'required'=> true,
                'options' => [
                    'https://api.au.itglue.com' => 'Australia',
                    'https://api.itglue.com'    => 'United States',
                    'https://api.eu.itglue.com' => 'Europe',
                ],
                'default' => 'https://api.au.itglue.com',
                'help'    => 'Select your IT Glue region',
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
                'phase'     => $phase,
                'offset'    => $offset,
                'total'     => $total,
                'timestamp' => now()->timestamp,
            ]),
        ]);
    }

    protected function dispatchBatches(string $type, array $items, int &$counter): void
    {
        collect($items)->chunk(200)->each(function (Collection $chunk) use ($type, &$counter) {
            \App\Jobs\ProcessIntegrationBatchJob::dispatch(
                $this->integration,
                $type,
                $chunk->toArray(),
                $this->currentSyncLogId
            );
            $counter++;
        });
    }

    public function sync(): array
    {
        if (!$this->currentSyncLogId) {
            $syncLog = \App\Models\SyncLog::create([
                'integration_id' => $this->integration->integration_id,
                'started_at'     => now(),
                'status'         => 'running',
            ]);
            $this->currentSyncLogId = $syncLog->sync_id;
            Log::info('Created new sync log entry', ['sync_id' => $this->currentSyncLogId]);
        }

        $metrics = [
            'processed' => 0,
            'batches'   => 0,
            'errors'    => [],
        ];

        $state = json_decode($this->integration->sync_state ?? '{}', true);
        $phase = $state['phase'] ?? 'organizations';

        try {
            if ($phase === 'organizations') {
                $metrics['processed'] += $this->syncOrganizations();
                $this->saveSyncState('ssl_certs', 0, 0);
                $phase = 'ssl_certs';
            }

            if ($phase === 'ssl_certs') {
                $metrics['processed'] += $this->syncCertificates($metrics['batches']);
                $this->saveSyncState('domains', 0, 0);
                $phase = 'domains';
            }

            if ($phase === 'domains') {
                $metrics['processed'] += $this->syncDomains($metrics['batches']);
                $this->saveSyncState('flexible_assets', 0, 0);
                $phase = 'flexible_assets';
            }

            if ($phase === 'flexible_assets') {
                $metrics['processed'] += $this->syncFlexibleAssets($metrics['batches']);
            }

            $this->integration->update(['sync_state' => null]);
        } catch (\Throwable $e) {
            $metrics['errors'][] = $e->getMessage();
            Log::critical('ITGlue sync failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        } finally {
            $this->updateStatsCache();
            $this->processUnmappedCustomers();
        }

        return [
            'records_synced'     => $metrics['processed'],
            'records_new'        => 0,
            'customers_mapped'   => [],
            'records_failed'     => count($metrics['errors']),
            'errors'             => $metrics['errors'],
            'batches_dispatched' => $metrics['batches'],
        ];
    }

    protected function syncOrganizations(): int
    {
        $organizations = $this->client->getOrganizations();
        $count = count($organizations);

        Log::info('ITGlue: Syncing organizations', ['count' => $count]);

        $batch = [];
        foreach ($organizations as $org) {
            try {
                $mapped = ITGlueMapper::mapCustomer($org);
                $batch[] = $mapped;
            } catch (\Throwable $e) {
                Log::warning('Failed to map organization', [
                    'org_id' => $org['id'] ?? 'unknown',
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        Log::info('ITGlue: Storing organizations', ['batch_size' => count($batch)]);
        $this->bulkStoreCustomers($batch);
        $this->notifyProgress($count); // Notify progress for organizations
        $this->sendHeartbeat(); // Send heartbeat once after batch processing

        return $count;
    }

   protected function syncCertificates(int &$batches): int
    {
        $items = $this->client->getSslCertificates(['expiration' => 'next90days']);
        $totalItems = count($items);

        if ($totalItems === 0) {
            return 0;
        }

        $batchCount = (int) ceil($totalItems / 200);

        Log::info('ITGlue: Dispatched SSL certificate batches', [
            'sync_id'     => $this->currentSyncLogId,
            'batch_count' => $batchCount,
            'total_items' => $totalItems,
        ]);

        $this->dispatchBatches('ssl_certs', $items, $batches);

        return $totalItems;
    }

    protected function syncDomains(int &$batches): int
    {
        $items = $this->client->getDomains(['expiration' => 'next90days']);
        $totalItems = count($items);

        if ($totalItems === 0) {
            return 0;
        }

        $batchCount = (int) ceil($totalItems / 200);

        Log::info('ITGlue: Dispatched domain batches', [
            'sync_id'     => $this->currentSyncLogId,
            'batch_count' => $batchCount,
            'total_items' => $totalItems,
        ]);

        $this->dispatchBatches('domains', $items, $batches);

        return $totalItems;
    }

    protected function syncFlexibleAssets(int &$batches): int
    {
        $typeIds = $this->client->findLicenseAssetTypeIds();

        if (empty($typeIds)) {
            Log::warning('ITGlue: No license asset types found - skipping flexible assets');
            return 0;
        }

        Log::info('ITGlue: Syncing flexible assets (bulk)', ['type_count' => count($typeIds)]);

        $redis = \Illuminate\Support\Facades\Redis::connection();
        $pendingKey = "sync_log:{$this->currentSyncLogId}:pending";
        $failedKey  = "sync_log:{$this->currentSyncLogId}:failed";

        $totalBatches = 0;
        $assetGroups = [];

        foreach ($typeIds as $typeId) {
            try {
                $assets = $this->client->getFlexibleAssets($typeId, null);
                $count  = count($assets);

                if ($count === 0) {
                    continue;
                }

                $chunkCount = (int) ceil($count / 200);
                $totalBatches += $chunkCount;
                $assetGroups[$typeId] = $assets;
            } catch (\Throwable $e) {
                Log::error('Failed to fetch flexible assets for type', [
                    'type_id' => $typeId,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if ($totalBatches === 0) {
            return 0;
        }

        Log::info('ITGlue: Dispatched flexible asset batches', [
            'sync_id'       => $this->currentSyncLogId,
            'total_batches' => $totalBatches,
        ]);

        $totalProcessed = 0;

        foreach ($assetGroups as $typeId => $assets) {
            $this->dispatchBatches('flexible_assets', $assets, $batches);
            $totalProcessed += count($assets);
        }

        return $totalProcessed;
    }

    protected function updateStatsCache(): void
    {
        $key = "integration_{$this->integration->integration_id}_customer_counts";

        Cache::forget($key);

        $mapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();

        $unmapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Cache::put($key, [
            'mapped'    => $mapped,
            'unmapped'  => $unmapped,
            'last_sync' => now(),
        ], now()->addMinutes(10));
    }

    private function processUnmappedCustomers(): void
    {
        $count = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        if ($count > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            Log::info('Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped'       => $count,
            ]);
        }
    }

    private function bulkStoreCustomers(array $customers): void
    {
        if (empty($customers)) {
            return;
        }

        $now = now();
        $storedCount = 0;

        foreach ($customers as $customer) {
            try {
                IntegrationCustomer::withoutGlobalScopes()->updateOrCreate(
                    [
                        'integration_id' => $this->integration->integration_id,
                        'external_id'    => (string)$customer['external_id'],
                    ],
                    [
                        'name'         => $customer['name'],
                        'metadata'     => $customer['metadata'],
                        'active'       => $customer['active'],
                        'last_seen_at' => $now,
                    ]
                );
                $storedCount++;
            } catch (\Exception $e) {
                Log::error('ITGlue: Failed to store customer', [
                    'external_id' => $customer['external_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info('ITGlue: Customers storage completed', ['stored' => $storedCount, 'total' => count($customers)]);
        $this->lastBatchProcessedCount = $storedCount;
    }


    public function processBatch(string $type, array $items): void
    {
        $this->lastBatchProcessedCount = 0;

        $customerMap = null;
        if (in_array($type, [
            IntegrationDataType::SSL_CERTIFICATE,
            IntegrationDataType::DOMAINS,
            IntegrationDataType::FLEXIBLE_ASSETS
        ])) {
            $customerMap = IntegrationCustomer::withoutGlobalScopes()->where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
        }

        switch ($type) {
            case 'organizations':
            case IntegrationDataType::ORGANIZATION_INFO:
                $batch = [];
                foreach ($items as $organization) {
                    try {
                        $batch[] = ITGlueMapper::mapCustomer($organization);
                    } catch (\Exception $e) {
                        Log::error('ITGlue Batch: Failed to process organization', [
                            'org_id' => $organization['id'] ?? 'unknown',
                            'error'  => $e->getMessage(),
                        ]);
                    }
                }
                $this->bulkStoreCustomers($batch);
                $this->sendHeartbeat();
                break;

            case IntegrationDataType::SSL_CERTIFICATE:
            case 'ssl_certs': // Legacy support
            case IntegrationDataType::DOMAINS:
            case 'domains': // Legacy support
            case IntegrationDataType::FLEXIBLE_ASSETS:
            case 'flexible_assets': // Legacy support
                $batch = [];
                foreach ($items as $item) {
                    try {
                        $data = null;
                        if ($type === IntegrationDataType::SSL_CERTIFICATE || $type === 'ssl_certs') {
                            $data = ITGlueMapper::mapSslCertificate($item);
                        } elseif ($type === IntegrationDataType::DOMAINS || $type === 'domains') { // @phpstan-ignore-line
                            $data = ITGlueMapper::mapDomain($item);
                        } elseif ($type === IntegrationDataType::FLEXIBLE_ASSETS || $type === 'flexible_assets') { // @phpstan-ignore-line
                            $data = ITGlueMapper::mapFlexibleAsset($item);
                        }

                        if (!$data) {
                            continue;
                        }

                        $data['integration_customer_id'] = $customerMap[$data['integration_customer_id'] ?? ''] ?? null;

                        if ($data['integration_customer_id']) {
                            $batch[] = $data;
                        }
                    } catch (\Exception $e) {
                        Log::error("ITGlue Batch: Failed to process {$type} item", ['error' => $e->getMessage()]);
                    }
                }

                // Determine data type for storage
                $dataType = match($type) {
                    'ssl_certs' => IntegrationDataType::SSL_CERTIFICATE,
                    'domains' => IntegrationDataType::DOMAINS,
                    'flexible_assets' => IntegrationDataType::FLEXIBLE_ASSETS,
                    default => $type
                };
                
                $this->bulkStoreHistoricalData($dataType, $batch);
                break;
        }
    }

    private function bulkStoreHistoricalData(string $dataType, array $batch): void
    {
        if (empty($batch)) {
            $this->lastBatchProcessedCount = 0;
            return;
        }

        $itemsWithExtId = array_map(function($item) {
            $extId = $item['payload']['id'] ?? 
                     $item['payload']['asset_id'] ?? 
                     $item['payload']['certificate_id'] ?? 
                     $item['payload']['domain_id'] ?? 
                     $item['payload']['organization_id'] ?? 
                     $item['payload']['domain_uuid'] ?? null;
            $item['external_id'] = $extId ? (string)$extId : null;
            return $item;
        }, $batch);

        $externalIds = array_filter(array_column($itemsWithExtId, 'external_id'));

        $now = now();

        \Illuminate\Support\Facades\DB::transaction(function () use ($dataType, $itemsWithExtId, $externalIds, $now) {
            if (!empty($externalIds)) {
                \Illuminate\Support\Facades\DB::table('integration_data')
                    ->where('integration_id', $this->integration->integration_id)
                    ->where('data_type', $dataType)
                    ->whereIn('external_id', $externalIds)
                    ->delete();
            }

            $insertData = [];
            foreach ($itemsWithExtId as $item) {
                $validator = app(\App\Services\IntegrationDataValidator::class);
                $payload = $validator->validate($dataType, $item['payload']);

                $insertData[] = [
                    'company_id'             => $this->integration->company_id,
                    'integration_id'         => $this->integration->integration_id,
                    'integration_customer_id'=> $item['integration_customer_id'],
                    'external_id'            => $item['external_id'],
                    'data_type'              => $dataType,
                    'data_timestamp'         => $item['timestamp'],
                    'data_payload'           => json_encode($payload),
                    'imported_at'            => $now,
                    'data_version'           => 1,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];
            }

            \Illuminate\Support\Facades\DB::table('integration_data')->insert($insertData);
        });

        $this->lastBatchProcessedCount = count($batch);
    }


    public function getLastBatchProcessedCount(): int
    {
        return $this->lastBatchProcessedCount;
    }

    public function getCustomers(): array
    {
        $organizations = $this->client->getOrganizations();
        return array_map(fn($org) => ITGlueMapper::mapCustomer($org), $organizations);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $totalProcessed = 0;
        $errors = [];

        try {
            Log::info('ITGlue: Starting historical import', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // 1. Organizations (Always sync these first as they are needed for mapping)
            $organizations = $this->client->getOrganizations(true);
            Log::info('ITGlue Historical: Fetched organizations', ['count' => count($organizations)]);
            
            $orgBatch = [];
            foreach ($organizations as $org) {
                try {
                    $orgBatch[] = ITGlueMapper::mapCustomer($org);
                } catch (\Exception $e) {
                    Log::error('ITGlue Historical: Failed to map organization', ['id' => $org['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                }
            }
            
            if (!empty($orgBatch)) {
                $this->bulkStoreCustomers($orgBatch);
                // We don't add organizations to totalProcessed but we do notify progress
                // Organizations = 25% of this chunk
                $this->notifyProgress(count($orgBatch), 25.0);
            }
            $this->sendHeartbeat();
            sleep(2); // Added delay for visual progress bar demo

            // Prepare customer map for subsequent data types
            $customerMap = IntegrationCustomer::withoutGlobalScopes()->where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
                
            Log::info('ITGlue Historical: Customer map size', ['size' => count($customerMap)]);

            // Range filter for updated_at
            $dateFilter = $startDate->toDateString() . '..' . $endDate->toDateString();
            
            // 2. SSL Certificates
            Log::info('ITGlue Historical: Fetching SSL certs', ['filter' => $dateFilter]);
            $certificates = $this->client->getSslCertificates(['updated_at' => $dateFilter]);
            Log::info('ITGlue Historical: Fetched SSL certs', ['count' => count($certificates)]);
            
            $certBatch = [];
            foreach ($certificates as $cert) {
                try {
                    $item = ITGlueMapper::mapSslCertificate($cert);
                    $extOrgId = (string)($item['integration_customer_id'] ?? '');
                    $item['integration_customer_id'] = $customerMap[$extOrgId] ?? null;
                    
                    if ($item['integration_customer_id']) {
                        $item['timestamp'] = $startDate;
                        $certBatch[] = $item;
                    }
                } catch (\Exception $e) {
                    Log::error('ITGlue Historical: Failed to map cert', ['error' => $e->getMessage()]);
                }
            }
            if (!empty($certBatch)) {
                $this->bulkStoreHistoricalData(IntegrationDataType::SSL_CERTIFICATE, $certBatch);
                $totalProcessed += count($certBatch);
                // SSL = 50% of this chunk
                $this->notifyProgress(count($certBatch), 50.0);
            } else {
                $this->notifyProgress(0, 50.0);
            }
            $this->sendHeartbeat();
            sleep(2); // Added delay for visual progress bar demo

            // 3. Domains
            Log::info('ITGlue Historical: Fetching domains', ['filter' => $dateFilter]);
            $domains = $this->client->getDomains(['updated_at' => $dateFilter]);
            Log::info('ITGlue Historical: Fetched domains', ['count' => count($domains)]);
            
            $domainBatch = [];
            foreach ($domains as $domain) {
                try {
                    $item = ITGlueMapper::mapDomain($domain);
                    $extOrgId = (string)($item['integration_customer_id'] ?? '');
                    $item['integration_customer_id'] = $customerMap[$extOrgId] ?? null;
                    
                    if ($item['integration_customer_id']) {
                        $item['timestamp'] = $startDate;
                        $domainBatch[] = $item;
                    }
                } catch (\Exception $e) {
                    Log::error('ITGlue Historical: Failed to map domain', ['error' => $e->getMessage()]);
                }
            }
            if (!empty($domainBatch)) {
                $this->bulkStoreHistoricalData(IntegrationDataType::DOMAINS, $domainBatch);
                $totalProcessed += count($domainBatch);
                // Domains = 75% of this chunk
                $this->notifyProgress(count($domainBatch), 75.0);
            } else {
                $this->notifyProgress(0, 75.0);
            }
            $this->sendHeartbeat();
            sleep(2); // Added delay for visual progress bar demo

            // 4. Flexible Assets (Licenses)
            $typeIds = $this->client->findLicenseAssetTypeIds();
            Log::info('ITGlue Historical: Flexible asset types', ['types' => $typeIds]);
            
            foreach ($typeIds as $index => $typeId) {
                $assets = $this->client->getFlexibleAssets($typeId, null, true);
                $assetBatch = [];
                foreach ($assets as $asset) {
                    try {
                        $updatedAtString = $asset['attributes']['updated-at'] ?? null;
                        if (!$updatedAtString) continue;
                        
                        $updatedAt = Carbon::parse($updatedAtString);
                        
                        if ($updatedAt->between($startDate, $endDate)) {
                            $item = ITGlueMapper::mapFlexibleAsset($asset);
                            $extOrgId = (string)($item['integration_customer_id'] ?? '');
                            $item['integration_customer_id'] = $customerMap[$extOrgId] ?? null;
                            
                            if ($item['integration_customer_id']) {
                                $item['timestamp'] = $startDate;
                                $assetBatch[] = $item;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('ITGlue Historical: Failed to map asset', ['error' => $e->getMessage()]);
                    }
                }
                if (!empty($assetBatch)) {
                    $this->bulkStoreHistoricalData(IntegrationDataType::FLEXIBLE_ASSETS, $assetBatch);
                    $totalProcessed += count($assetBatch);
                }
                
                // Calculate incremental progress for asset types (from 75% to 100%)
                $assetProgress = 75.0 + (($index + 1) / count($typeIds)) * 25.0;
                $this->notifyProgress(count($assetBatch), $assetProgress);
                $this->sendHeartbeat();
                sleep(1); 
            }

        } catch (\Exception $e) {
            Log::error('ITGlue Historical Import Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; 
        }

        return [
            'success' => true,
            'total_processed' => 0, // Zero because we use notifyProgress for everything
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return Carbon::now();
    }

    public function getMetadata(): array
    {
        return [
            'name'                => 'ITGlue',
            'description'         => 'IT Glue Documentation and Asset Management',
            'data_types'          => [
                IntegrationDataType::ORGANIZATION_INFO,
                IntegrationDataType::SSL_CERTIFICATE,
                IntegrationDataType::DOMAINS,
                IntegrationDataType::FLEXIBLE_ASSETS,
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
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

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('itglue', 'ITGlue');
        $builder->description('IT Glue Organizations and Assets');

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

    public function getCustomerLicenses(int $integrationCustomerId): array
    {
        $licenses = IntegrationData::where('company_id', $this->integration->company_id)
            ->where('integration_id', $this->integration->integration_id)
            ->where('integration_customer_id', $integrationCustomerId)
            ->where('data_type', 'flexible_assets')
            ->get()
            ->filter(function ($data) {
                $typeName = $data->data_payload['type_name'] ?? '';
                return stripos($typeName, 'License') !== false;
            })
            ->map(function ($data) {
                $payload = $data->data_payload;
                $traits = $payload['traits'] ?? [];

                $licenseName = $traits['license-name'] ?? $payload['name'] ?? 'Unknown License';
                if (is_array($licenseName)) {
                    $licenseName = reset($licenseName);
                }

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
}

