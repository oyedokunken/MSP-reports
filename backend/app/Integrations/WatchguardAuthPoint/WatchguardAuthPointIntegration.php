<?php

namespace App\Integrations\WatchguardAuthPoint;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WatchguardAuthPointIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected WatchguardAuthPointClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new WatchguardAuthPointClient($integration->credentials ?? [], $rateLimiter, $integration);
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
            $accountId = $this->integration->credentials['account_id'] ?? '';
            
            // Create/update account as customer
            $accountData = WatchguardAuthPointMapper::mapAccount([], $accountId);
            $this->storeCustomer($accountData);
            $totalProcessed++;

            // Sync users
            $users = $this->client->getUsers();
            Log::info('WatchGuard AuthPoint: Syncing users', [
                'integration_id' => $this->integration->integration_id,
                'user_count' => count($users),
            ]);

            foreach ($users as $user) {
                try {
                    $userData = WatchguardAuthPointMapper::mapUser($user);
                    $this->storeHistoricalData($userData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process user: {$e->getMessage()}";
                }
            }

            // Sync resources
            $resources = $this->client->getResources();
            foreach ($resources as $resource) {
                try {
                    $resourceData = WatchguardAuthPointMapper::mapResource($resource);
                    $this->storeHistoricalData($resourceData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process resource: {$e->getMessage()}";
                }
            }

            // Sync enrollments
            $enrollments = $this->client->getUserEnrollments();
            foreach ($enrollments as $enrollment) {
                try {
                    $enrollmentData = WatchguardAuthPointMapper::mapUserEnrollment($enrollment);
                    $this->storeHistoricalData($enrollmentData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process enrollment: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('WatchGuard AuthPoint: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('WatchGuard AuthPoint: Sync completed', [
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
        $accountId = $this->integration->credentials['account_id'] ?? '';
        return [WatchguardAuthPointMapper::mapAccount([], $accountId)];
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // Get authentication events
            $events = $this->client->getAuthenticationEvents($startDate);
            
            foreach ($events as $event) {
                try {
                    $eventData = WatchguardAuthPointMapper::mapAuthenticationEvent($event);
                    $data[] = $eventData;
                    $this->storeHistoricalData($eventData);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process auth event: {$e->getMessage()}";
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
            'name' => 'WatchGuard AuthPoint',
            'description' => 'WatchGuard AuthPoint multi-factor authentication',
            'data_types' => [
                'watchguard_user',
                'watchguard_authentication_event',
                'watchguard_user_enrollment',
                'watchguard_resource',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        Log::info('WatchGuard AuthPoint: Disconnected', [
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
                'name' => 'api_url',
                'label' => 'API URL',
                'type' => 'text',
                'required' => false,
                'default' => 'https://api.usa.cloud.watchguard.com',
                'placeholder' => 'https://api.usa.cloud.watchguard.com',
                'help' => 'WatchGuard Cloud API URL (varies by region)',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key (Client ID)',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your WatchGuard API key',
                'help' => 'Your WatchGuard Cloud API key from Managed Access page',
            ],
            [
                'name' => 'api_secret',
                'label' => 'API Secret (Client Secret)',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your API secret',
                'help' => 'Your WatchGuard Cloud API secret',
            ],
            [
                'name' => 'account_id',
                'label' => 'Account ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Enter your Account ID',
                'help' => 'Your WatchGuard account identifier',
            ],
        ];
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('watchguardauthpoint', 'WatchGuard AuthPoint');
        $builder->description('Multi-factor authentication monitoring');
        
        $builder->addSection('metrics', 'Overview', 'grid')
            ->icon('Key')
            ->dataPath('metrics')
            ->gridColumns(4)
            ->addMetric('total_users', 'Total Users', 'total_users', 'number', 'Users', 'blue')
            ->addMetric('total_resources', 'Resources', 'total_resources', 'number', 'Shield', 'purple')
            ->addMetric('auth_success', 'Auth Success', 'auth_success_rate', 'percent', 'CheckCircle', 'green')
            ->addMetric('auth_failures', 'Failures (24h)', 'auth_failures', 'number', 'XCircle', 'red')
            ->end();

        $builder->addSection('users', 'Users', 'table')
            ->description('Enrolled MFA users')
            ->icon('Users')
            ->dataPath('users')
            ->tableColumns([
                ['key' => 'username', 'label' => 'Username', 'sortable' => true, 'filterable' => true],
                ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                ['key' => 'last_login', 'label' => 'Last Login', 'sortable' => true, 'format' => 'datetime'],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'sortable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'Active' => 'success',
                        'Inactive' => 'error',
                        'Pending' => 'warning',
                    ],
                ],
            ])
            ->sortable()
            ->filterable()
            ->paginated(25)
            ->end();
        
        return $builder->build();
    }
}
