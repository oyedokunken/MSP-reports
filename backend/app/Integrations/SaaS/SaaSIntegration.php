<?php

namespace App\Integrations\SaaS;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Traits\HasDefaultPreview;
use App\Integrations\Concerns\HasHeartbeat;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Jobs\BatchAutoMatchCustomersJob;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaaSIntegration implements IntegrationInterface
{
    use HasDefaultPreview;
    use HasHeartbeat;

    protected Integration $integration;
    protected SaaSClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new SaaSClient(
            $integration->credentials ?? [],
            app(\App\Services\RateLimiter::class),
            $integration
        );
        $this->mappingService = app(CustomerMappingService::class);
    }

    public function getCredentialFields(): array
    {
        return [
            [
                'name'        => 'api_key',
                'label'       => 'API Key',
                'type'        => 'password',
                'required'    => true,
                'placeholder' => 'Enter your SaaS Alerts API key',
                'help'        => 'Found in SaaS Alerts Partner Portal. Used in the api_key header.',
            ],
            [
                'name'        => 'partner_id',
                'label'       => 'Partner ID',
                'type'        => 'text',
                'required'    => true,
                'placeholder' => 'eu1-Y675j1foFxorQyATsx9U',
                'help'        => 'Your SaaS Alerts Partner ID from the portal.',
            ],
            [
                'name'     => 'base_url',
                'label'    => 'Environment',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1' => 'Production',
                    'https://us-central1-saas-alerts-qa.cloudfunctions.net/reportApi/api/v1'    => 'QA',
                    'https://us-central1-saas-alerts-dev.cloudfunctions.net/reportApi/api/v1'   => 'Development',
                ],
                'default' => 'https://us-central1-the-byway-248217.cloudfunctions.net/reportApi/api/v1',
                'help'    => 'Select the target SaaS Alerts environment',
            ],
        ];
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * Full sync: customers only (events handled by historical import)
     */
    public function sync(): array
    {
        $errors = [];
        $processedCustomers = 0;

        try {
            // Sync all customers
            $saasCustomers = $this->client->getCustomers();

            foreach ($saasCustomers as $customer) {
                try {
                    $mapped = SaaSMapper::mapCustomer($customer);
                    if (empty($mapped['external_id']) || empty($mapped['name'])) {
                        continue;
                    }

                    $this->storeCustomer($mapped);
                    $processedCustomers++;
                } catch (Throwable $e) {
                    $errors[] = "Customer sync failed: {$e->getMessage()}";
                    Log::warning('SaaS Alerts sync - customer failed', [
                        'error' => $e->getMessage(),
                        'customer' => $customer['id'] ?? 'unknown',
                    ]);
                }
            }

            // Auto-match unmapped customers
            $this->processUnmappedCustomers();

            Log::info('SaaS Alerts full sync completed', [
                'customers_processed' => $processedCustomers,
                'errors'              => count($errors),
            ]);

            $this->updateStatsCache();
        } catch (Throwable $e) {
            $errors[] = "Sync orchestration failed: {$e->getMessage()}";
            Log::error('SaaS Alerts sync failed fatally', ['error' => $e->getMessage()]);
        }

        return [
            'records_synced' => $processedCustomers,
            'records_new'    => 0,
            'records_failed' => count($errors),
            'errors'         => $errors,
        ];
    }

    /**
     * Process batch of items (required by framework)
     */
    public function processBatch(string $type, array $items): void
    {
        if ($type === 'customers') {
            foreach ($items as $customer) {
                $this->sendHeartbeat();
                
                try {
                    $mapped = SaaSMapper::mapCustomer($customer);
                    if (!empty($mapped['external_id']) && !empty($mapped['name'])) {
                        $this->storeCustomer($mapped);
                    }
                } catch (Throwable $e) {
                    Log::warning('SaaS batch: Failed to process customer', [
                        'customer_id' => $customer['id'] ?? 'unknown',
                        'error'       => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Fetch and store historical events for all mapped customers
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        // Increase memory for large historical payloads
        ini_set('memory_limit', '512M');

        $errors = [];
        $totalEvents = 0;

        $customers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->get();

        Log::info('SaaS Alerts historical sync started', [
            'integration_id' => $this->integration->integration_id,
            'customers'      => $customers->count(),
            'range'          => $startDate->toDateString() . ' → ' . $endDate->toDateString(),
        ]);

        foreach ($customers as $index => $intCustomer) {
            if ($index % 5 === 0) {
                $this->sendHeartbeat();
            }

            try {
                // Use getAllEvents for complete data with scroll pagination
                $events = $this->client->getAllEvents([
                    'customerId' => $intCustomer->external_id,
                    'start'      => $startDate->format('Y-m-d'),
                    'end'        => $endDate->format('Y-m-d'),
                    'size'       => 500,
                ]);

                collect($events)->chunk(200)->each(function ($chunk) use (
                    $intCustomer,
                    &$totalEvents,
                    &$errors
                ) {
                    $batch = [];

                    foreach ($chunk as $event) {
                        try {
                            $mapped = SaaSMapper::mapEvent($event, $intCustomer->integration_customer_id);

                            $batch[] = [
                                'company_id'              => $this->integration->company_id,
                                'integration_id'          => $this->integration->integration_id,
                                'integration_customer_id' => $mapped['integration_customer_id'],
                                'external_id'             => $mapped['external_id'],
                                'data_type'               => $mapped['data_type'],
                                'data_timestamp'          => $mapped['timestamp']->format('Y-m-d H:i:s'),
                                'data_payload'            => json_encode($mapped['payload']),
                                'imported_at'             => now()->toDateTimeString(),
                                'data_version'            => 1,
                            ];

                            $totalEvents++;
                        } catch (Throwable $e) {
                            $errors[] = "Event mapping failed for customer {$intCustomer->name}: {$e->getMessage()}";
                        }
                    }

                    if (!empty($batch)) {
                        try {
                            IntegrationData::upsert(
                                $batch,
                                ['integration_id', 'data_type', 'external_id', 'data_timestamp'],
                                ['data_payload', 'imported_at', 'integration_customer_id']
                            );
                        } catch (Throwable $e) {
                            $errors[] = "Batch upsert failed for customer {$intCustomer->name}: {$e->getMessage()}";
                            Log::error('Historical upsert failed', ['error' => $e->getMessage()]);
                        }
                    }
                });

                Log::info('Historical events processed for customer', [
                    'customer'     => $intCustomer->name,
                    'external_id'  => $intCustomer->external_id,
                    'events_count' => count($events),
                ]);
            } catch (Throwable $e) {
                $errors[] = "Failed to process customer {$intCustomer->name}: {$e->getMessage()}";
                Log::error('Historical customer failed', [
                    'external_id' => $intCustomer->external_id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->updateStatsCache();

        return [
            'success'      => empty($errors),
            'total_events' => $totalEvents,
            'customers'    => $customers->count(),
            'errors_count' => count($errors),
            'errors'       => $errors,
        ];
    }

    // ──────────────────────────────────────────────
    //  Private Helpers
    // ──────────────────────────────────────────────

    private function storeCustomer(array $customer): void
    {
        if (empty($customer['external_id']) || empty($customer['name'])) {
            return;
        }

        DB::transaction(function () use ($customer) {
            IntegrationCustomer::updateOrCreate(
                [
                    'integration_id' => $this->integration->integration_id,
                    'external_id'    => $customer['external_id'],
                ],
                [
                    'name'         => $customer['name'],
                    'metadata'     => $customer['metadata'] ?? [],
                    'active'       => $customer['active'] ?? true,
                    'last_seen_at' => now(),
                ]
            );
        });
    }

    private function processUnmappedCustomers(): void
    {
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        if ($unmappedCount > 0) {
            BatchAutoMatchCustomersJob::dispatchSync($this->integration);
            Log::info('Dispatched batch auto-match for unmapped customers', ['count' => $unmappedCount]);
        }
    }

    private function updateStatsCache(): void
    {
        $key = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($key);

        $mapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')->count();
        $unmapped = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')->count();

        Cache::put($key, [
            'mapped'    => $mapped,
            'unmapped'  => $unmapped,
            'last_sync' => now(),
        ], now()->addMinutes(15));
    }

    public function getCustomers(): array
    {
        return array_map(
            fn($c) => SaaSMapper::mapCustomer($c),
            $this->client->getCustomers()
        );
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        return now()->subDays(365);
    }

    public function getMetadata(): array
    {
        return [
            'name'                   => 'SaaS Alerts',
            'description'            => 'Security monitoring and alerting for SaaS applications',
            'data_types'             => ['customers', 'security_events'],
            'supports_historical'    => true,
            'historical_range_days'  => 365,
        ];
    }

    public function disconnect(): void
    {
        Cache::forget("integration_{$this->integration->integration_id}_customer_counts");
    }

    public function healthCheck(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (Throwable) {
            return false;
        }
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 100,
            'requests_per_hour'   => 2000,
        ];
    }
}
