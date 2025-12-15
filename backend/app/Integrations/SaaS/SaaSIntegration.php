<?php

namespace App\Integrations\SaaS;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\AutoMatchCustomersJob;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SaaSIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    
    protected Integration $integration;
    protected SaaSClient $client;
    protected CustomerMappingService $mappingService;
    protected array $mappedDuringSync = [];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new SaaSClient($integration->credentials ?? [], $rateLimiter, $integration);
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'placeholder' => 'Enter your SaaS Alerts API key',
                'help' => 'Found in SaaS Alerts Partner Portal. Used in api_key header for authentication.',
            ],
            [
                'name' => 'partner_id',
                'label' => 'Partner ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'eu1-Y675j1foFxorQyATsx9U',
                'help' => 'Your SaaS Alerts Partner ID. Found in Partner Portal.',
            ],
            [
                'name' => 'base_url',
                'label' => 'Environment',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1' => 'Production',
                    'https://us-central1-saas-alerts-qa.cloudfunctions.net/reportApi/api/v1' => 'QA',
                    'https://us-central1-saas-alerts-dev.cloudfunctions.net/reportApi/api/v1' => 'Development',
                ],
                'default' => 'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1',
                'help' => 'Select the SaaS Alerts API environment',
            ],
        ];
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
        $totalEvents = 0;

        try {
            // Fetch customers
            $saasCustomers = $this->client->getCustomers();
            
            Log::info('SaaS Alerts sync: Retrieved customers', [
                'count' => count($saasCustomers),
                'integration_id' => $this->integration->integration_id,
            ]);

            $totalCustomers = count($saasCustomers);
            
            // Process customers in chunks
            collect($saasCustomers)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed, &$totalEvents, $totalCustomers) {
                foreach ($chunk as $customer) {
                    try {
                        $standardized = SaaSMapper::mapCustomer($customer);
                        $this->storeCustomer($standardized);
                        $totalProcessed++;
                        
                        // Fetch recent security events for this customer (last 24 hours)
                        // Similar to Datto's 1-hour redundant window pattern
                        try {
                            $integrationCustomer = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                                ->where('external_id', $customer['id'])
                                ->first();
                            
                            if ($integrationCustomer) {
                                $events = $this->client->getEvents([
                                    'customerId' => $customer['id'],
                                    'start' => now()->subHours(24)->format('Y-m-d'),
                                    'end' => now()->format('Y-m-d'),
                                    'size' => 100, // Reasonable batch for regular sync
                                ]);
                                
                                foreach ($events as $event) {
                                    try {
                                        $mapped = SaaSMapper::mapEvent($event, $integrationCustomer->integration_customer_id);
                                        $this->storeHistoricalData($mapped);
                                        $totalEvents++;
                                    } catch (\Exception $e) {
                                        Log::warning('SaaS Alerts sync: Failed to process event', [
                                            'customer_id' => $customer['id'],
                                            'event_id' => $event['eventId'] ?? $event['_id'] ?? 'unknown',
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('SaaS Alerts sync: Failed to fetch events for customer', [
                                'customer_id' => $customer['id'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process customer {$customer['id']}: {$e->getMessage()}";
                        Log::error('SaaS Alerts sync: Failed to process customer', [
                            'integration_id' => $this->integration->integration_id,
                            'customer_id' => $customer['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                Log::info('SaaS Alerts sync: Processed chunk', [
                    'integration_id' => $this->integration->integration_id,
                    'processed' => $totalProcessed,
                    'total' => $totalCustomers,
                    'events' => $totalEvents,
                ]);
            });

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('SaaS Alerts sync: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Process unmapped customers
        $additionalMapped = $this->processUnmappedCustomers();
        $mappedCustomers = array_merge($this->mappedDuringSync, $additionalMapped);

        // Update cache with customer counts
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

        Log::info('SaaS Alerts sync: Completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'total_events' => $totalEvents,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $totalProcessed,
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
        $saasCustomers = $this->client->getCustomers();
        return array_map(fn($customer) => SaaSMapper::mapCustomer($customer), $saasCustomers);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // NO MORE $data = [] bucket! Just track stats
        $errors = [];
        $totalProcessed = 0;

        try {
            // Get all mapped customers for this integration
            $integrationCustomers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->whereNotNull('customer_id')
                ->get();

            Log::info('SaaS Alerts historical: Starting event import', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'customer_count' => $integrationCustomers->count(),
            ]);

            foreach ($integrationCustomers as $integrationCustomer) {
                try {
                    // Fetch events for this customer
                    $events = $this->client->getEvents([
                        'customerId' => $integrationCustomer->external_id,
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d'),
                        'size' => 1000, // Larger batch for historical import
                    ]);

                    // Process events in chunks
                    collect($events)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed, $integrationCustomer) {
                        foreach ($chunk as $event) {
                            try {
                                $standardized = SaaSMapper::mapEvent($event, $integrationCustomer->integration_customer_id);
                                $this->storeHistoricalData($standardized);
                                $totalProcessed++;
                            } catch (\Exception $e) {
                                $errors[] = "Failed to process event: {$e->getMessage()}";
                                Log::error('SaaS Alerts historical: Failed to process event', [
                                    'integration_id' => $this->integration->integration_id,
                                    'customer_id' => $integrationCustomer->external_id,
                                    'event_id' => $event['eventId'] ?? $event['_id'] ?? 'unknown',
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    });

                    Log::info('SaaS Alerts historical: Processed customer events', [
                        'integration_id' => $this->integration->integration_id,
                        'customer_id' => $integrationCustomer->external_id,
                        'customer_name' => $integrationCustomer->name,
                        'events_count' => count($events),
                    ]);

                } catch (\Exception $e) {
                    $errors[] = "Failed to fetch events for customer {$integrationCustomer->name}: {$e->getMessage()}";
                    Log::error('SaaS Alerts historical: Failed to fetch customer events', [
                        'integration_id' => $this->integration->integration_id,
                        'customer_id' => $integrationCustomer->external_id,
                        'customer_name' => $integrationCustomer->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('SaaS Alerts historical: Completed', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_processed' => $totalProcessed,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            Log::error('SaaS Alerts historical: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(365);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'SaaS Alerts',
            'description' => 'SaaS Alerts - Security monitoring and alerting for SaaS applications',
            'data_types' => [
                'customers',
                'users',
                'security_events',
                'billing',
                'devices',
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        // Clean up any cached data
        $cacheKey = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($cacheKey);
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
            'requests_per_minute' => 100,
            'requests_per_hour' => 2000,
        ];
    }

    private function storeCustomer(array $customer): void
    {
        // Validate required fields
        if (empty($customer['external_id']) || empty($customer['name'])) {
            Log::warning('SaaS Alerts: Skipping customer with missing required fields', [
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
        IntegrationData::create([
            'company_id' => $this->integration->company_id,
            'integration_id' => $this->integration->integration_id,
            'integration_customer_id' => $data['integration_customer_id'],
            'data_type' => $data['data_type'],
            'data_timestamp' => $data['timestamp'],
            'data_payload' => $data['payload'],
            'imported_at' => now(),
            'data_version' => 1,
        ]);
    }
}
