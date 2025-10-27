<?php

namespace App\Integrations\Autotask;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Jobs\AutoMatchCustomersJob;
use Carbon\Carbon;

class AutotaskIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected AutotaskClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new AutotaskClient($integration->credentials);
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

        try {
            // Get companies (customers) from Autotask
            $autotaskCompanies = $this->client->getCompanies();
            
            \Log::info('Autotask sync: Retrieved companies', [
                'count' => count($autotaskCompanies),
                'integration_id' => $this->integration->integration_id,
            ]);

            foreach ($autotaskCompanies as $company) {
                try {
                    // Map to standardized format
                    $standardized = AutotaskMapper::mapCompany($company);

                    // Store in database
                    $this->storeCustomer($standardized);

                } catch (\Exception $e) {
                    $errors[] = "Failed to process company {$company['id']}: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
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
                $unifiedCustomer = Customer::create([
                    'company_id' => $this->integration->company_id,
                    'canonical_name' => $integrationCustomer->name,
                    'status' => 'active',
                    'metadata' => [],
                ]);

                // Link integration customer to new unified customer
                $integrationCustomer->customer_id = $unifiedCustomer->customer_id;
                $integrationCustomer->save();

                // Create mapping record
                CustomerMapping::create([
                    'customer_id' => $unifiedCustomer->customer_id,
                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
                    'mapping_type' => 'auto',
                    'confidence_score' => 100,
                    'mapped_at' => now(),
                    'notes' => 'Auto-created from source of truth integration',
                ]);

                $mappedList[] = [
                    'external_id' => $integrationCustomer->external_id,
                    'name' => $integrationCustomer->name,
                    'unified_customer_id' => $unifiedCustomer->customer_id,
                    'canonical_name' => $unifiedCustomer->canonical_name,
                ];

                \Log::info('Auto-created customer from source of truth', [
                    'integration_type' => 'Autotask',
                    'customer_name' => $integrationCustomer->name,
                    'customer_id' => $unifiedCustomer->customer_id,
                ]);
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

        try {
            $tickets = $this->client->getTickets($startDate, $endDate);

            foreach ($tickets as $ticket) {
                try {
                    $standardized = AutotaskMapper::mapTicket($ticket);
                    $data[] = $standardized;

                    $this->storeHistoricalData($standardized);

                } catch (\Exception $e) {
                    $errors[] = "Failed to process ticket: {$e->getMessage()}";
                }
            }

            $timeEntries = $this->client->getTimeEntries($startDate, $endDate);
            
            foreach ($timeEntries as $entry) {
                try {
                    $standardized = AutotaskMapper::mapTimeEntry($entry);
                    $data[] = $standardized;
                    $this->storeHistoricalData($standardized);
                } catch (\Exception $e) {
                    $errors[] = "Failed to process time entry: {$e->getMessage()}";
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
                $unifiedCustomer = Customer::create([
                    'company_id' => $this->integration->company_id,
                    'canonical_name' => $customer['name'],
                    'status' => 'active',
                    'metadata' => [],
                ]);

                // Link integration customer to new unified customer
                $integrationCustomer->customer_id = $unifiedCustomer->customer_id;
                $integrationCustomer->save();

                // Create mapping record
                CustomerMapping::create([
                    'customer_id' => $unifiedCustomer->customer_id,
                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
                    'mapping_type' => 'auto',
                    'confidence_score' => 100,
                    'mapped_at' => now(),
                    'notes' => 'Auto-created from source of truth integration',
                ]);

                // Track this mapping
                $this->mappedDuringSync[] = [
                    'external_id' => $customer['external_id'],
                    'name' => $customer['name'],
                    'unified_customer_id' => $unifiedCustomer->customer_id,
                    'canonical_name' => $unifiedCustomer->canonical_name,
                ];

                \Log::info('Auto-created customer from source of truth', [
                    'integration_type' => 'Autotask',
                    'customer_name' => $customer['name'],
                    'customer_id' => $unifiedCustomer->customer_id,
                ]);
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
