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

            // Get configuration to determine what to sync
            $config = $this->integration->configuration ?? [];
            
            // Get all tenants for per-tenant syncing
            $tenants = $this->client->listTenants();

            foreach ($tenants as $tenant) {
                $tenantFilter = $tenant['defaultDomainName'] ?? $tenant['customerId'] ?? null;
                if (!$tenantFilter) {
                    continue;
                }

                try {
                    // Sync users & MFA
                    if ($config['sync_users'] ?? true) {
                        $userResult = $this->syncUsersForTenant($tenantFilter);
                        $results['users'] += $userResult['synced'];
                    }

                    // Sync licenses
                    if ($config['sync_licenses'] ?? true) {
                        $licenseResult = $this->syncLicensesForTenant($tenantFilter);
                        $results['licenses'] += $licenseResult['synced'];
                    }

                    // Sync security alerts
                    if ($config['sync_security'] ?? true) {
                        $alertResult = $this->syncSecurityAlertsForTenant($tenantFilter);
                        $results['security_alerts'] += $alertResult['synced'];

                        $incidentResult = $this->syncSecurityIncidentsForTenant($tenantFilter);
                        $results['security_incidents'] += $incidentResult['synced'];
                    }

                    // Sync conditional access policies
                    if ($config['sync_conditional_access'] ?? true) {
                        $caResult = $this->syncConditionalAccessForTenant($tenantFilter);
                        $results['conditional_access'] += $caResult['synced'];
                    }

                    // Sync Defender status
                    if ($config['sync_defender'] ?? true) {
                        $defenderResult = $this->syncDefenderForTenant($tenantFilter);
                        $results['defender_states'] += $defenderResult['synced'];
                    }

                    // Sync Email & Exchange
                    if ($config['sync_email'] ?? true) {
                        $usageResult = $this->syncMailboxUsageForTenant($tenantFilter);
                        $results['mailbox_usage'] += $usageResult['synced'];

                        $protectionResult = $this->syncExchangeProtectionForTenant($tenantFilter);
                        $results['exchange_protection'] += $protectionResult['synced'];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Tenant {$tenantFilter}: {$e->getMessage()}";
                    Log::warning('CIPP: Error syncing tenant data', [
                        'integration_id' => $this->integration->integration_id,
                        'tenant' => $tenantFilter,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('CIPP: Sync completed', [
                'integration_id' => $this->integration->integration_id,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            Log::error('CIPP: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
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

            foreach ($tenants as $tenant) {
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

                    // Auto-match to existing customers
                    if ($integrationCustomer->wasRecentlyCreated) {
                        BatchAutoMatchCustomersJob::dispatch(
                            $this->integration->company_id,
                            [$integrationCustomer->integration_customer_id]
                        );
                    }

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Tenant {$tenant['displayName']}: {$e->getMessage()}";
                }
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
        $synced = 0;
        $errors = [];

        try {
            $users = $this->client->listMFAUsers($tenantFilter);

            foreach ($users as $user) {
                try {
                    $mapped = CIPPMapper::mapUser($user, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => 'user',
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => now(),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "User sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list users for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync licenses for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncLicensesForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $licenses = $this->client->listLicenses($tenantFilter);

            foreach ($licenses as $license) {
                try {
                    $mapped = CIPPMapper::mapLicense($license, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => 'license',
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => now(),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "License sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list licenses for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync security alerts for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncSecurityAlertsForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $alerts = $this->client->listSecurityAlerts($tenantFilter);

            foreach ($alerts as $alert) {
                try {
                    $mapped = CIPPMapper::mapSecurityAlert($alert, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => 'security_alert',
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => Carbon::parse($mapped['created_at'] ?? now()),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Security alert sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list security alerts for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync security incidents for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncSecurityIncidentsForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $incidents = $this->client->listSecurityIncidents($tenantFilter);

            foreach ($incidents as $incident) {
                try {
                    $mapped = CIPPMapper::mapSecurityIncident($incident, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => 'security_incident',
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => Carbon::parse($mapped['created_at'] ?? now()),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Security incident sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list security incidents for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync conditional access policies for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncConditionalAccessForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $policies = $this->client->listConditionalAccessPolicies($tenantFilter);

            foreach ($policies as $policy) {
                try {
                    $mapped = CIPPMapper::mapConditionalAccessPolicy($policy, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => 'conditional_access',
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => now(),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Conditional access sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list conditional access policies for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync Defender status for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncDefenderForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $devices = $this->client->listDefenderState($tenantFilter);

            foreach ($devices as $device) {
                try {
                    $mapped = CIPPMapper::mapDefenderState($device, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => 'defender_state',
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => Carbon::parse($mapped['last_seen'] ?? now()),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Defender state sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list Defender state for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync mailbox usage for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncMailboxUsageForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $usages = $this->client->listMailboxUsage($tenantFilter);

            foreach ($usages as $usage) {
                try {
                    $mapped = CIPPMapper::mapMailboxUsage($usage, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => IntegrationDataType::MAILBOX_USAGE,
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => now(),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Mailbox usage sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list mailbox usage for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Sync exchange protection for a specific tenant
     * 
     * @return array{synced: int, errors: array<string>}
     */
    protected function syncExchangeProtectionForTenant(string $tenantFilter): array
    {
        $synced = 0;
        $errors = [];

        try {
            $protections = $this->client->listExchangeProtection($tenantFilter);

            foreach ($protections as $protection) {
                try {
                    $mapped = CIPPMapper::mapExchangeProtection($protection, $tenantFilter);
                    
                    IntegrationData::create([
                        'company_id' => $this->integration->company_id,
                        'integration_id' => $this->integration->integration_id,
                        'data_type' => IntegrationDataType::EXCHANGE_PROTECTION,
                        'external_id' => $mapped['external_id'],
                        'data_timestamp' => now(),
                        'data_payload' => $mapped,
                        'imported_at' => now(),
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Exchange protection sync error: {$e->getMessage()}";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to list exchange protection for {$tenantFilter}: {$e->getMessage()}";
        }

        return ['synced' => $synced, 'errors' => $errors];
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

        return $data;
    }

    /**
     * Get the earliest date for which historical data is available
     */
    public function getAvailableHistoryRange(): ?Carbon
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
    public function getDisplayData(): array
    {
        $template = new TemplateBuilder();
        
        try {
            $tenants = $this->client->listTenants();
            $tenantCount = count($tenants);
            
            // Get summary stats
            $totalUsers = 0;
            $totalLicenses = 0;
            $activeAlerts = 0;
            
            // Sample first few tenants for stats
            $sampleTenants = array_slice($tenants, 0, 5);
            foreach ($sampleTenants as $tenant) {
                $tenantFilter = $tenant['defaultDomainName'] ?? null;
                if (!$tenantFilter) continue;
                
                try {
                    $users = $this->client->listMFAUsers($tenantFilter);
                    $totalUsers += count($users);
                    
                    $licenses = $this->client->listLicenses($tenantFilter);
                    $totalLicenses += count($licenses);
                    
                    $alerts = $this->client->listSecurityAlerts($tenantFilter);
                    $activeAlerts += count(array_filter($alerts, fn($a) => ($a['status'] ?? '') !== 'resolved'));
                } catch (\Exception $e) {
                    // Continue with other tenants
                }
            }

            $template->addSection('overview', 'Overview')
                ->addStat('overview', 'Managed Tenants', (string) $tenantCount, 'building')
                ->addStat('overview', 'Total Users (Sample)', (string) $totalUsers, 'users')
                ->addStat('overview', 'License Types', (string) $totalLicenses, 'key')
                ->addStat('overview', 'Active Alerts', (string) $activeAlerts, 'alert-triangle');

            // Add tenant list
            $template->addSection('tenants', 'Managed Tenants');
            foreach (array_slice($tenants, 0, 10) as $tenant) {
                $template->addListItem('tenants', [
                    'title' => $tenant['displayName'] ?? 'Unknown',
                    'subtitle' => $tenant['defaultDomainName'] ?? '',
                    'status' => 'active',
                ]);
            }

        } catch (\Exception $e) {
            $template->addSection('error', 'Error')
                ->addStat('error', 'Status', 'Connection Error', 'alert-circle');
        }

        return $template->build();
    }
}
