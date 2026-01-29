<?php

declare(strict_types=1);

namespace App\Integrations\AdminDroid;
use App\Integrations\Concerns\HasHeartbeat;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class AdminDroidIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    protected Integration $integration;
    protected AdminDroidClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new AdminDroidClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        $errors = [];
        $totalProcessed = 0;

        try {
            // Get organization info
            $orgs = $this->client->getOrganization();
            
            Log::info('AdminDroid Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
            ]);

            foreach ($orgs as $org) {
                try {
                    $standardized = AdminDroidMapper::mapOrganization($org);
                    $this->storeCustomer($standardized);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process organization: {$e->getMessage()}";
                }
            }

            // Sync users
            $users = $this->client->getUsers();
            Log::info('AdminDroid Integration: Syncing users', [
                'integration_id' => $this->integration->integration_id,
                'user_count' => count($users),
            ]);

            foreach ($users as $user) {
                try {
                    $userData = AdminDroidMapper::mapUser($user);
                    $this->storeHistoricalData($userData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process user: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('AdminDroid Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('AdminDroid Integration: Sync completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $totalProcessed,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    public function getCustomers(): array
    {
        $orgs = $this->client->getOrganization();
        return array_values(array_map(fn($org) => AdminDroidMapper::mapOrganization($org), $orgs));
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // Get sign-in activity
            $signIns = $this->client->getSignInActivity($startDate);
            
            foreach ($signIns as $signIn) {
                try {
                    $signInData = AdminDroidMapper::mapSignInActivity($signIn);
                    $data[] = $signInData;
                    $this->storeHistoricalData($signInData);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process sign-in: {$e->getMessage()}";
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

    public function getAvailableHistoryRange(): Carbon
    {
        return now()->subDays(30);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'AdminDroid',
            'description' => 'Microsoft 365 reporting and analytics via Graph API',
            'data_types' => [
                'admindroid_user',
                'admindroid_sign_in',
                'admindroid_m365_report',
                'admindroid_mailbox_usage',
                'admindroid_teams_usage',
            ],
            'supports_historical' => true,
            'historical_range_days' => 30,
        ];
    }

    public function disconnect(): void
    {
        Log::info('AdminDroid Integration: Disconnected', [
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
            'requests_per_minute' => 120,
            'requests_per_hour' => 2000,
        ];
    }

    /**
     * @param array<string, mixed> $customer
     */
    private function storeCustomer(array $customer): void
    {
        IntegrationCustomer::updateOrCreate(
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
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeHistoricalData(array $data): void
    {
        $payload = $data['payload'];
        $recordId = $payload['id'] ?? null;
        $dataType = $data['data_type'];
        
        if ($recordId) {
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $dataType)
                ->whereRaw("data_payload->>'id' = ?", [(string)$recordId])
                ->first();

            if ($existing) {
                $existing->update([
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
                return;
            }
        }

        IntegrationData::create([
            'company_id' => $this->integration->company_id,
            'integration_id' => $this->integration->integration_id,
            'integration_customer_id' => $data['integration_customer_id'] ?? null,
            'data_type' => $dataType,
            'data_timestamp' => $data['timestamp'],
            'data_payload' => $payload,
            'imported_at' => now(),
            'data_version' => 1,
        ]);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'tenant_id',
                'label' => 'Tenant ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Enter your Microsoft 365 Tenant ID',
                'help' => 'Your Microsoft 365 tenant identifier (GUID)',
            ],
            [
                'name' => 'client_id',
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Enter your Azure AD App Client ID',
                'help' => 'The Application (client) ID from Azure AD app registration',
            ],
            [
                'name' => 'client_secret',
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your Client Secret',
                'help' => 'The client secret from Azure AD app registration',
            ],
        ];
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('admindroid', 'AdminDroid');
        $builder->description('Microsoft 365 reporting and analytics');
        
        $builder->addSection('metrics', 'M365 Overview', 'grid')
            ->icon('BarChart2')
            ->dataPath('metrics')
            ->gridColumns(4)
            ->addMetric('total_users', 'Total Users', 'total_users', 'number', 'Users', 'blue')
            ->addMetric('active_users', 'Active Users', 'active_users', 'number', 'UserCheck', 'green')
            ->addMetric('sign_ins_today', 'Sign-ins Today', 'sign_ins_today', 'number', 'LogIn', 'purple')
            ->addMetric('failed_sign_ins', 'Failed Sign-ins', 'failed_sign_ins', 'number', 'AlertTriangle', 'red')
            ->end();

        $builder->addSection('users', 'Users', 'table')
            ->description('Microsoft 365 users')
            ->icon('Users')
            ->dataPath('users')
            ->tableColumns([
                ['key' => 'display_name', 'label' => 'Name', 'sortable' => true, 'filterable' => true],
                ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                ['key' => 'last_sign_in', 'label' => 'Last Sign-in', 'sortable' => true, 'format' => 'datetime'],
                [
                    'key' => 'account_enabled',
                    'label' => 'Status',
                    'sortable' => true,
                    'format' => 'boolean',
                    'trueLabel' => 'Active',
                    'falseLabel' => 'Disabled',
                ],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->end();
        
        return $builder->build();
    }
}
