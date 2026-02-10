<?php

declare(strict_types=1);

namespace App\Integrations\ThreatLocker;

use App\Integrations\Concerns\HasHeartbeat;
use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Concerns\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class ThreatLockerIntegration implements IntegrationInterface
{
    use HasHeartbeat;
    use HasDefaultPreview;

    protected Integration $integration;
    protected ThreatLockerClient $client;
    protected RateLimiter $rateLimiter;

    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->rateLimiter = app(RateLimiter::class);
        $this->config = include __DIR__ . '/config.php';

        /** @var array<string, mixed> $credentials */
        $credentials = $integration->credentials ?? [];
        $this->client = new ThreatLockerClient($credentials, $this->rateLimiter, $integration);
    }

    public function authenticate(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (\Exception $e) {
            Log::error('ThreatLocker: Authentication failed', [
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
        Log::info('ThreatLocker: Starting sync', [
            'integration_id' => $this->integration->integration_id,
        ]);

        $totalSynced = 0;
        $totalFailed = 0;
        /** @var array<int, string> $errors */
        $errors = [];
        /** @var array<int, array{id: int, name: string}> $customersMapped */
        $customersMapped = [];

        try {
            // Step 1: Sync organizations as integration customers
            $orgResult = $this->syncOrganizations();
            $totalSynced += $orgResult['synced'];
            $totalFailed += $orgResult['failed'];
            $errors = array_merge($errors, $orgResult['errors']);
            $customersMapped = $orgResult['customers_mapped'];

            $this->sendHeartbeat($totalSynced);

            // Step 2: Sync computers for each organization
            /** @var array<string, mixed> $configuration */
            $configuration = $this->integration->configuration ?? [];
            $syncComputers = $configuration['sync_computers'] ?? true;

            if ($syncComputers) {
                $computerResult = $this->syncComputers();
                $totalSynced += $computerResult['synced'];
                $totalFailed += $computerResult['failed'];
                $errors = array_merge($errors, $computerResult['errors']);
            }

            $this->sendHeartbeat($totalSynced);

            // Step 3: Sync policies (if enabled)
            $syncPolicies = $configuration['sync_policies'] ?? true;

            if ($syncPolicies) {
                $policyResult = $this->syncPolicies();
                $totalSynced += $policyResult['synced'];
                $totalFailed += $policyResult['failed'];
                $errors = array_merge($errors, $policyResult['errors']);
            }

            $this->sendHeartbeat($totalSynced);

            // Step 4: Sync applications (if enabled)
            $syncApplications = $configuration['sync_applications'] ?? true;

            if ($syncApplications) {
                $appResult = $this->syncApplications();
                $totalSynced += $appResult['synced'];
                $totalFailed += $appResult['failed'];
                $errors = array_merge($errors, $appResult['errors']);
            }

            $this->sendHeartbeat($totalSynced);

            // Step 5: Sync storage policies (if enabled)
            $syncStoragePolicies = $configuration['sync_storage_policies'] ?? true;

            if ($syncStoragePolicies) {
                $storageResult = $this->syncStoragePolicies();
                $totalSynced += $storageResult['synced'];
                $totalFailed += $storageResult['failed'];
                $errors = array_merge($errors, $storageResult['errors']);
            }

            $this->sendHeartbeat($totalSynced);

            // Step 6: Sync storage devices (if enabled)
            $syncStorageDevices = $configuration['sync_storage_devices'] ?? true;

            if ($syncStorageDevices) {
                $deviceResult = $this->syncStorageDevices();
                $totalSynced += $deviceResult['synced'];
                $totalFailed += $deviceResult['failed'];
                $errors = array_merge($errors, $deviceResult['errors']);
            }

            $this->sendHeartbeat($totalSynced);

            // Step 4: Auto-match customers
            try {
                BatchAutoMatchCustomersJob::dispatch($this->integration);
            } catch (\Exception $e) {
                Log::warning('ThreatLocker: Auto-match dispatch failed (non-critical)', [
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('ThreatLocker: Sync completed', [
                'integration_id' => $this->integration->integration_id,
                'total_synced' => $totalSynced,
                'total_failed' => $totalFailed,
                'errors_count' => count($errors),
            ]);
        } catch (\Exception $e) {
            Log::error('ThreatLocker: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
            $errors[] = $e->getMessage();
        }

        return [
            'records_synced' => $totalSynced,
            'records_failed' => $totalFailed,
            'errors' => $errors,
            'customers_mapped' => $customersMapped,
            'batches_dispatched' => 0,
        ];
    }

    /**
     * Sync organizations as integration customers
     *
     * @return array{synced: int, failed: int, errors: array<int, string>, customers_mapped: array<int, array{id: int, name: string}>}
     */
    protected function syncOrganizations(): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];
        /** @var array<int, array{id: int, name: string}> $customers_mapped */
        $customers_mapped = [];

        try {
            $organizations = $this->client->getOrganizations();

            foreach ($organizations as $org) {
                try {
                    $mapped = ThreatLockerMapper::mapOrganization($org);

                    if (empty($mapped['external_id'])) {
                        $failed++;
                        continue;
                    }

                    $customer = IntegrationCustomer::updateOrCreate(
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

                    $customers_mapped[] = [
                        'id' => $customer->integration_customer_id,
                        'name' => (string) $mapped['name'],
                    ];

                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    $orgName = $org['name'] ?? 'unknown';
                    $errors[] = "Org sync error ({$orgName}): " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Organization fetch failed: ' . $e->getMessage();
        }

        Log::info('ThreatLocker: Organizations synced', [
            'integration_id' => $this->integration->integration_id,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed', 'errors', 'customers_mapped');
    }

    /**
     * Sync computers for all integration customers
     *
     * @return array{synced: int, failed: int, errors: array<int, string>}
     */
    protected function syncComputers(): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];

        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('active', true)
            ->get();

        foreach ($customers as $customer) {
            try {
                $orgId = $customer->external_id;
                $computers = $this->client->getComputers($orgId);

                foreach ($computers as $computer) {
                    try {
                        $mapped = ThreatLockerMapper::mapComputer($computer, $orgId);

                        if (empty($mapped['external_id'])) {
                            $failed++;
                            continue;
                        }

                        IntegrationData::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $customer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'external_id' => $mapped['external_id'],
                            ],
                            [
                                'company_id' => $this->integration->company_id,
                                'data_payload' => $mapped['data'],
                                'data_timestamp' => now(),
                                'imported_at' => now(),
                                'data_version' => 1,
                            ]
                        );

                        $synced++;
                    } catch (\Exception $e) {
                        $failed++;
                        $compName = $computer['computerName'] ?? 'unknown';
                        $errors[] = "Computer sync error ({$compName}): " . $e->getMessage();
                    }
                }

                $this->sendHeartbeat($synced);
            } catch (\Exception $e) {
                $errors[] = "Computer fetch failed for org {$customer->name}: " . $e->getMessage();
            }
        }

        Log::info('ThreatLocker: Computers synced', [
            'integration_id' => $this->integration->integration_id,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed', 'errors');
    }

    /**
     * Sync policies for all integration customers
     *
     * @return array{synced: int, failed: int, errors: array<int, string>}
     */
    protected function syncPolicies(): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];

        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('active', true)
            ->get();

        foreach ($customers as $customer) {
            try {
                $orgId = $customer->external_id;
                $policies = $this->client->getPolicies($orgId);

                foreach ($policies as $policy) {
                    try {
                        $mapped = ThreatLockerMapper::mapPolicy($policy, $orgId);

                        if (empty($mapped['external_id'])) {
                            $failed++;
                            continue;
                        }

                        IntegrationData::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $customer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'external_id' => $mapped['external_id'],
                            ],
                            [
                                'company_id' => $this->integration->company_id,
                                'data_payload' => $mapped['data'],
                                'data_timestamp' => now(),
                                'imported_at' => now(),
                                'data_version' => 1,
                            ]
                        );

                        $synced++;
                    } catch (\Exception $e) {
                        $failed++;
                        $polName = $policy['name'] ?? 'unknown';
                        $errors[] = "Policy sync error ({$polName}): " . $e->getMessage();
                    }
                }

                $this->sendHeartbeat($synced);
            } catch (\Exception $e) {
                $errors[] = "Policy fetch failed for org {$customer->name}: " . $e->getMessage();
            }
        }

        Log::info('ThreatLocker: Policies synced', [
            'integration_id' => $this->integration->integration_id,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed', 'errors');
    }

    /**
     * Sync applications for all integration customers
     *
     * @return array{synced: int, failed: int, errors: array<int, string>}
     */
    protected function syncApplications(): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];

        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('active', true)
            ->get();

        foreach ($customers as $customer) {
            try {
                $orgId = $customer->external_id;
                $applications = $this->client->getApplications($orgId);

                foreach ($applications as $app) {
                    try {
                        $mapped = ThreatLockerMapper::mapApplication($app, $orgId);

                        if (empty($mapped['external_id'])) {
                            $failed++;
                            continue;
                        }

                        IntegrationData::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $customer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'external_id' => $mapped['external_id'],
                            ],
                            [
                                'company_id' => $this->integration->company_id,
                                'data_payload' => $mapped['data'],
                                'data_timestamp' => now(),
                                'imported_at' => now(),
                                'data_version' => 1,
                            ]
                        );

                        $synced++;
                    } catch (\Exception $e) {
                        $failed++;
                        $appName = $app['name'] ?? 'unknown';
                        $errors[] = "Application sync error ({$appName}): " . $e->getMessage();
                    }
                }

                $this->sendHeartbeat($synced);
            } catch (\Exception $e) {
                $errors[] = "Application fetch failed for org {$customer->name}: " . $e->getMessage();
            }
        }

        Log::info('ThreatLocker: Applications synced', [
            'integration_id' => $this->integration->integration_id,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed', 'errors');
    }

    /**
     * Sync storage control policies for all integration customers
     *
     * @return array{synced: int, failed: int, errors: array<int, string>}
     */
    protected function syncStoragePolicies(): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];

        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('active', true)
            ->get();

        foreach ($customers as $customer) {
            try {
                $orgId = $customer->external_id;

                // Get groups first to scope storage policies
                $groupData = $this->client->getComputerGroups($orgId);
                $groups = $groupData['groups'];

                foreach ($groups as $group) {
                    $groupId = $group['groupId'] ?? null;
                    if (!$groupId) {
                        continue;
                    }

                    try {
                        $policies = $this->client->getStoragePolicies($orgId, $groupId);

                        foreach ($policies as $policy) {
                            try {
                                $mapped = ThreatLockerMapper::mapStoragePolicy($policy, $orgId);

                                if (empty($mapped['external_id'])) {
                                    $failed++;
                                    continue;
                                }

                                IntegrationData::updateOrCreate(
                                    [
                                        'integration_id' => $this->integration->integration_id,
                                        'integration_customer_id' => $customer->integration_customer_id,
                                        'data_type' => $mapped['data_type'],
                                        'external_id' => $mapped['external_id'],
                                    ],
                                    [
                                        'company_id' => $this->integration->company_id,
                                        'data_payload' => $mapped['data'],
                                        'data_timestamp' => now(),
                                        'imported_at' => now(),
                                        'data_version' => 1,
                                    ]
                                );

                                $synced++;
                            } catch (\Exception $e) {
                                $failed++;
                                $polName = $policy['name'] ?? 'unknown';
                                $errors[] = "Storage policy sync error ({$polName}): " . $e->getMessage();
                            }
                        }
                    } catch (\Exception $e) {
                        $groupName = $group['name'] ?? 'unknown';
                        $errors[] = "Storage policy fetch failed for group {$groupName}: " . $e->getMessage();
                    }
                }

                $this->sendHeartbeat($synced);
            } catch (\Exception $e) {
                $errors[] = "Storage policy fetch failed for org {$customer->name}: " . $e->getMessage();
            }
        }

        Log::info('ThreatLocker: Storage policies synced', [
            'integration_id' => $this->integration->integration_id,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed', 'errors');
    }

    /**
     * Sync storage devices for all integration customers
     *
     * @return array{synced: int, failed: int, errors: array<int, string>}
     */
    protected function syncStorageDevices(): array
    {
        $synced = 0;
        $failed = 0;
        /** @var array<int, string> $errors */
        $errors = [];

        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('active', true)
            ->get();

        foreach ($customers as $customer) {
            try {
                $orgId = $customer->external_id;
                $devices = $this->client->getStorageDevices($orgId);

                foreach ($devices as $device) {
                    try {
                        $mapped = ThreatLockerMapper::mapStorageDevice($device, $orgId);

                        if (empty($mapped['external_id'])) {
                            $failed++;
                            continue;
                        }

                        IntegrationData::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $customer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'external_id' => $mapped['external_id'],
                            ],
                            [
                                'company_id' => $this->integration->company_id,
                                'data_payload' => $mapped['data'],
                                'data_timestamp' => now(),
                                'imported_at' => now(),
                                'data_version' => 1,
                            ]
                        );

                        $synced++;
                    } catch (\Exception $e) {
                        $failed++;
                        $devName = $device['deviceName'] ?? 'unknown';
                        $errors[] = "Storage device sync error ({$devName}): " . $e->getMessage();
                    }
                }

                $this->sendHeartbeat($synced);
            } catch (\Exception $e) {
                $errors[] = "Storage device fetch failed for org {$customer->name}: " . $e->getMessage();
            }
        }

        Log::info('ThreatLocker: Storage devices synced', [
            'integration_id' => $this->integration->integration_id,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return compact('synced', 'failed', 'errors');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $organizations = $this->client->getOrganizations();
        $customers = [];

        foreach ($organizations as $org) {
            $customers[] = ThreatLockerMapper::mapOrganization($org);
        }

        return $customers;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->config['name'] ?? 'ThreatLocker',
            'description' => $this->config['description'] ?? '',
            'version' => $this->config['version'] ?? '2.0.0',
            'data_types' => array_keys($this->config['data_types'] ?? []),
            'supports_historical' => true,
            'historical_range_days' => 90,
            'rate_limits' => $this->config['rate_limits'] ?? [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        $fields = [];
        foreach ($this->config['credentials'] ?? [] as $key => $field) {
            $fields[] = array_merge(['name' => $key, 'key' => $key], $field);
        }
        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigurationFields(): array
    {
        $fields = [];
        foreach ($this->config['configuration'] ?? [] as $key => $field) {
            $fields[] = array_merge(['name' => $key, 'key' => $key], $field);
        }
        return $fields;
    }

    public function healthCheck(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $processed = 0;
        $errors = [];
        
        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->where('active', true)
            ->get();

        foreach ($customers as $customer) {
            try {
                $orgId = $customer->external_id;
                $audits = $this->client->getAuditLogs($orgId, $startDate, $endDate);

                foreach ($audits as $audit) {
                    try {
                        $mapped = ThreatLockerMapper::mapAudit($audit, $orgId);

                        if (empty($mapped['external_id'])) {
                            continue;
                        }

                        IntegrationData::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'integration_customer_id' => $customer->integration_customer_id,
                                'data_type' => $mapped['data_type'],
                                'external_id' => $mapped['external_id'],
                            ],
                            [
                                'company_id' => $this->integration->company_id,
                                'data_payload' => $mapped['data'],
                                'data_timestamp' => now(),
                                'imported_at' => now(),
                                'data_version' => 1,
                            ]
                        );

                        $processed++;
                    } catch (\Exception $e) {
                        $errors[] = "Audit map error for org {$customer->name}: " . $e->getMessage();
                    }
                }

                $this->sendHeartbeat($processed);
            } catch (\Exception $e) {
                $errors[] = "Audit fetch failed for org {$customer->name}: " . $e->getMessage();
            }
        }

        return [
            'data' => [], // Result is stored in DB
            'total_processed' => $processed,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(90);
    }

    public function disconnect(): void
    {
        $this->client->clearCache();
    }

    /**
     * @return array<string, int>
     */
    public function getRateLimits(): array
    {
        $rateLimits = $this->config['rate_limits'] ?? [];

        return [
            'requests_per_minute' => (int) ($rateLimits['requests_per_minute'] ?? 60),
            'requests_per_hour' => (int) ($rateLimits['requests_per_hour'] ?? 1000),
        ];
    }
}
