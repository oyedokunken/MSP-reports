<?php

namespace App\Integrations\Datto;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\DisplayTemplate\TemplateBuilder;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Jobs\AutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DattoIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected DattoClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new DattoClient($integration->credentials, $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'rmm_region',
                'label' => 'RMM Region',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'https://syrah-api.centrastage.net' => 'Syrah (Default)',
                    'https://pinotage-api.centrastage.net' => 'Pinotage',
                    'https://concord-api.centrastage.net' => 'Concord',
                    'https://merlot-api.centrastage.net' => 'Merlot',
                ],
                'default' => 'https://syrah-api.centrastage.net',
                'help' => 'Select your Datto RMM API region',
            ],
            [
                'name' => 'rmm_api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your Datto RMM API Key',
                'help' => 'Found in Datto RMM under Setup > API Credentials',
            ],
            [
                'name' => 'rmm_api_secret',
                'label' => 'API Secret',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your Datto RMM API Secret',
                'help' => 'Keep this secret secure',
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
            $dattoCustomers = $this->client->getCustomers();
            
            \Log::info('Datto Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'customer_count' => count($dattoCustomers),
            ]);

            $totalCustomers = count($dattoCustomers);
            collect($dattoCustomers)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed, $totalCustomers) {
                foreach ($chunk as $customer) {
                    try {
                        $standardized = DattoMapper::mapCustomer($customer);

                        $this->storeCustomer($standardized);
                        $totalProcessed++;

                    } catch (\Exception $e) {
                        $customerId = $customer['id'] ?? $customer['uid'] ?? $customer['organizationId'] ?? 'unknown';
                        $errors[] = "Failed to process customer {$customerId}: {$e->getMessage()}";
                        \Log::error('Datto Integration: Failed to process customer', [
                            'integration_id' => $this->integration->integration_id,
                            'customer_id' => $customerId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                \Log::info('Datto Integration: Processed chunk', [
                    'integration_id' => $this->integration->integration_id,
                    'processed' => $totalProcessed,
                    'total' => $totalCustomers,
                ]);
            });

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            \Log::error('Datto Integration: Sync failed', [
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
        
        \Log::info('Datto Integration: Sync completed', [
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

    private function processUnmappedCustomers(): array
    {
        $unmappedCustomers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->get();

        $mappedList = [];

        foreach ($unmappedCustomers as $integrationCustomer) {
            if ($this->integration->is_source_of_truth) {
                $mappedList[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Datto');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }

        return $mappedList;
    }

    public function getCustomers(): array
    {
        $dattoCustomers = $this->client->getCustomers();
        return array_map(fn($customer) => DattoMapper::mapCustomer($customer), $dattoCustomers);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            
            $backupData = $this->client->getBackupHistory($startDate, $endDate);

            foreach ($backupData as $record) {
                try {
                    $standardized = DattoMapper::mapBackupData($record);
                    $data[] = $standardized;

                    
                    $this->storeHistoricalData($standardized);

                } catch (\Exception $e) {
                    $errors[] = "Failed to process historical record: {$e->getMessage()}";
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
        
        return now()->subDays(90);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'Datto',
            'description' => 'Datto RMM and Backup Platform',
            'data_types' => [
                'backup_status',
                'device_status',
                'uptime_metrics',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        $cacheKey = 'datto_rmm_token_' . $this->integration->integration_id;
        Cache::forget($cacheKey);
        
        Log::info('Datto Integration: Disconnected and cleared tokens', [
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
        // Validate required fields
        if (empty($customer['external_id']) || empty($customer['name'])) {
            Log::warning('Datto: Skipping customer with missing required fields', [
                'customer' => $customer
            ]);
            return;
        }

        DB::transaction(function () use ($customer) {
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
                    $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Datto');
                } else {
                    AutoMatchCustomersJob::dispatch($integrationCustomer);
                }
            }
        });
    }

    private function storeHistoricalData(array $data): void
    {
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

    public function getDisplayTemplate(): array
    {
        $builder = new TemplateBuilder('datto', 'Datto RMM & Backup');
        
        $builder->description('Datto RMM and Backup Platform data visualization');

        // Devices section
        $builder->addSection('devices', 'Devices', 'table')
            ->description('All managed devices from Datto RMM')
            ->icon('Monitor')
            ->order(1)
            ->dataPath('devices')
            ->tableColumns([
                [
                    'key' => 'hostname',
                    'label' => 'Hostname',
                    'sortable' => true,
                    'filterable' => true,
                    'width' => '200px',
                ],
                [
                    'key' => 'type',
                    'label' => 'Type',
                    'sortable' => true,
                    'filterable' => true,
                    'format' => 'badge',
                    'width' => '120px',
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'sortable' => true,
                    'format' => 'status',
                    'width' => '100px',
                    'colorMap' => [
                        'online' => 'success',
                        'offline' => 'error',
                        'warning' => 'warning',
                    ],
                ],
                [
                    'key' => 'os',
                    'label' => 'Operating System',
                    'sortable' => true,
                    'filterable' => true,
                ],
                [
                    'key' => 'ip_address',
                    'label' => 'IP Address',
                    'width' => '140px',
                ],
                [
                    'key' => 'last_seen',
                    'label' => 'Last Seen',
                    'sortable' => true,
                    'format' => 'datetime',
                    'width' => '180px',
                ],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->selectable()
            ->exportable()
            ->emptyMessage('No devices found for this customer')
            ->end();

        // Backup Status section
        $builder->addSection('backups', 'Backup Status', 'table')
            ->description('Recent backup job status')
            ->icon('HardDrive')
            ->order(2)
            ->dataPath('backups')
            ->tableColumns([
                [
                    'key' => 'device_name',
                    'label' => 'Device',
                    'sortable' => true,
                    'filterable' => true,
                ],
                [
                    'key' => 'backup_type',
                    'label' => 'Type',
                    'sortable' => true,
                    'format' => 'badge',
                    'width' => '120px',
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'sortable' => true,
                    'format' => 'status',
                    'width' => '120px',
                    'colorMap' => [
                        'success' => 'success',
                        'failed' => 'error',
                        'warning' => 'warning',
                        'running' => 'info',
                    ],
                ],
                [
                    'key' => 'last_backup',
                    'label' => 'Last Backup',
                    'sortable' => true,
                    'format' => 'datetime',
                    'width' => '180px',
                ],
                [
                    'key' => 'size',
                    'label' => 'Size',
                    'sortable' => true,
                    'format' => 'filesize',
                    'width' => '120px',
                ],
                [
                    'key' => 'duration',
                    'label' => 'Duration',
                    'format' => 'duration',
                    'width' => '100px',
                ],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->selectable()
            ->exportable()
            ->emptyMessage('No backup data available')
            ->end();

        // Metrics section
        $builder->addSection('metrics', 'Key Metrics', 'grid')
            ->description('Overview metrics')
            ->icon('BarChart3')
            ->order(3)
            ->dataPath('metrics')
            ->config([
                'columns' => 4,
                'gap' => 4,
                'metrics' => [
                    [
                        'id' => 'total_devices',
                        'label' => 'Total Devices',
                        'valuePath' => 'total_devices',
                        'format' => 'number',
                        'icon' => 'Monitor',
                        'color' => 'blue',
                    ],
                    [
                        'id' => 'online_devices',
                        'label' => 'Online Devices',
                        'valuePath' => 'online_devices',
                        'format' => 'number',
                        'icon' => 'CheckCircle',
                        'color' => 'green',
                    ],
                    [
                        'id' => 'backup_success_rate',
                        'label' => 'Backup Success Rate',
                        'valuePath' => 'backup_success_rate',
                        'format' => 'percentage',
                        'icon' => 'HardDrive',
                        'color' => 'purple',
                    ],
                    [
                        'id' => 'alerts',
                        'label' => 'Active Alerts',
                        'valuePath' => 'active_alerts',
                        'format' => 'number',
                        'icon' => 'AlertTriangle',
                        'color' => 'orange',
                    ],
                ],
            ])
            ->emptyMessage('No metrics available')
            ->end();

        // Alerts section
        $builder->addSection('alerts', 'Active Alerts', 'list')
            ->description('Current system alerts and warnings')
            ->icon('AlertTriangle')
            ->order(4)
            ->dataPath('alerts')
            ->collapsible(true, false)
            ->config([
                'itemTemplate' => [
                    'title' => '{{message}}',
                    'subtitle' => '{{device_name}} - {{timestamp}}',
                    'icon' => '{{severity_icon}}',
                    'iconColor' => '{{severity_color}}',
                    'badge' => '{{severity}}',
                ],
                'sortBy' => 'timestamp',
                'sortOrder' => 'desc',
            ])
            ->emptyMessage('No active alerts')
            ->end();

        return $builder->build();
    }

    public function getPreviewData(?int $integrationCustomerId = null): array
    {
        try {
            // If specific customer requested, filter data
            $customer = null;
            if ($integrationCustomerId) {
                $customer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                    ->where('id', $integrationCustomerId)
                    ->first();
                
                if (!$customer) {
                    return [
                        'success' => false,
                        'error' => 'Customer not found',
                    ];
                }
            }

            $devicesData = [];
            $backupsData = [];
            $alertsData = [];

            // Fetch RMM data if customer specified
            if ($customer && $customer->external_id) {
                try {
                    // Get devices for this site
                    $devices = $this->client->getRmmDevices($customer->external_id);
                    $devicesData = collect($devices)->map(function ($device) {
                        return [
                            'id' => $device['uid'] ?? $device['id'] ?? null,
                            'hostname' => $device['hostname'] ?? $device['description'] ?? 'Unknown',
                            'type' => $device['deviceType']['category'] ?? 'Unknown',
                            'status' => ($device['online'] ?? false) ? 'online' : 'offline',
                            'os' => $device['operatingSystem'] ?? 'Unknown',
                            'ip_address' => $device['intIpAddress'] ?? 'N/A',
                            'last_seen' => $device['lastSeen'] ?? now()->toIso8601String(),
                        ];
                    })->toArray();

                    // Get alerts for this site
                    $alerts = $this->client->getRmmAlerts($customer->external_id);
                    $alertsData = collect($alerts)->map(function ($alert) {
                        $severity = strtolower($alert['priority'] ?? 'low');
                        return [
                            'id' => $alert['uid'] ?? null,
                            'message' => $alert['alertMessage'] ?? 'Unknown alert',
                            'device_name' => $alert['hostname'] ?? 'System',
                            'timestamp' => $alert['alertDate'] ?? now()->toIso8601String(),
                            'severity' => $severity,
                            'severity_icon' => $this->getSeverityIcon($severity),
                            'severity_color' => $this->getSeverityColor($severity),
                        ];
                    })->toArray();
                } catch (\Exception $e) {
                    Log::warning('Datto Integration: Failed to fetch RMM data', [
                        'customer_id' => $customer->external_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Get all sites/customers
                $sites = $this->client->getRmmSites();
                $devicesData = collect($sites)->take(10)->map(function ($site) {
                    return [
                        'id' => $site['uid'] ?? $site['id'] ?? null,
                        'hostname' => $site['name'] ?? 'Unknown Site',
                        'type' => 'Site',
                        'status' => 'active',
                        'os' => 'N/A',
                        'ip_address' => 'N/A',
                        'last_seen' => now()->toIso8601String(),
                    ];
                })->toArray();
            }

            // Mock backup data for now (would come from Datto SaaS API)
            $backupsData = collect($devicesData)->take(5)->map(function ($device, $index) {
                return [
                    'id' => $index + 1,
                    'device_name' => $device['hostname'],
                    'backup_type' => 'incremental',
                    'status' => $index % 3 === 0 ? 'success' : ($index % 5 === 0 ? 'failed' : 'success'),
                    'last_backup' => now()->subHours(rand(1, 24))->toIso8601String(),
                    'size' => rand(1000000, 50000000000),
                    'duration' => rand(300, 7200),
                ];
            })->toArray();

            // Calculate metrics
            $totalDevices = count($devicesData);
            $onlineDevices = collect($devicesData)->where('status', 'online')->count();
            $successfulBackups = collect($backupsData)->where('status', 'success')->count();
            $backupSuccessRate = count($backupsData) > 0 
                ? ($successfulBackups / count($backupsData)) * 100 
                : 0;

            return [
                'success' => true,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'external_id' => $customer->external_id,
                ] : null,
                'data' => [
                    'devices' => $devicesData,
                    'backups' => $backupsData,
                    'metrics' => [
                        'total_devices' => $totalDevices,
                        'online_devices' => $onlineDevices,
                        'backup_success_rate' => round($backupSuccessRate, 1),
                        'active_alerts' => count($alertsData),
                    ],
                    'alerts' => $alertsData,
                ],
                'timestamp' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error('Datto Integration: Failed to get preview data', [
                'integration_id' => $this->integration->integration_id,
                'customer_id' => $integrationCustomerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getSeverityIcon(string $severity): string
    {
        return match($severity) {
            'critical' => 'AlertOctagon',
            'high' => 'AlertTriangle',
            'medium' => 'AlertCircle',
            'low' => 'Info',
            default => 'Bell',
        };
    }

    private function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'gray',
        };
    }
}
