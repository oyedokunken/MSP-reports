<?php

namespace App\Integrations\SaaS;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Integrations\Concerns\HasHeartbeat;
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
    use HasHeartbeat;
    
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

    protected function saveSyncState(string $phase, int $processed, int $total): void
    {
        DB::table('integrations')
            ->where('integration_id', $this->integration->integration_id)
            ->update([
                'sync_state' => json_encode([
                    'phase' => $phase,
                    'offset' => $processed,
                    'total' => $total,
                    'timestamp' => now()->timestamp,
                ])
            ]);
    }

    public function processBatch(string $type, array $items): void
    {
         if ($type === 'customers') {
            foreach ($items as $customer) {
                // Heartbeat per item
                $this->sendHeartbeat();
                
                try {
                    // 1. Map to Standard Format
                    $mappedCustomer = SaaSMapper::mapCustomer($customer);

                    // Validate required fields
                    if (empty($mappedCustomer['external_id']) || empty($mappedCustomer['name'])) {
                        continue;
                    }

                    $integrationCustomer = DB::transaction(function () use ($mappedCustomer) {
                        return IntegrationCustomer::updateOrCreate(
                            [
                                'integration_id' => $this->integration->integration_id,
                                'external_id' => $mappedCustomer['external_id'],
                            ],
                            [
                                'name' => $mappedCustomer['name'],
                                'metadata' => $mappedCustomer['metadata'],
                                'active' => $mappedCustomer['active'],
                                'last_seen_at' => now(),
                            ]
                        );
                    });
                     
                     // 2. Fetch Events (Sync Logic)
                     // Fetch recent security events for this customer (last 24 hours)
                     $events = $this->client->getEvents([
                        'customerId' => $mappedCustomer['external_id'],
                        'start' => now()->subHours(24)->format('Y-m-d'),
                        'end' => now()->format('Y-m-d'),
                        'size' => 100, // Reasonable batch for regular sync
                    ]);
                    
                    foreach ($events as $event) {
                         try {
                                $mapped = SaaSMapper::mapEvent($event, $integrationCustomer->integration_customer_id);
                                $this->storeHistoricalData($mapped);
                            } catch (\Exception $e) {
                                \Log::warning('SaaS Alerts batch: Failed to process event', [
                                    'customer_id' => $customer['id'],
                                    'event_id' => $event['eventId'] ?? $event['_id'] ?? 'unknown',
                                    'error' => $e->getMessage(),
                                ]);
                            }
                    }

                } catch (\Exception $e) {
                    \Log::error('SaaS Alerts batch: Failed to process customer', [
                        'customer_id' => $customer['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }
         }
    }

    public function sync(): array
    {
        $errors = [];
        $this->mappedDuringSync = [];
        $totalProcessed = 0;
        $totalEvents = 0; // Not strictly tracked in sync() return anymore as it happens in batches

        try {
             // 1. Checkpoint: Resume
            $state = json_decode($this->integration->sync_state, true);
            $phase = $state['phase'] ?? 'customers';
            $resumeOffset = $state['offset'] ?? 0;
            
             // Fetch customers
            $saasCustomers = $this->client->getCustomers();
            
            if ($phase === 'customers') {
                $totalCustomers = count($saasCustomers);
                
                if ($resumeOffset > 0) {
                     $saasCustomers = array_slice($saasCustomers, $resumeOffset);
                     $totalProcessed = $resumeOffset;
                }
                
                // 1. Synchronous Customer Sync (Match Datto Pattern)
                foreach ($saasCustomers as $customer) {
                    try {
                        $standardized = SaaSMapper::mapCustomer($customer);
                        $this->storeCustomer($standardized);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                         // Gracefully handle individual failures
                         Log::warning('SaaS Sync: Customer store failed', ['error' => $e->getMessage()]);
                    }
                }
                
                // 2. Auto-Map Logic (Fetch unmapped -> Auto Map)
                $this->processUnmappedCustomers();

                Log::info('SaaS Alerts sync: Customers synced, dispatching event batches', [
                    'count' => count($saasCustomers),
                    'total_processed' => $totalProcessed,
                ]);
                
                // 3. Dispatch Event Fetching Batches
                 collect($saasCustomers)->chunk(10)->each(function ($chunk) use ($totalCustomers) {
                     // Note: processBatch will still verify customer existence/update metadata
                     \App\Jobs\ProcessIntegrationBatchJob::dispatch($this->integration, 'customers', $chunk->toArray(), $this->currentSyncLogId);
                     $this->saveSyncState('customers', 0, $totalCustomers); // Tracking handled partly by batches
                });
            }

             $this->integration->update(['sync_state' => null]);
             
             // Initial cache update
             $this->updateStatsCache();

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            Log::error('SaaS Alerts sync: Failed', ['error' => $e->getMessage()]);
        }

        return [
            'records_synced' => $totalProcessed,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }
    
    private function updateStatsCache(): void
    {
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
    }

    private function processUnmappedCustomers(): array
    {
        // Only dispatch if there are actually unmapped customers
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();
        
        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            \Log::info('SaaS: Dispatched auto-match job', [
                'integration_id' => $this->integration->integration_id,
                'unmapped_count' => $unmappedCount,
            ]);
        }
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

            foreach ($integrationCustomers as $index => $integrationCustomer) {
                if ($index % 5 === 0) $this->sendHeartbeat();
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
        $payload = $data['payload'];
        $eventId = $payload['event_id'] ?? null;
        
        if ($eventId) {
            // UPSERT: Use event_id as unique identifier to prevent duplicates
            $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                ->where('data_type', $data['data_type'])
                ->whereRaw("data_payload->>'event_id' = ?", [$eventId])
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
            // Fallback to insert for events without unique ID
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
