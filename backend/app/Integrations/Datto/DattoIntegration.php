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
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Integrations\Concerns\HasHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DattoIntegration implements IntegrationInterface
{
    use HasHeartbeat;

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
             // 1. Checkpoint: Resume from last state
            $state = json_decode($this->integration->sync_state, true);
            $phase = $state['phase'] ?? 'sites';
            $resumeOffset = $state['offset'] ?? 0;

            // 2. Fetch all RMM Sites
            $dattoCustomers = $this->client->getCustomers();
            $totalCustomers = count($dattoCustomers);
            
            \Log::info('Datto Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'customer_count' => $totalCustomers,
                'resume_offset' => $resumeOffset,
            ]);

            // 3. Slice and Chunk (Batch Size = 5 due to 60/min rate limit)
            // 5 sites * (1 site + 1 device + 1 alerts) = 15 calls min?
            // Actually processBatch makes 2 calls (devices + alerts).
            // 5 sites * 2 calls = 10 calls per job. 6 workers = 60 calls/min. Safe.
            if ($resumeOffset > 0) {
                 $dattoCustomers = array_slice($dattoCustomers, $resumeOffset);
                 $totalProcessed = $resumeOffset;
            }

            collect($dattoCustomers)->chunk(5)->each(function ($chunk) use (&$errors, &$totalProcessed, $totalCustomers) {
                // Dispatch batch job
                \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'sites', $chunk->toArray(), $this->currentSyncLogId);
                
                $totalProcessed += count($chunk);
                
                $this->saveSyncState('sites', $totalProcessed, $totalCustomers);
            });

            // 4. Clear Checkpoint if we finished everything
            // Note: processBatch handles everything (Sites, Devices, Alerts) in one go for Datto
            $this->integration->update(['sync_state' => null]);
            
            // 5. Update Cache
            $this->updateStatsCache();

        } catch (\Exception $e) {
            if ($e instanceof \App\Integrations\Exceptions\RateLimitException) {
                throw $e;
            }

            $errors[] = "Sync failed: {$e->getMessage()}";
            \Log::error('Datto Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'records_synced' => $totalProcessed,
            'records_new' => 0, // Calculated async
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
        BatchAutoMatchCustomersJob::dispatch($this->integration);
        return [];
    }

    public function getCustomers(): array
    {
        $dattoCustomers = $this->client->getCustomers();
        return array_map(fn($customer) => DattoMapper::mapCustomer($customer), $dattoCustomers);
    }

    protected $progressCallback = null;

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
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
            case 'sites':
                foreach ($items as $site) {
                    try {
                        // 1. Sync Site (Customer)
                        $standardized = DattoMapper::mapCustomer($site);
                        $this->storeCustomer($standardized);

                        // 2. Sync Devices (Historical/Current State)
                        // Note: Datto site UID is needed for device lookup
                        $siteUid = $site['uid'] ?? $site['organizationId'] ?? null;
                        if ($siteUid) {
                            $this->syncSiteDevices($siteUid, $site);
                            $this->syncSiteAlerts($siteUid, $site);
                        }

                        // Heartbeat (per site is frequent enough for Datto given rate limits)
                        if (method_exists($this, 'sendHeartbeat')) {
                            $this->sendHeartbeat();
                        }

                    } catch (\Exception $e) {
                         $siteId = $site['id'] ?? 'unknown';
                         Log::error('Datto Batch: Failed to process site', [
                             'site_id' => $siteId, 
                             'error' => $e->getMessage()
                         ]);
                    }
                }
                break;
        }
    }

    protected function syncSiteDevices(string $siteUid, array $site): void
    {
        try {
            $devices = $this->client->getRmmDevices($siteUid);
            foreach ($devices as $device) {
                try {
                    $this->storeDeviceData($device, $site, now());
                } catch (\Exception $e) {
                    Log::error("Datto Batch: Device store failed", ['device' => $device['hostname'] ?? 'unknown']);
                }
            }
        } catch (\App\Integrations\Exceptions\RateLimitException $e) {
            throw $e; // Let Job handle backoff
        } catch (\Exception $e) {
            Log::error("Datto Batch: Device fetch failed for site $siteUid", ['error' => $e->getMessage()]);
        }
    }

    protected function syncSiteAlerts(string $siteUid, array $site): void
    {
        try {
            // Open Alerts
            $openAlerts = $this->client->getRmmAlerts($siteUid, true);
            foreach ($openAlerts as $alert) {
                $this->storeAlertData($alert, $site);
            }
            
            // Resolved Alerts (Last 90 days or just recent? Implementation plan implies consolidated sync)
            // Let's grab distinct recent window to be safe, e.g., 24 hours, or rely on full historical import separately?
            // The plan said "Consolidate sync and getHistoricalData".
            // Let's fetch reasonably recent history (e.g. 7 days) to ensure we capture updates.
            $resolvedAlerts = $this->client->getRmmAlerts($siteUid, false, now()->subDays(7));
             foreach ($resolvedAlerts as $alert) {
                $this->storeAlertData($alert, $site);
            }

        } catch (\App\Integrations\Exceptions\RateLimitException $e) {
            throw $e;
        } catch (\Exception $e) {
             Log::error("Datto Batch: Alert fetch failed for site $siteUid", ['error' => $e->getMessage()]);
        }
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // Historical data is now handled via processBatch dispatching during sync()
        // This method remains to satisfy the interface but performs no action
        return [
            'success' => true,
            'total_processed' => 0,
            'errors' => [],
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
            // Batch job processes all unmapped customers at end of sync
        });
    }

    private function storeHistoricalData(array $data): void
    {
        // Determine unique key based on data type
        $payload = $data['payload'];
        $uniqueKey = $this->getUniqueKeyFromPayload($data['data_type'], $payload);
        
        if ($uniqueKey) {
            // Use upsert pattern - update if exists, insert if not
            DB::table('integration_data')->updateOrInsert(
                [
                    'integration_id' => $this->integration->integration_id,
                    'data_type' => $data['data_type'],
                    'integration_customer_id' => $data['integration_customer_id'],
                    // Use raw SQL to match on JSONB field
                ],
                [
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $data['integration_customer_id'],
                    'data_type' => $data['data_type'],
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => json_encode($payload),
                    'imported_at' => now(),
                    'data_version' => 1,
                ]
            );
        } else {
            // Fallback to insert for data types without unique keys
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
     * Get unique key from payload based on data type
     */
    private function getUniqueKeyFromPayload(string $dataType, array $payload): ?string
    {
        return match($dataType) {
            'alert' => $payload['alert_uid'] ?? null,
            'device_status' => $payload['device_uid'] ?? null,
            'uptime_metric' => $payload['device_uid'] ?? null,
            default => null,
        };
    }

    protected function storeAlertData(array $alert, array $site): void
    {
        $siteUid = $site['uid'] ?? $site['id'] ?? null;
        if (!$siteUid) return;

        $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('external_id', $siteUid)
            ->first();

        if (!$integrationCustomer) return;

        // Extract alert data from Datto API response
        $alertUid = $alert['alertUid'] ?? $alert['uid'] ?? null;
        if (!$alertUid) return; // Skip alerts without unique ID
        
        $priority = $alert['priority'] ?? 'Information';
        $description = $alert['diagnostics'] ?? $alert['alertMessage'] ?? 'No details';
        $deviceName = $alert['alertSourceInfo']['deviceName'] ?? $alert['hostname'] ?? 'Unknown';
        $deviceUid = $alert['alertSourceInfo']['deviceUid'] ?? $alert['deviceUid'] ?? null;
        $resolved = $alert['resolved'] ?? false;
        $muted = $alert['muted'] ?? false;
        $ticketNumber = $alert['ticketNumber'] ?? null;
        
        // Parse timestamp - Datto uses milliseconds
        $timestamp = now();
        if (isset($alert['timestamp'])) {
            $timestamp = Carbon::createFromTimestampMs($alert['timestamp']);
        } elseif (isset($alert['alertDate'])) {
            $timestamp = Carbon::parse($alert['alertDate']);
        }
        
        // Parse resolved timestamp
        $resolvedAt = null;
        if ($resolved && isset($alert['resolvedOn'])) {
            $resolvedAt = Carbon::createFromTimestampMs($alert['resolvedOn'])->toIso8601String();
        }

        $payload = [
            'alert_uid' => $alertUid,
            'device_uid' => $deviceUid,
            'device_name' => $deviceName,
            'priority' => $priority,
            'description' => $description,
            'source' => $alert['alertContext']['source'] ?? $alert['source'] ?? null,
            'event_type' => $alert['alertContext']['type'] ?? $alert['category'] ?? null,
            'resolved' => $resolved,
            'resolved_at' => $resolvedAt,
            'muted' => $muted,
            'ticket_number' => $ticketNumber,
        ];

        // UPSERT: Update existing alert or insert new one
        // Uses alert_uid as unique identifier to prevent duplicates
        $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
            ->where('data_type', 'alert')
            ->whereRaw("data_payload->>'alert_uid' = ?", [$alertUid])
            ->first();

        if ($existing) {
            // Update existing alert (e.g., resolved status changed)
            $existing->update([
                'data_timestamp' => $timestamp,
                'data_payload' => $payload,
                'imported_at' => now(),
            ]);
        } else {
            // Insert new alert
            IntegrationData::create([
                'company_id' => $this->integration->company_id,
                'integration_id' => $this->integration->integration_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'data_type' => 'alert',
                'data_timestamp' => $timestamp,
                'data_payload' => $payload,
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
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

    public function syncDevices(): void
    {
        \Log::info('Datto Integration: Starting device and alert sync', [
            'integration_id' => $this->integration->integration_id,
        ]);

        try {
            $sites = $this->client->getRmmSites();
            $totalDevices = 0;
            $totalAlerts = 0;
            $totalSites = count($sites);
            $processedSites = 0;

            foreach ($sites as $site) {
                $processedSites++;
                
                try {
                    $siteUid = $site['uid'];
                    
                    // Sync devices
                    $devices = $this->client->getRmmDevices($siteUid);
                    foreach ($devices as $device) {
                        $this->storeDeviceData($device, $site);
                        $totalDevices++;
                    }
                    
                    // Sync alerts
                    $alerts = $this->client->getRmmAlerts($siteUid);
                    foreach ($alerts as $alert) {
                        $this->storeAlertData($alert, $site);
                        $totalAlerts++;
                    }
                    
                    // Rate limiting handled by RateLimiter service in DattoClient::makeRmmRequest()
                    // RateLimiter waits intelligently only when approaching limits
                    
                } catch (\App\Integrations\Exceptions\RateLimitException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    // Log error but continue with other sites
                    \Log::warning('Datto Integration: Failed to sync data for site', [
                        'integration_id' => $this->integration->integration_id,
                        'site_uid' => $site['uid'] ?? 'unknown',
                        'site_name' => $site['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    
                    // If rate limited, stop processing more sites
                    if (str_contains($e->getMessage(), 'Rate limit')) {
                        \Log::warning('Datto Integration: Rate limit hit, stopping sync', [
                            'processed_sites' => $processedSites,
                            'total_sites' => $totalSites,
                            'devices_synced' => $totalDevices,
                            'alerts_synced' => $totalAlerts,
                        ]);
                        break;
                    }
                }
            }

            \Log::info('Datto Integration: Device and alert sync completed', [
                'integration_id' => $this->integration->integration_id,
                'total_devices' => $totalDevices,
                'total_alerts' => $totalAlerts,
                'sites_processed' => $processedSites,
                'sites_total' => $totalSites,
            ]);

        } catch (\App\Integrations\Exceptions\RateLimitException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Datto Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - let sync continue even if sync fails
        }
    }

    protected function storeDeviceData(array $device, array $site, ?\Carbon\Carbon $timestamp = null): void
    {
        // Find the integration customer for this site
        // Datto uses 'uid' as the unique identifier, not 'id'
        $siteUid = $site['uid'] ?? $site['id'] ?? null;
        
        if (!$siteUid) {
            \Log::warning('Datto Integration: Site missing UID', ['site' => $site]);
            return;
        }
        
        $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('external_id', $siteUid)
            ->first();
        
        if (!$integrationCustomer) {
            \Log::debug('Datto Integration: No integration customer found for site', [
                'site_uid' => $siteUid,
                'site_name' => $site['name'] ?? 'Unknown',
            ]);
            return; // Skip if customer not mapped
        }
        
        $deviceUid = $device['uid'] ?? null;
        if (!$deviceUid) {
            return; // Skip devices without unique ID
        }
        
        // Add site info to device for mapping
        $device['siteUid'] = $siteUid;
        $device['siteName'] = $site['name'] ?? 'Unknown';
        
        // Map device data using DattoMapper
        $mappedDevice = DattoMapper::mapDeviceData($device);
        
        // Use provided timestamp (for historical import) or now() (for sync)
        $metricTimestamp = $timestamp ?? now();
        $dateOnly = $metricTimestamp->copy()->startOfDay();
        
        // UPSERT device_status: One record per device per day
        $existingDevice = IntegrationData::where('integration_id', $this->integration->integration_id)
            ->where('data_type', 'device_status')
            ->where('integration_customer_id', $integrationCustomer->integration_customer_id)
            ->whereRaw("data_payload->>'device_uid' = ?", [$deviceUid])
            ->whereDate('data_timestamp', $dateOnly)
            ->first();

        if ($existingDevice) {
            $existingDevice->update([
                'data_timestamp' => $mappedDevice['timestamp'],
                'data_payload' => $mappedDevice['payload'],
                'imported_at' => now(),
            ]);
        } else {
            IntegrationData::create([
                'company_id' => $this->integration->company_id,
                'integration_id' => $this->integration->integration_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'data_type' => $mappedDevice['data_type'],
                'data_timestamp' => $mappedDevice['timestamp'],
                'data_payload' => $mappedDevice['payload'],
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
        
        // Calculate uptime percentage based on online status
        $isOnline = $device['online'] ?? false;
        $uptimePercentage = $isOnline ? 100.0 : 0.0;

        $uptimePayload = [
            'device_uid' => $deviceUid,
            'device_name' => $device['hostname'] ?? 'Unknown',
            'uptime_percentage' => $uptimePercentage,
            'total_hours' => 24,
            'online_hours' => $isOnline ? 24 : 0,
            'offline_hours' => $isOnline ? 0 : 24,
            'timestamp' => $metricTimestamp->toIso8601String(),
        ];

        // UPSERT uptime_metric: One record per device per day
        $existingUptime = IntegrationData::where('integration_id', $this->integration->integration_id)
            ->where('data_type', 'uptime_metric')
            ->where('integration_customer_id', $integrationCustomer->integration_customer_id)
            ->whereRaw("data_payload->>'device_uid' = ?", [$deviceUid])
            ->whereDate('data_timestamp', $dateOnly)
            ->first();

        if ($existingUptime) {
            $existingUptime->update([
                'data_timestamp' => $metricTimestamp,
                'data_payload' => $uptimePayload,
                'imported_at' => now(),
            ]);
        } else {
            IntegrationData::create([
                'company_id' => $this->integration->company_id,
                'integration_id' => $this->integration->integration_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'data_type' => 'uptime_metric',
                'data_timestamp' => $metricTimestamp,
                'data_payload' => $uptimePayload,
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
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
