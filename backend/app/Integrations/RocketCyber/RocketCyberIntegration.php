<?php

namespace App\Integrations\RocketCyber;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RocketCyberIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    protected Integration $integration;
    protected RocketCyberClient $client;
    protected CustomerMappingService $mappingService;
    protected array $mappedDuringSync = [];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new RocketCyberClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            $accounts = $this->client->getAccounts();
            
            Log::info('RocketCyber Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'account_count' => count($accounts),
            ]);

            foreach ($accounts as $account) {
                try {
                    $standardized = RocketCyberMapper::mapAccount($account);
                    $this->storeCustomer($standardized);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $accountId = $account['id'] ?? $account['accountId'] ?? 'unknown';
                    $errors[] = "Failed to process account {$accountId}: {$e->getMessage()}";
                    Log::error('RocketCyber Integration: Failed to process account', [
                        'integration_id' => $this->integration->integration_id,
                        'account_id' => $accountId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('RocketCyber Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->processUnmappedCustomers();

        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        Log::info('RocketCyber Integration: Sync completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $mappedCount,
            'records_new' => $unmappedCount,
            'customers_mapped' => $this->mappedDuringSync,
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function processUnmappedCustomers(): array
    {
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            Log::info('RocketCyber: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
        return [];
    }

    public function getCustomers(): array
    {
        $accounts = $this->client->getAccounts();
        return array_map(fn($account) => RocketCyberMapper::mapAccount($account), $accounts);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // Get security incidents
            $incidents = $this->client->getSecurityIncidents(null, $startDate);
            
            foreach ($incidents as $incident) {
                try {
                    $standardized = RocketCyberMapper::mapSecurityIncident($incident);
                    $data[] = $standardized;
                    $this->storeHistoricalData($standardized);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process incident: {$e->getMessage()}";
                }
            }

            // Get threat detections
            $threats = $this->client->getThreatDetections(null, $startDate);
            
            foreach ($threats as $threat) {
                try {
                    $standardized = RocketCyberMapper::mapThreatDetection($threat);
                    $data[] = $standardized;
                    $this->storeHistoricalData($standardized);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process threat: {$e->getMessage()}";
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
            'name' => 'RocketCyber',
            'description' => 'RocketCyber managed SOC security monitoring',
            'data_types' => [
                'rocketcyber_account',
                'rocketcyber_security_incident',
                'rocketcyber_threat_detection',
                'rocketcyber_agent',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        Log::info('RocketCyber Integration: Disconnected', [
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

        if (!$integrationCustomer->customer_id && $this->integration->is_source_of_truth) {
            $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'RocketCyber');
        }
    }

    private function storeHistoricalData(array $data): void
    {
        $payload = $data['payload'];
        $recordId = $payload['id'] ?? null;
        
        if ($recordId) {
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $data['data_type'])
                ->whereRaw("data_payload->>'id' = ?", [(string)$recordId])
                ->first();

            if ($existing) {
                $existing->update([
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
            } else {
                IntegrationData::create([
                    'company_id' => $this->integration->company_id,
                    'integration_id' => $this->integration->integration_id,
                    'integration_customer_id' => $data['integration_customer_id'] ?? null,
                    'data_type' => $data['data_type'],
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                    'data_version' => 1,
                ]);
            }
        } else {
            IntegrationData::create([
                'company_id' => $this->integration->company_id,
                'integration_id' => $this->integration->integration_id,
                'integration_customer_id' => $data['integration_customer_id'] ?? null,
                'data_type' => $data['data_type'],
                'data_timestamp' => $data['timestamp'],
                'data_payload' => $payload,
                'imported_at' => now(),
                'data_version' => 1,
            ]);
        }
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your RocketCyber API key',
                'help' => 'Your RocketCyber API key from the partner portal',
            ],
            [
                'name' => 'account_id',
                'label' => 'Account ID',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter your RocketCyber Account ID (optional)',
                'help' => 'Leave empty to sync all accounts',
            ],
        ];
    }

    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('rocketcyber', 'RocketCyber');
        $builder->description('Managed SOC security monitoring');
        
        $builder->addSection('customers', 'Accounts', 'table')
            ->description('Synced accounts from RocketCyber')
            ->icon('Shield')
            ->dataPath('customers')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Account Name', 'sortable' => true, 'filterable' => true, 'width' => '300px'],
                ['key' => 'agent_count', 'label' => 'Agents', 'sortable' => true],
                [
                    'key' => 'active',
                    'label' => 'Status',
                    'sortable' => true,
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
        
        $builder->addSection('metrics', 'Overview', 'grid')
            ->icon('BarChart3')
            ->dataPath('metrics')
            ->gridColumns(3)
            ->addMetric('total_accounts', 'Total Accounts', 'total_accounts', 'number', 'Users', 'blue')
            ->addMetric('total_incidents', 'Incidents', 'total_incidents', 'number', 'AlertTriangle', 'red')
            ->addMetric('total_agents', 'Agents', 'total_agents', 'number', 'Server', 'green')
            ->end();
        
        return $builder->build();
    }
}
