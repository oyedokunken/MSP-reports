<?php

namespace App\Integrations\Autotask;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Jobs\AutoMatchCustomersJob;
use App\Services\RateLimiter;
use App\Services\CustomerMappingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AutotaskIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected AutotaskClient $client;
    protected CustomerMappingService $mappingService;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new AutotaskClient($integration->credentials, $rateLimiter, $integration);
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
            // Get companies (customers) from Autotask
            $autotaskCompanies = $this->client->getCompanies();
            
            \Log::info('Autotask sync: Retrieved companies', [
                'count' => count($autotaskCompanies),
                'integration_id' => $this->integration->integration_id,
            ]);

            // Process in chunks of 50 to reduce memory usage (per performance-optimization.md)
            $totalCompanies = count($autotaskCompanies);
            collect($autotaskCompanies)->chunk(50)->each(function ($chunk) use (&$errors, &$totalProcessed, $totalCompanies) {
                foreach ($chunk as $company) {
                    try {
                        // Map to standardized format
                        $standardized = AutotaskMapper::mapCompany($company);

                        // Store in database
                        $this->storeCustomer($standardized);
                        $totalProcessed++;

                    } catch (\Exception $e) {
                        $errors[] = "Failed to process company {$company['id']}: {$e->getMessage()}";
                        \Log::error('Autotask sync: Failed to process company', [
                            'integration_id' => $this->integration->integration_id,
                            'company_id' => $company['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // Log progress after each chunk
                \Log::info('Autotask sync: Processed chunk', [
                    'integration_id' => $this->integration->integration_id,
                    'processed' => $totalProcessed,
                    'total' => $totalCompanies,
                ]);
            });

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
            \Log::error('Autotask sync: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Process existing unmapped customers from database
        $additionalMapped = $this->processUnmappedCustomers();
        $mappedCustomers = array_merge($this->mappedDuringSync, $additionalMapped);

        // Count mapped and unmapped customers AFTER sync
        // Cache results for 10 minutes (per performance-optimization.md: 5-15 min TTL)
        $cacheKey = "integration_{$this->integration->integration_id}_customer_counts";
        Cache::forget($cacheKey); // Clear cache after sync
        
        $mappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNotNull('customer_id')
            ->count();
        
        $unmappedCount = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->count();

        // Cache the counts
        Cache::put($cacheKey, [
            'mapped' => $mappedCount,
            'unmapped' => $unmappedCount,
            'last_sync' => now(),
        ], now()->addMinutes(10));

        \Log::info('Autotask sync: Completed', [
            'integration_id' => $this->integration->integration_id,
            'total_processed' => $totalProcessed,
            'mapped_count' => $mappedCount,
            'unmapped_count' => $unmappedCount,
            'errors' => count($errors),
        ]);

        return [
            'records_synced' => $mappedCount,        // Already mapped customers
            'records_new' => $unmappedCount,         // Unmapped customers (need mapping)
            'customers_mapped' => $mappedCustomers,  // Customers mapped during this sync
            'records_failed' => count($errors),
            'errors' => $errors,
        ];
    }

    private function processUnmappedCustomers(): array
    {
        // Get all unmapped customers for this integration
        $unmappedCustomers = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->whereNull('customer_id')
            ->get();

        $mappedList = [];

        foreach ($unmappedCustomers as $integrationCustomer) {
            // If this integration is source of truth, auto-create unified customer
            if ($this->integration->is_source_of_truth) {
                $mappedList[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Autotask');
            } else {
                // Not source of truth - try auto-matching
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }

        return $mappedList;
    }

    public function getCustomers(): array
    {
        $autotaskCompanies = $this->client->getCompanies();
        return array_map(fn($company) => AutotaskMapper::mapCompany($company), $autotaskCompanies);
    }

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];
        $totalProcessed = 0;

        try {
            // Fetch tickets
            $tickets = $this->client->getTickets($startDate, $endDate);

            // Process tickets in chunks of 50 (per integration-framework.md)
            collect($tickets)->chunk(50)->each(function ($chunk) use (&$data, &$errors, &$totalProcessed) {
                foreach ($chunk as $ticket) {
                    try {
                        $standardized = AutotaskMapper::mapTicket($ticket);
                        $data[] = $standardized;
                        $this->storeHistoricalData($standardized);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process ticket: {$e->getMessage()}";
                        \Log::error('Autotask historical: Failed to process ticket', [
                            'integration_id' => $this->integration->integration_id,
                            'ticket_id' => $ticket['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            // Fetch time entries
            $timeEntries = $this->client->getTimeEntries($startDate, $endDate);
            
            // Process time entries in chunks of 50
            collect($timeEntries)->chunk(50)->each(function ($chunk) use (&$data, &$errors, &$totalProcessed) {
                foreach ($chunk as $entry) {
                    try {
                        $standardized = AutotaskMapper::mapTimeEntry($entry);
                        $data[] = $standardized;
                        $this->storeHistoricalData($standardized);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed to process time entry: {$e->getMessage()}";
                        \Log::error('Autotask historical: Failed to process time entry', [
                            'integration_id' => $this->integration->integration_id,
                            'entry_id' => $entry['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            \Log::info('Autotask historical: Completed', [
                'integration_id' => $this->integration->integration_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'total_processed' => $totalProcessed,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            $errors[] = "Historical data fetch failed: {$e->getMessage()}";
            \Log::error('Autotask historical: Failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        // Autotask typically allows 365 days of history
        return now()->subDays(365);
    }

    public function getMetadata(): array
    {
        return [
            'name' => 'Autotask',
            'description' => 'Autotask PSA - Professional Services Automation',
            'data_types' => [
                'tickets',
                'time_entries',
                'contracts',
                'billing',
                'service_calls',
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        // Clean up any cached data or sessions
        // Autotask uses API key auth, so no session to destroy
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
            'requests_per_hour' => 5000,
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

        // If unmapped, handle based on source of truth setting
        if (!$integrationCustomer->customer_id) {
            // If this integration is source of truth, auto-create unified customer
            if ($this->integration->is_source_of_truth) {
                $this->mappedDuringSync[] = $this->mappingService->createFromSourceOfTruth($integrationCustomer, 'Autotask');
            } else {
                AutoMatchCustomersJob::dispatch($integrationCustomer);
            }
        }
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
