<?php

namespace App\Integrations\Kemp;
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

class KempIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    protected Integration $integration;
    protected KempClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new KempClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $errors = [];
        $totalProcessed = 0;

        try {
            // Get system info to create/update the LoadMaster as a "customer"
            $systemInfo = $this->client->getSystemInfo();
            $baseUrl = $this->integration->credentials['api_url'] ?? '';
            
            $standardized = KempMapper::mapLoadMaster($systemInfo, $baseUrl);
            $this->storeCustomer($standardized);
            $totalProcessed++;

            // Sync Virtual Services
            $virtualServices = $this->client->getVirtualServices();
            Log::info('Kemp Integration: Syncing Virtual Services', [
                'integration_id' => $this->integration->integration_id,
                'vs_count' => count($virtualServices),
            ]);

            foreach ($virtualServices as $vs) {
                try {
                    $vsData = KempMapper::mapVirtualService($vs);
                    $this->storeHistoricalData($vsData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process VS: {$e->getMessage()}";
                }
            }

            // Sync Real Servers
            $realServers = $this->client->getRealServers();
            foreach ($realServers as $rs) {
                try {
                    $rsData = KempMapper::mapRealServer($rs);
                    $this->storeHistoricalData($rsData);
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to process RS: {$e->getMessage()}";
                }
            }

            // Sync Stats
            $stats = $this->client->getStats();
            $statsData = KempMapper::mapStats($stats);
            $this->storeHistoricalData($statsData);

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('Kemp Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Kemp Integration: Sync completed', [
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $systemInfo = $this->client->getSystemInfo();
        $baseUrl = $this->integration->credentials['api_url'] ?? '';
        return [KempMapper::mapLoadMaster($systemInfo, $baseUrl)];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // Get current stats snapshot
            $stats = $this->client->getStats();
            $statsData = KempMapper::mapStats($stats);
            $data[] = $statsData;
            $this->storeHistoricalData($statsData);

            // Get Virtual Services
            $virtualServices = $this->client->getVirtualServices();
            foreach ($virtualServices as $vs) {
                $vsData = KempMapper::mapVirtualService($vs);
                $data[] = $vsData;
                $this->storeHistoricalData($vsData);
            }

            // Get Real Servers
            $realServers = $this->client->getRealServers();
            foreach ($realServers as $rs) {
                $rsData = KempMapper::mapRealServer($rs);
                $data[] = $rsData;
                $this->storeHistoricalData($rsData);
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
        // Kemp provides current state only, no historical data
        return now();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Kemp',
            'description' => 'Kemp LoadMaster load balancer monitoring',
            'data_types' => [
                'kemp_stats',
                'kemp_virtual_service',
                'kemp_real_server',
            ],
            'supports_historical' => true,
            'historical_range_days' => 30,
        ];
    }

    public function disconnect(): void
    {
        Log::info('Kemp Integration: Disconnected', [
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

    /**
     * @return array<string, int>
     */
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
        
        // For stats, use date as unique key (one per day)
        if ($dataType === 'kemp_stats') {
            $dateKey = Carbon::now()->format('Y-m-d');
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $dataType)
                ->whereDate('data_timestamp', Carbon::today())
                ->first();

            if ($existing) {
                $existing->update([
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                ]);
                return;
            }
        } elseif ($recordId) {
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $dataType)
                ->whereRaw("data_payload->>'id' = ?", [(string)$recordId])
                ->whereDate('data_timestamp', Carbon::today())
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_url',
                'label' => 'LoadMaster URL',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'https://loadmaster.example.com',
                'help' => 'URL of your Kemp LoadMaster appliance (include https://)',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => false,
                'placeholder' => 'Enter your Kemp API key',
                'help' => 'API key from LoadMaster System Configuration > API',
            ],
            [
                'name' => 'username',
                'label' => 'Username',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'bal (default admin user)',
                'help' => 'LoadMaster admin username (if not using API key)',
            ],
            [
                'name' => 'password',
                'label' => 'Password',
                'type' => 'password',
                'required' => false,
                'placeholder' => 'Enter password',
                'help' => 'LoadMaster admin password (if not using API key)',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('kemp', 'Kemp LoadMaster');
        $builder->description('Load balancer monitoring and status');
        
        $builder->addSection('metrics', 'System Stats', 'grid')
            ->icon('Activity')
            ->dataPath('metrics')
            ->gridColumns(4)
            ->addMetric('cpu_usage', 'CPU Usage', 'cpu_usage', 'percent', 'Cpu', 'blue')
            ->addMetric('memory_used', 'Memory Used', 'memory_used', 'bytes', 'HardDrive', 'purple')
            ->addMetric('total_connections', 'Total Connections', 'total_connections', 'number', 'Network', 'green')
            ->addMetric('connections_per_sec', 'Conn/Sec', 'connections_per_sec', 'number', 'Zap', 'orange')
            ->end();

        $builder->addSection('virtual_services', 'Virtual Services', 'table')
            ->description('Configured virtual services')
            ->icon('Server')
            ->dataPath('virtual_services')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true],
                ['key' => 'address', 'label' => 'Address', 'sortable' => true],
                ['key' => 'port', 'label' => 'Port', 'sortable' => true],
                ['key' => 'active_connections', 'label' => 'Active Conn', 'sortable' => true],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'sortable' => true,
                    'format' => 'status',
                    'colorMap' => [
                        'Up' => 'success',
                        'Down' => 'error',
                        'Disabled' => 'warning',
                    ],
                ],
            ])
            ->sortable()
            ->filterable()
            ->paginated(true, 25)
            ->end();
        
        return $builder->build();
    }
}
