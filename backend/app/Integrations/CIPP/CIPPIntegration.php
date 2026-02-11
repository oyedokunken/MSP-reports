<?php

declare(strict_types=1);

namespace App\Integrations\CIPP;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\DisplayTemplate\TemplateBuilder;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Integrations\Contracts\IntegrationDataType;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use App\Integrations\Concerns\HasHeartbeat;
use App\Integrations\Concerns\HasDefaultPreview;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

final class CIPPIntegration implements IntegrationInterface
{
    use HasHeartbeat;
    use HasDefaultPreview;

    protected Integration $integration;
    protected CIPPClient $client;
    protected CustomerMappingService $mappingService;
    /** @var array<string, bool> */
    protected array $supportedEndpoints = [];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new CIPPClient($integration->credentials, $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'base_url',
                'label' => 'CIPP API URL',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'https://your-cipp.azurewebsites.net',
                'help' => 'Your CIPP instance URL',
            ],
            [
                'name' => 'tenant_id',
                'label' => 'Azure Tenant ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'help' => 'Your Azure AD Tenant ID (GUID format)',
            ],
            [
                'name' => 'client_id',
                'label' => 'Application (Client) ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
                'help' => 'The Application ID from your CIPP API client registration',
            ],
            [
                'name' => 'client_secret',
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
                'help' => 'The client secret from your CIPP API client',
            ],
        ];
    }

    public function authenticate(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (\Exception $e) {
            Log::error('CIPP: Authentication failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $results = [
            'tenants' => 0,
            'users' => 0,
            'licenses' => 0,
            'security_alerts' => 0,
            'security_incidents' => 0,
            'conditional_access' => 0,
            'defender_states' => 0,
            'mailbox_usage' => 0,
            'exchange_protection' => 0,
            'errors' => [],
        ];

        try {
            // Sync tenants (customers)
            $tenantResult = $this->syncTenants();
            $results['tenants'] = $tenantResult['synced'];
            if (!empty($tenantResult['errors'])) {
                $results['errors'] = array_merge($results['errors'], $tenantResult['errors']);
            }

            // Get all tenants for per-tenant syncing
            $tenants = $this->client->listTenants();
            $totalTenants = count($tenants);

            Log::info('CIPP: Dispatching tenant sync batches', [
                'integration_id' => $this->integration->integration_id,
                'tenant_count' => $totalTenants,
            ]);

            // Dispatch tenant batches (e.g., 5 tenants per batch)
            $batchesDispatched = 0;
            $chunks = array_chunk($tenants, 3); // 3 tenants per batch for safe time limit

            foreach ($chunks as $chunk) {
                \App\Jobs\ProcessIntegrationBatchJob::dispatch(
                    $this->integration,
                    'tenant_data',
                    $chunk,
                    $this->currentSyncLogId
                );
                $batchesDispatched++;
            }

            Log::info('CIPP: Batches dispatched', [
                'count' => $batchesDispatched,
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('CIPP: Sync failed during initialization', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Map to standardized results for the platform
        $results['records_synced'] = $results['tenants'];
        $results['records_failed'] = count($results['errors']);
        $results['batches_dispatched'] = $batchesDispatched ?? 0;

        return $results;
    }

    /**
     * Process a batch of tenants
     * 
     * @param string $type
     * @param array<int, array<string, mixed>> $tenants
     */
    public function processBatch(string $type, array $tenants): void
    {
        if ($type !== 'tenant_data') {
            return;
        }

        $config = $this->integration->configuration ?? [];

        foreach ($tenants as $tenant) {
            $this->sendHeartbeat();
            $tenantFilter = $tenant['defaultDomainName'] ?? $tenant['customerId'] ?? null;
            if (!$tenantFilter) {
                continue;
            }

            try {
                // Sync users & MFA
                if ($config['sync_users'] ?? true) {
                    $this->syncUsersForTenant($tenantFilter);
                }

                // Sync licenses
                if ($config['sync_licenses'] ?? true) {
                    $this->syncLicensesForTenant($tenantFilter);
                }

                // Sync security alerts
                if ($config['sync_security'] ?? true) {
                    $this->syncSecurityAlertsForTenant($tenantFilter);
                    $this->syncSecurityIncidentsForTenant($tenantFilter);
                }

                // Sync conditional access policies
                if ($config['sync_conditional_access'] ?? true) {
                    $this->syncConditionalAccessForTenant($tenantFilter);
                }

                // Sync Defender status
                if ($config['sync_defender'] ?? true) {
                    $this->syncDefenderForTenant($tenantFilter);
                }

                // Sync Email & Exchange
                if ($config['sync_email'] ?? true) {
                    $this->syncMailboxUsageForTenant($tenantFilter);
                    $this->syncExchangeProtectionForTenant($tenantFilter);
                }
            } catch (\Exception $e) {
                Log::warning('CIPP Batch: Error syncing tenant data', [
                    'integration_id' => $this->integration->integration_id,
                    'tenant' => $tenantFilter,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Helper to bulk-sync integration data and send heartbeats
     * 
     * @param string $dataType
     * @param array<int, array<string, mixed>> $items
     */
    protected function bulkSyncData(string $dataType, array $items): int
    {
        if (empty($items)) {
            return 0;
        }

        $chunks = array_chunk($items, 200); // 200 items per batch
        $totalSynced = 0;

        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $item) {
                // Determine timestamp
                $timestamp = now();
                if (isset($item['created_at']))
                    $timestamp = Carbon::parse($item['created_at']);
                else if (isset($item['last_seen']))
                    $timestamp = Carbon::parse($item['last_seen']);
                else if (isset($item['data_timestamp']))
                    $timestamp = Carbon::parse($item['data_timestamp']);

                $insertData[] = [
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'data_type' => $dataType,
                    'external_id' => $item['external_id'] ?? '',
                    'data_timestamp' => $timestamp,
                    'data_payload' => json_encode($item),
                    'imported_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // High performance insert
            IntegrationData::insert($insertData);

            $totalSynced += count($chunk);
            $this->sendHeartbeat(count($chunk)); // Update UI progress!
        }

        return $totalSynced;
    }

    /**
     * Sync tenants as customers
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncTenants(): array
    {
        $synced = 0;
        $errors = [];

        try {
            $tenants = $this->client->listTenants();

            $anyNewTenants = false;
            foreach ($tenants as $tenant) {
                $this->sendHeartbeat();
                try {
                    $mapped = CIPPMapper::mapTenant($tenant);

                    $integrationCustomer = IntegrationCustomer::updateOrCreate(
                        [
                            'integration_id' => $this->integration->integration_id,
                            'external_id' => $mapped['external_id'],
                        ],
                        [
                            'name' => $mapped['name'],
                            'active' => $mapped['active'],
                            'metadata' => $mapped['metadata'],
                            'last_seen_at' => now(),
                        ]
                    );

                    // Track if we need to auto-match later
                    if ($integrationCustomer->wasRecentlyCreated) {
                        $anyNewTenants = true;
                    }

                    $synced++;
                    $this->sendHeartbeat(1); // Update UI for each tenant customer synced
                } catch (\Exception $e) {
                    $errors[] = "Tenant {$tenant['displayName']}: {$e->getMessage()}";
                }
            }

            // Auto-match once at the end if any new tenants were found
            if ($anyNewTenants) {
                BatchAutoMatchCustomersJob::dispatch($this->integration);
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list tenants: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync users for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncUsersForTenant(string $tenantFilter): array
    {
        $errors = [];

        try {
            $users = $this->client->listMFAUsers($tenantFilter);
            $mappedItems = [];

            foreach ($users as $user) {
                try {
                    $mappedItems[] = CIPPMapper::mapUser($user, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "User mapping error: {$e->getMessage()}";
                }
            }

            $synced = $this->bulkSyncData('user', $mappedItems);
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            return ['synced' => 0, 'errors' => ["Failed to list users for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * Sync licenses for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncLicensesForTenant(string $tenantFilter): array
    {
        $errors = [];

        try {
            $licenses = $this->client->listLicenses($tenantFilter);
            $mappedItems = [];

            foreach ($licenses as $license) {
                try {
                    $mappedItems[] = CIPPMapper::mapLicense($license, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "License mapping error: {$e->getMessage()}";
                }
            }

            $synced = $this->bulkSyncData('license', $mappedItems);
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            return ['synced' => 0, 'errors' => ["Failed to list licenses for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * Sync security alerts for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncSecurityAlertsForTenant(string $tenantFilter): array
    {
        if (isset($this->supportedEndpoints['alerts']) && !$this->supportedEndpoints['alerts'])
            return ['synced' => 0, 'errors' => []];
        $errors = [];
        try {
            $alerts = $this->client->listSecurityAlerts($tenantFilter);
            $mappedItems = [];
            foreach ($alerts as $alert) {
                try {
                    $mappedItems[] = CIPPMapper::mapSecurityAlert($alert, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "Alert mapping error: {$e->getMessage()}";
                }
            }
            $synced = $this->bulkSyncData('security_alert', $mappedItems);
            $this->supportedEndpoints['alerts'] = true;
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403'))
                $this->supportedEndpoints['alerts'] = false;
            return ['synced' => 0, 'errors' => ["Failed to list security alerts for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * Sync security incidents for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncSecurityIncidentsForTenant(string $tenantFilter): array
    {
        if (isset($this->supportedEndpoints['incidents']) && !$this->supportedEndpoints['incidents'])
            return ['synced' => 0, 'errors' => []];
        $errors = [];
        try {
            $incidents = $this->client->listSecurityIncidents($tenantFilter);
            $mappedItems = [];
            foreach ($incidents as $incident) {
                try {
                    $mappedItems[] = CIPPMapper::mapSecurityIncident($incident, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "Incident mapping error: {$e->getMessage()}";
                }
            }
            $synced = $this->bulkSyncData('security_incident', $mappedItems);
            $this->supportedEndpoints['incidents'] = true;
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403'))
                $this->supportedEndpoints['incidents'] = false;
            return ['synced' => 0, 'errors' => ["Failed to list security incidents for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * Sync conditional access policies for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncConditionalAccessForTenant(string $tenantFilter): array
    {
        if (isset($this->supportedEndpoints['conditional_access']) && !$this->supportedEndpoints['conditional_access'])
            return ['synced' => 0, 'errors' => []];
        $errors = [];
        try {
            $policies = $this->client->listConditionalAccessPolicies($tenantFilter);
            $mappedItems = [];
            foreach ($policies as $policy) {
                try {
                    $mappedItems[] = CIPPMapper::mapConditionalAccessPolicy($policy, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "CA mapping error: {$e->getMessage()}";
                }
            }
            $synced = $this->bulkSyncData('conditional_access', $mappedItems);
            $this->supportedEndpoints['conditional_access'] = true;
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403'))
                $this->supportedEndpoints['conditional_access'] = false;
            return ['synced' => 0, 'errors' => ["Failed to list conditional access policies for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncDefenderForTenant(string $tenantFilter): array
    {
        if (isset($this->supportedEndpoints['defender']) && !$this->supportedEndpoints['defender'])
            return ['synced' => 0, 'errors' => []];
        $errors = [];
        try {
            $devices = $this->client->listDefenderState($tenantFilter);
            $mappedItems = [];
            foreach ($devices as $device) {
                try {
                    $mappedItems[] = CIPPMapper::mapDefenderState($device, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "Defender mapping error: {$e->getMessage()}";
                }
            }
            $synced = $this->bulkSyncData('defender_state', $mappedItems);
            $this->supportedEndpoints['defender'] = true;
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403'))
                $this->supportedEndpoints['defender'] = false;
            return ['synced' => 0, 'errors' => ["Failed to list Defender state for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * Sync mailbox usage for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncMailboxUsageForTenant(string $tenantFilter): array
    {
        if (isset($this->supportedEndpoints['mailbox']) && !$this->supportedEndpoints['mailbox'])
            return ['synced' => 0, 'errors' => []];
        $errors = [];
        try {
            $usage = $this->client->listMailboxUsage($tenantFilter);
            $mappedItems = [];
            foreach ($usage as $item) {
                try {
                    $mappedItems[] = CIPPMapper::mapMailboxUsage($item, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "Mailbox mapping error: {$e->getMessage()}";
                }
            }
            $synced = $this->bulkSyncData('mailbox_usage', $mappedItems);
            $this->supportedEndpoints['mailbox'] = true;
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403'))
                $this->supportedEndpoints['mailbox'] = false;
            return ['synced' => 0, 'errors' => ["Failed to list mailbox usage for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncExchangeProtectionForTenant(string $tenantFilter): array
    {
        if (isset($this->supportedEndpoints['exchange']) && !$this->supportedEndpoints['exchange'])
            return ['synced' => 0, 'errors' => []];
        $errors = [];
        try {
            $protection = $this->client->listExchangeProtection($tenantFilter);
            $mappedItems = [];
            foreach ($protection as $item) {
                try {
                    $mappedItems[] = CIPPMapper::mapExchangeProtection($item, $tenantFilter);
                } catch (\Exception $e) {
                    $errors[] = "Exchange protection mapping error: {$e->getMessage()}";
                }
            }
            $synced = $this->bulkSyncData('exchange_protection', $mappedItems);
            $this->supportedEndpoints['exchange'] = true;
            return ['synced' => $synced, 'errors' => $errors];
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '403'))
                $this->supportedEndpoints['exchange'] = false;
            return ['synced' => 0, 'errors' => ["Failed to list exchange protection for {$tenantFilter}: {$e->getMessage()}"]];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $customers = [];

        try {
            $tenants = $this->client->listTenants();

            foreach ($tenants as $tenant) {
                $customers[] = CIPPMapper::mapTenant($tenant);
            }
        } catch (\Exception $e) {
            Log::error('CIPP: Failed to get customers', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $customers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [
            'sign_ins' => [],
            'audit_logs' => [],
            'security_alerts' => [],
        ];

        try {
            $tenants = $this->client->listTenants();

            foreach ($tenants as $tenant) {
                $this->sendHeartbeat();
                $tenantFilter = $tenant['defaultDomainName'] ?? $tenant['customerId'] ?? null;
                if (!$tenantFilter) {
                    continue;
                }

                try {
                    // Get sign-in logs
                    $days = $startDate->diffInDays($endDate);
                    $signIns = $this->client->listSignIns($tenantFilter, (int) $days);
                    foreach ($signIns as $signIn) {
                        $data['sign_ins'][] = CIPPMapper::mapSignIn($signIn, $tenantFilter);
                    }

                    // Get audit logs
                    $auditLogs = $this->client->listAuditLogs(
                        $tenantFilter,
                        $startDate->toIso8601String(),
                        $endDate->toIso8601String()
                    );
                    $data['audit_logs'] = array_merge($data['audit_logs'], $auditLogs);

                } catch (\Exception $e) {
                    Log::warning('CIPP: Error getting historical data for tenant', [
                        'integration_id' => $this->integration->integration_id,
                        'tenant' => $tenantFilter,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('CIPP: Failed to get historical data', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'data' => $data,
            'total_processed' => count($data['sign_ins']) + count($data['audit_logs']) + count($data['security_alerts']),
        ];
    }

    /**
     * Get the earliest date for which historical data is available
     */
    public function getAvailableHistoryRange(): Carbon
    {
        // CIPP typically has 30 days of sign-in and audit log history
        return now()->subDays(30);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        $config = require __DIR__ . '/config.php';

        return [
            'name' => $config['name'],
            'display_name' => $config['display_name'],
            'description' => $config['description'],
            'data_types' => $config['data_types'],
            'supports_historical' => $config['supports_historical'],
            'historical_range_days' => $config['historical_range_days'],
            'rate_limits' => $config['rate_limits'],
        ];
    }

    public function disconnect(): void
    {
        try {
            $this->client->clearTokenCache();
        } catch (\Exception $e) {
            Log::error('CIPP: Disconnect failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function healthCheck(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRateLimits(): array
    {
        $config = require __DIR__ . '/config.php';
        return $config['rate_limits'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array
    {
        $builder = new TemplateBuilder('cipp', 'CIPP (Microsoft 365)');
        $builder->description('Microsoft 365 Management via CIPP');

        // Overview
        $builder->addSection('overview', 'Overview', 'grid')
            ->icon('Activity')
            ->dataPath('metrics')
            ->gridColumns(4)
            ->addMetric('managed_tenants', 'Managed Tenants', 'tenant_count', 'number', 'Building', 'blue')
            ->addMetric('total_users', 'Total Users (Sample)', 'total_users', 'number', 'Users', 'green')
            ->addMetric('license_types', 'License Types', 'total_licenses', 'key', 'Key', 'purple')
            ->addMetric('active_alerts', 'Active Alerts', 'active_alerts', 'number', 'AlertTriangle', 'orange')
            ->end();

        // Sample Tenants
        $builder->addSection('tenants', 'Managed Tenants Sample', 'table')
            ->icon('Building')
            ->dataPath('tenants')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Display Name', 'sortable' => true],
                ['key' => 'domain', 'label' => 'Default Domain', 'sortable' => true],
                ['key' => 'status', 'label' => 'Status', 'format' => 'status'],
            ])
            ->end();

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreviewData(?int $integrationCustomerId = null): array
    {
        try {
            Log::info('CIPP Preview: Fetching preview data', [
                'integration_id' => $this->integration->integration_id,
                'customer_id' => $integrationCustomerId
            ]);

            // 1. Get tenants from API (fastest way to verify connection)
            // We cache this for 5 minutes to avoid repeated slow calls
            $cacheKey = "cipp_tenants_{$this->integration->integration_id}";
            $tenants = Cache::remember($cacheKey, 300, function() {
                return $this->client->listTenants();
            });
            
            $tenantCount = count($tenants);
            Log::debug('CIPP Preview: Tenant count', ['count' => $tenantCount]);

            // 2. Fetch summary metrics from DATABASE instead of API
            // Sequential API calls for sample tenants were causing timeouts (60s+)
            $totalUsers = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', 'user')
                ->count();

            $totalLicenses = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', 'license')
                ->count();

            $activeAlerts = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', 'security_alert')
                ->where('data_payload->status', '!=', 'resolved')
                ->count();

            // 3. Prepare tenant list for display
            // If customerId is provided, just show that one if it exists
            $displayTenants = [];
            if ($integrationCustomerId) {
                $customer = \App\Models\IntegrationCustomer::find($integrationCustomerId);
                if ($customer) {
                    $displayTenants[] = [
                        'name' => $customer->name,
                        'domain' => $customer->external_id,
                        'status' => $customer->active ? 'active' : 'inactive'
                    ];
                }
            }
            
            // If we have no specific customer or it wasn't found, show top 10 from API
            if (empty($displayTenants) && !empty($tenants)) {
                $displayTenants = array_map(fn($t) => [
                    'name' => $t['displayName'] ?? $t['defaultDomainName'] ?? 'Unknown',
                    'domain' => $t['defaultDomainName'] ?? $t['customerId'] ?? '',
                    'status' => 'active'
                ], array_slice($tenants, 0, 10));
            }

            return [
                'success' => true,
                'data' => [
                    'metrics' => [
                        'tenant_count' => $tenantCount,
                        'total_users' => $totalUsers,
                        'total_licenses' => $totalLicenses,
                        'active_alerts' => $activeAlerts,
                    ],
                    'tenants' => $displayTenants
                ],
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\Exception $e) {
            Log::error('CIPP Preview Error', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to fetch preview data: ' . $e->getMessage()
            ];
        }
    }
}

