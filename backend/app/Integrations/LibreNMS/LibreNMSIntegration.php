<?php

namespace App\Integrations\LibreNMS;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\AutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LibreNMSIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected LibreNMSClient $client;
    protected CustomerMappingService $mappingService;

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

    protected $mappedDuringSync = [];

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            // Sync device groups as customers
            $groups = $this->client->getDeviceGroups();
            
            Log::info('LibreNMS Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'group_count' => count($groups),
            ]);

            foreach ($groups as $group) {
                try {
                    $standardized = LibreNMSMapper::mapDeviceGroup($group);
                    $this->storeCustomer($standardized);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $groupId = $group['id'] ?? 'unknown';
                    $errors[] = "Failed to process group {$groupId}: {$e->getMessage()}";
                    Log::error('LibreNMS Integration: Failed to process group', [
                        'integration_id' => $this->integration->integration_id,
                        'group_id' => $groupId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Sync devices and their availability
            $this->syncDevices($errors);

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('LibreNMS Integration: Sync failed', [
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
        
        Log::info('LibreNMS Integration: Sync completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $totalProcessed,
            'records_new' => $unmappedCount,
            'customers_mapped' => $mappedCustomers,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    protected function syncDevices(array &$errors): void
    {
        try {
            $devices = $this->client->getAllDevices();
            
            Log::info('LibreNMS Integration: Syncing devices', [
                'integration_id' => $this->integration->integration_id,
                'device_count' => count($devices),
            ]);

            foreach ($devices as $device) {
                try {
                    $deviceId = $device['device_id'];
                    
                    // Store device details (without groups to avoid per-device API calls)
                    // Groups and availability will be fetched on-demand during report generation
                    $deviceDetails = LibreNMSMapper::mapDeviceDetails($device, []);
                    $this->storeHistoricalData($deviceDetails);

                    // Store device uptime data
                    $uptimeData = LibreNMSMapper::mapDeviceUptime($device);
                    $this->storeHistoricalData($uptimeData);
                    
                    // Skip per-device availability API calls during sync to prevent timeout
                    // Availability metrics will be fetched on-demand during report generation
                    
                } catch (\Exception $e) {
                    $deviceId = $device['device_id'] ?? 'unknown';
                    $errors[] = "Failed to sync device {$deviceId}: {$e->getMessage()}";
                    Log::error('LibreNMS Integration: Failed to sync device', [
                        'integration_id' => $this->integration->integration_id,
                        'device_id' => $deviceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Failed to sync devices: {$e->getMessage()}";
            Log::error('LibreNMS Integration: Failed to sync devices', [
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
                $mappedList[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'LibreNMS');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }

        return $mappedList;
    }

    public function getCustomers(): array
    {
        $groups = $this->client->getDeviceGroups();
        return array_map(fn($group) => LibreNMSMapper::mapDeviceGroup($group), $groups);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // LibreNMS provides current availability metrics, not historical time-series
            // We sync current state as a snapshot
            
            $devices = $this->client->getAllDevices();
            
            foreach ($devices as $device) {
                try {
                    $deviceId = $device['device_id'];
                    
                    // Store uptime snapshot
                    $uptimeData = LibreNMSMapper::mapDeviceUptime($device);
                    $data[] = $uptimeData;
                    $this->storeHistoricalData($uptimeData);
                    
                    // Store availability snapshot
                    $availability = $this->client->getDeviceAvailability($deviceId);
                    if (!empty($availability)) {
                        $availabilityData = LibreNMSMapper::mapDeviceAvailability($deviceId, $availability);
                        $data[] = $availabilityData;
                        $this->storeHistoricalData($availabilityData);
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Failed to process device {$device['device_id']}: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
        }

        return [
            'data' => $data,
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

        if (!$integrationCustomer->customer_id) {
            if ($this->integration->is_source_of_truth) {
                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'LibreNMS');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }
    }

    private function storeHistoricalData(array $data): void
    {
        // For LibreNMS, we don't have integration_customer_id for devices
        // Store with company context only
        
        $matchAttributes = [
            'integration_id' => $this->integration->integration_id,
            'data_type' => $data['data_type'],
        ];

        // If payload has device_id, use it to match existing record
        if (isset($data['payload']['device_id'])) {
            $matchAttributes['data_payload->device_id'] = $data['payload']['device_id'];
        }

        IntegrationData::updateOrCreate(
            $matchAttributes,
            [
                'company_id' => $this->integration->company_id,
                'integration_customer_id' => $data['integration_customer_id'],
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $data['payload'],
                'imported_at' => now(),
                'data_version' => 1,
            ]
        );
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
