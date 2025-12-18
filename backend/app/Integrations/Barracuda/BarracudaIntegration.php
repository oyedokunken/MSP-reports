<?php

namespace App\Integrations\Barracuda;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
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

class BarracudaIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
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

    protected $mappedDuringSync = [];

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

    private function processUnmappedCustomers(): array
    {
        // Dispatch single batch job to process all unmapped customers
        BatchAutoMatchCustomersJob::dispatch($this->integration);
        return []; // Batch job handles mapping asynchronously
    }

    public function getCustomers(): array
    {
        $barracudaCustomers = $this->client->getCustomers();
        return array_map(fn($customer) => BarracudaMapper::mapCustomer($customer), $barracudaCustomers);
    }

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

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(90);
    }

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

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

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
}
