<?php

namespace App\Integrations\LibreNMS;

use App\Integrations\Contracts\IntegrationInterface;
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

class LibreNMSIntegration implements IntegrationInterface
{
    use HasHeartbeat;

    protected Integration $integration;
    protected LibreNMSClient $client;
    protected CustomerMappingService $mappingService;
    protected $progressCallback = null;
    protected static array $customerMapCache = [];
    protected static array $deviceGroupsMapCache = [];

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
        $rateLimiter = app(RateLimiter::class);
        $this->client = new LibreNMSClient($integration->credentials, $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_token',
                'label' => 'API Token',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your LibreNMS API token',
                'help' => 'Your LibreNMS API token from Settings > API',
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
            case 'groups':
                foreach ($items as $group) {
                    try {
                        $standardized = LibreNMSMapper::mapDeviceGroup($group);
                        $this->storeCustomer($standardized);
                        
                        // Heartbeat
                        $this->sendHeartbeat();
                    } catch (\Exception $e) {
                         $groupId = $group['id'] ?? 'unknown';
                         Log::error('LibreNMS Batch: Failed to process group', ['group_id' => $groupId, 'error' => $e->getMessage()]);
                    }
                }
                break;

            case 'devices':
                // Use optimized maps for device-group and customer association
                $customerMap = $this->getCustomerMap();
                $deviceGroupsMap = $this->getDeviceGroupsMap();
                
                // Fetch availability in parallel for this batch
                $deviceIds = array_column($items, 'device_id');
                $availabilityBatch = $this->client->getDeviceAvailabilityBatch($deviceIds);

                foreach ($items as $device) {
                    try {
                        $deviceId = (int)($device['device_id'] ?? 0);
                        if (!$deviceId) continue;
                        
                        // Get groups from our pre-loaded map
                        $groups = $deviceGroupsMap[$deviceId] ?? [];
                        
                        // Find associated customer
                        $integrationCustomerId = null;
                        foreach ($groups as $group) {
                            $externalId = 'group_' . $group['id'];
                            if (isset($customerMap[$externalId])) {
                                $integrationCustomerId = $customerMap[$externalId];
                                break;
                            }
                        }

                        // Store device details with customer association
                        $deviceDetails = LibreNMSMapper::mapDeviceDetails($device, $groups);
                        $deviceDetails['integration_customer_id'] = $integrationCustomerId;
                        $this->storeHistoricalData($deviceDetails);

                        // Store device uptime data with customer association
                        $uptimeData = LibreNMSMapper::mapDeviceUptime($device);
                        $uptimeData['integration_customer_id'] = $integrationCustomerId;
                        $this->storeHistoricalData($uptimeData);

                        // Store device availability data with customer association
                        $availability = $availabilityBatch[$deviceId] ?? [];
                        if (!empty($availability)) {
                            $mappedAvail = LibreNMSMapper::mapDeviceAvailability($deviceId, $availability);
                            $mappedAvail['integration_customer_id'] = $integrationCustomerId;
                            $this->storeHistoricalData($mappedAvail);
                        }
                        
                        // Heartbeat
                        $this->sendHeartbeat();
                    } catch (\Exception $e) {
                        $deviceId = $device['device_id'] ?? 'unknown';
                        Log::error('LibreNMS Batch: Failed to process device', ['device_id' => $deviceId, 'error' => $e->getMessage()]);
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
            $phase = $state['phase'] ?? 'groups';

            // PHASE 1: DEVICE GROUPS (Customers)
            if ($phase === 'groups') {
                $groups = $this->client->getDeviceGroups();
                Log::info('LibreNMS Integration: Syncing Groups', ['count' => count($groups)]);
                
                collect($groups)->chunk(50)->each(function ($chunk) {
                     \App\Jobs\ProcessIntegrationBatchJob::dispatchSync($this->integration, 'groups', $chunk->toArray(), $this->currentSyncLogId);
                });
                
                $totalProcessed += count($groups);
                $this->saveSyncState('devices', 0, 0); // Move to next phase
                $phase = 'devices';
            }

            // PHASE 2: DEVICES (Data)
            if ($phase === 'devices') {
                try {
                    $devices = $this->client->getAllDevices();
                    Log::info('LibreNMS Integration: Syncing Devices', ['count' => count($devices)]);
                    
                    collect($devices)->chunk(50)->each(function ($chunk) {
                        \App\Jobs\ProcessIntegrationBatchJob::dispatchSync($this->integration, 'devices', $chunk->toArray(), $this->currentSyncLogId);
                    });
                    $totalProcessed += count($devices);
                } catch (\Exception $e) {
                     $errors[] = "Device sync failed: " . $e->getMessage();
                }
                
                // Clear state
                $this->integration->update(['sync_state' => null]);
            }

        } catch (\Exception $e) {
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('LibreNMS Integration: Sync failed', ['error' => $e->getMessage()]);
        }

        // Auto-match Logic (via Cache Helper or inline)
        $this->updateStatsCache();

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



    protected function getCustomerMap(): array
    {
        $id = $this->integration->integration_id;
        if (!isset(self::$customerMapCache[$id])) {
            self::$customerMapCache[$id] = IntegrationCustomer::where('integration_id', $id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();
        }
        return self::$customerMapCache[$id];
    }

    protected function getDeviceGroupsMap(): array
    {
        $id = $this->integration->integration_id;
        if (!isset(self::$deviceGroupsMapCache[$id])) {
            $map = [];
            try {
                $allGroups = $this->client->getDeviceGroups();
                foreach ($allGroups as $group) {
                    try {
                        $devicesInGroup = $this->client->getDevicesInGroup($group['id']);
                        foreach ($devicesInGroup as $dev) {
                            $map[$dev['device_id']][] = $group;
                        }
                    } catch (\Exception $e) {
                        // Ignore single group failures
                    }
                }
            } catch (\Exception $e) {
                Log::error('LibreNMS: Failed to build device-groups map', ['error' => $e->getMessage()]);
            }
            self::$deviceGroupsMapCache[$id] = $map;
        }
        return self::$deviceGroupsMapCache[$id];
    }

    private function processUnmappedCustomers(): array
    {
        // Only dispatch if there are actually unmapped customers
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            \Log::info('LibreNMS: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
        return [];
    }

    public function getCustomers(): array
    {
        $groups = $this->client->getDeviceGroups();
        return array_map(fn($group) => LibreNMSMapper::mapDeviceGroup($group), $groups);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $totalProcessed = 0;
        $errors = [];

        try {
            Log::info('LibreNMS: Starting optimized historical import', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // 1. Get all devices
            $devices = $this->client->getAllDevices();
            $totalDevices = count($devices);
            
            if ($totalDevices === 0) {
                return ['success' => true, 'total_processed' => 0, 'errors' => []];
            }

            // 2. Pre-load customer mappings (memory optimization)
            $customerMap = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->pluck('integration_customer_id', 'external_id')
                ->toArray();

            // 3. Pre-load device-group memberships (optimization from previous step)
            $deviceGroupsMap = [];
            $allGroups = $this->client->getDeviceGroups();
            foreach ($allGroups as $group) {
                try {
                    $devicesInGroup = $this->client->getDevicesInGroup($group['id']);
                    foreach ($devicesInGroup as $dev) {
                        $deviceGroupsMap[$dev['device_id']][] = $group;
                    }
                } catch (\Exception $e) { /* ignore single group failures */ }
            }

            // 4. Process in chunks for parallel API fetching
            $deviceChunks = array_chunk($devices, 50);
            foreach ($deviceChunks as $chunkIndex => $chunk) {
                $batchRecords = [];
                $deviceIds = array_column($chunk, 'device_id');
                
                // Fetch availability in parallel for this chunk
                $availabilityBatch = $this->client->getDeviceAvailabilityBatch($deviceIds);

                foreach ($chunk as $device) {
                    try {
                        $deviceId = $device['device_id'];
                        $groups = $deviceGroupsMap[$deviceId] ?? [];
                        
                        // Find customer
                        $integrationCustomerId = null;
                        foreach ($groups as $group) {
                            $externalId = 'group_' . $group['id'];
                            if (isset($customerMap[$externalId])) {
                                $integrationCustomerId = $customerMap[$externalId];
                                break;
                            }
                        }

                        if (!$integrationCustomerId) continue;

                        // Map device details
                        $details = LibreNMSMapper::mapDeviceDetails($device, $groups);
                        $batchRecords[] = [
                            'data_type' => 'device_details',
                            'integration_customer_id' => $integrationCustomerId,
                            'timestamp' => $startDate,
                            'payload' => $details['payload'],
                        ];

                        // Map uptime
                        $uptime = LibreNMSMapper::mapDeviceUptime($device);
                        $batchRecords[] = [
                            'data_type' => 'device_uptime',
                            'integration_customer_id' => $integrationCustomerId,
                            'timestamp' => $startDate,
                            'payload' => $uptime['payload'],
                        ];

                        // Map availability (from parallel batch)
                        $availability = $availabilityBatch[$deviceId] ?? [];
                        if (!empty($availability)) {
                            $mappedAvail = LibreNMSMapper::mapDeviceAvailability($deviceId, $availability);
                            $batchRecords[] = [
                                'data_type' => 'device_availability',
                                'integration_customer_id' => $integrationCustomerId,
                                'timestamp' => $startDate,
                                'payload' => $mappedAvail['payload'],
                            ];
                        }

                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Device {$device['device_id']} failed: " . $e->getMessage();
                    }
                }

                // 5. Bulk Store the entire chunk
                if (!empty($batchRecords)) {
                    $this->bulkStoreHistoricalData($batchRecords);
                }

                // Progress notification
                $currentProgress = min(100, (($chunkIndex + 1) * 50 / $totalDevices) * 100);
                $this->notifyProgress(count($chunk), $currentProgress);
                $this->sendHeartbeat();
            }

        } catch (\Exception $e) {
            Log::error('LibreNMS Historical Import Error', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'success' => empty($errors),
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        // LibreNMS tracks availability up to 365 days
        return Carbon::now()->subDays(365);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'LibreNMS',
            'description' => 'Network Monitoring System - Device availability and uptime tracking',
            'data_types' => [
                'device_group',
                'device',
                'device_availability',
                'device_uptime',
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        // LibreNMS uses API token authentication, no tokens to clear
        Log::info('LibreNMS Integration: Disconnected', [
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
            'requests_per_minute' => 2000, // Massively increased for internal server
            'requests_per_hour' => 20000,
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

        // Batch job processes all unmapped customers at end of sync
        if (!$integrationCustomer->customer_id && $this->integration->is_source_of_truth) {
            $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'LibreNMS');
        }
    }

    private function bulkStoreHistoricalData(array $batch): void
    {
        if (empty($batch)) return;

        $now = now();
        $insertData = [];

        foreach ($batch as $data) {
            $payload = $data['payload'];
            $deviceId = $payload['device_id'] ?? null;
            $timestamp = Carbon::parse($data['timestamp']);
            $dateOnly = $timestamp->copy()->startOfDay();
            
            // Generate a unique external_id for indexing/upserting
            $externalId = $deviceId 
                ? "device_{$deviceId}_{$data['data_type']}_" . $dateOnly->format('Y-m-d')
                : null;

            $insertData[] = [
                'company_id'             => $this->integration->company_id,
                'integration_id'         => $this->integration->integration_id,
                'integration_customer_id'=> $data['integration_customer_id'] ?? null,
                'data_type'              => $data['data_type'],
                'external_id'            => $externalId,
                'data_timestamp'         => $data['timestamp'],
                'data_payload'           => json_encode($payload),
                'imported_at'            => $now,
                'data_version'           => 1,
                'created_at'             => $now,
                'updated_at'             => $now,
            ];
        }

        // Bulk UPSERT using indexed external_id
        IntegrationData::upsert(
            $insertData,
            ['integration_id', 'data_type', 'external_id', 'data_timestamp'],
            ['integration_customer_id', 'data_payload', 'imported_at', 'updated_at']
        );
    }

    private function storeHistoricalData(array $data): void
    {
        $this->bulkStoreHistoricalData([$data]);
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('librenms', 'LibreNMS');
        $builder->description('LibreNMS Device Groups and Devices');
        
        // Customers (Device Groups) table
        $builder->addSection('customers', 'Device Groups', 'table')
            ->description('Synced device groups from LibreNMS')
            ->icon('Server')
            ->dataPath('customers')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Group Name', 'sortable' => true, 'filterable' => true, 'width' => '300px'],
                ['key' => 'description', 'label' => 'Description', 'sortable' => true, 'filterable' => true],
                ['key' => 'type', 'label' => 'Type', 'sortable' => true, 'filterable' => true],
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
            ])
            ->sortable()
            ->filterable()
            ->paginated(25)
            ->end();
        
        // Overview metrics
        $builder->addSection('metrics', 'Overview', 'grid')
            ->icon('BarChart3')
            ->dataPath('metrics')
            ->gridColumns(3)
            ->addMetric('total_groups', 'Total Groups', 'total_groups', 'number', 'Users', 'blue')
            ->addMetric('total_devices', 'Total Devices', 'total_devices', 'number', 'Server', 'purple')
            ->addMetric('active_devices', 'Active Devices', 'active_devices', 'number', 'CheckCircle', 'green')
            ->end();
        
        return $builder->build();
    }

    public function getPreviewData(?int $integrationCustomerId = null): array
    {
        try {
            // Fetch from Database to match ITGlue behavior and avoid API rate limits
            $query = IntegrationCustomer::where('integration_id', $this->integration->integration_id);
            
            if ($integrationCustomerId) {
                $query->where('integration_customer_id', $integrationCustomerId);
            }
            
            $customers = $query->get();
            
            $mappedGroups = $customers->map(function($customer) {
                $metadata = $customer->metadata ?? [];
                return [
                    'id' => $customer->integration_customer_id,
                    'name' => $customer->name,
                    'description' => $metadata['description'] ?? '',
                    'type' => $metadata['type'] ?? '',
                    'active' => $customer->active ? 'Active' : 'Inactive',
                ];
            })->toArray();

            // Calculate metrics from stored data
            $totalGroups = $customers->count();
            
            // Count unique devices from historical data (approximate)
            $totalDevices = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', 'device_uptime')
                ->distinct('data_payload->device_id')
                ->count();
                
            $activeDevices = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', 'device_uptime')
                ->where('data_payload->status', 1)
                ->distinct('data_payload->device_id')
                ->count();

            return [
                'success' => true,
                'data' => [
                    'customers' => $mappedGroups,
                    'metrics' => [
                        'total_groups' => $totalGroups,
                        'total_devices' => $totalDevices,
                        'active_devices' => $activeDevices,
                    ]
                ],
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch preview data: ' . $e->getMessage(),
            ];
        }
    }
}
