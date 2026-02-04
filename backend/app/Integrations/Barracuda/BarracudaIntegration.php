<?php

declare(strict_types=1);

namespace App\Integrations\Barracuda;
use App\Integrations\Concerns\HasHeartbeat;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Concerns\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class BarracudaIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;
    protected Integration $integration;
    protected BarracudaClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new BarracudaClient($integration->credentials, $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /** @var array<string, mixed> */
    protected $mappedDuringSync = [];

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;

        try {
            // Get all customers from Barracuda
            $barracudaCustomers = $this->client->getCustomers();
            
            Log::info('Barracuda Integration: Starting sync', [
                'integration_id' => $this->integration->integration_id,
                'customer_count' => count($barracudaCustomers),
            ]);

            foreach ($barracudaCustomers as $customer) {
                try {
                    // Map to standardized format
                    $standardized = BarracudaMapper::mapCustomer($customer);

                    // Store in database
                    $this->storeCustomer($standardized);
                    $totalProcessed++;

                } catch (\Exception $e) {
                    $customerId = $customer['id'] ?? 'unknown';
                    $errors[] = "Failed to process customer {$customerId}: {$e->getMessage()}";
                    Log::error('Barracuda Integration: Failed to process customer', [
                        'integration_id' => $this->integration->integration_id,
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('Barracuda Integration: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Process existing unmapped customers from database
        $additionalMapped = $this->processUnmappedCustomers();
        $mappedCustomers = array_merge($this->mappedDuringSync, $additionalMapped);

        // Count mapped and unmapped customers AFTER sync
        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        Log::info('Barracuda Integration: Sync completed', [
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

    /**
     * @return array<string, mixed>
     */
    private function processUnmappedCustomers(): array
    {
        // Only dispatch if there are actually unmapped customers
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            \Log::info('Barracuda: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
        return []; // Batch job handles mapping asynchronously
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        $barracudaCustomers = $this->client->getCustomers();
        return array_map(fn($customer) => BarracudaMapper::mapCustomer($customer), $barracudaCustomers);
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            $securityData = $this->client->getSecurityData($startDate, $endDate);

            foreach ($securityData as $record) {
                try {
                    $standardized = BarracudaMapper::mapSecurityData($record);
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

    public function getAvailableHistoryRange(): Carbon
    {
        return now()->subDays(90);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Barracuda',
            'description' => 'Barracuda Email Security Platform',
            'data_types' => [
                'email_security',
                'spam_filtering',
                'compliance_reports',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    public function disconnect(): void
    {
        Log::info('Barracuda Integration: Disconnected', [
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
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'client_id',
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'Enter your Barracuda Client ID',
                'help' => 'Found in your Barracuda account API settings',
            ],
            [
                'name' => 'client_secret',
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your Barracuda Client Secret',
                'help' => 'Keep this secret secure',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $customer
     */
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

        // Refresh to get latest customer_id value
        $integrationCustomer->refresh();

        // Batch job processes all unmapped customers at end of sync
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeHistoricalData(array $data): void
    {
        $payload = $data['payload'];
        $recordId = $payload['record_id'] ?? $payload['id'] ?? null;
        
        if ($recordId) {
            // UPSERT: Use record_id + data_type as unique key
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
                    'integration_customer_id' => $data['integration_customer_id'],
                    'data_type' => $data['data_type'],
                    'data_timestamp' => $data['timestamp'],
                    'data_payload' => $payload,
                    'imported_at' => now(),
                    'data_version' => 1,
                ]);
            }
        } else {
            // Fallback for data without unique ID
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
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array
    {
        $builder = new \App\Integrations\DisplayTemplate\TemplateBuilder('barracuda', 'Barracuda');
        $builder->description('Email security and spam filtering');
        
        $builder->addSection('customers', 'Customers', 'table')
            ->description('Synced customers from Barracuda')
            ->icon('Users')
            ->dataPath('customers')
            ->tableColumns([
                ['key' => 'name', 'label' => 'Customer Name', 'sortable' => true, 'filterable' => true, 'width' => '300px'],
                ['key' => 'external_id', 'label' => 'External ID', 'sortable' => true],
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
            ->paginated(true, 25)
            ->end();
        
        $builder->addSection('metrics', 'Overview', 'grid')
            ->icon('BarChart3')
            ->dataPath('metrics')
            ->gridColumns(3)
            ->addMetric('total_customers', 'Total Customers', 'total_customers', 'number', 'Users', 'blue')
            ->addMetric('active_customers', 'Active', 'active_customers', 'number', 'CheckCircle', 'green')
            ->addMetric('inactive_customers', 'Inactive', 'inactive_customers', 'number', 'XCircle', 'red')
            ->end();
        
        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreviewData(?int $integrationCustomerId = null): array
    {
        try {
            $query = IntegrationCustomer::where('integration_id', $this->integration->integration_id);
            
            if ($integrationCustomerId) {
                $query->where('integration_customer_id', $integrationCustomerId);
            }
            
            $customers = $query->get();
            
            $mappedCustomers = $customers->map(function($customer) {
                return [
                    'id' => $customer->integration_customer_id,
                    'name' => $customer->name,
                    'external_id' => $customer->external_id,
                    'active' => $customer->active ? 'Active' : 'Inactive',
                ];
            })->toArray();

            $totalCustomers = $customers->count();
            $activeCustomers = $customers->where('active', true)->count();
            $inactiveCustomers = $totalCustomers - $activeCustomers;

            return [
                'success' => true,
                'data' => [
                    'customers' => $mappedCustomers,
                    'metrics' => [
                        'total_customers' => $totalCustomers,
                        'active_customers' => $activeCustomers,
                        'inactive_customers' => $inactiveCustomers,
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
