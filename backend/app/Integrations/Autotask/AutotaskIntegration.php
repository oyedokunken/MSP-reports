<?php

namespace App\Integrations\Autotask;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
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

    public function sync(): array
    {
        $customers = [];
        $errors = [];

        try {
            // Get companies (customers) from Autotask
            $autotaskCompanies = $this->client->getCompanies();

            foreach ($autotaskCompanies as $company) {
                try {
                    // Map to standardized format
                    $standardized = AutotaskMapper::mapCompany($company);
                    $customers[] = $standardized;

                    // Store in database
                    $this->storeCustomer($standardized);

                } catch (\Exception $e) {
                    $errors[] = "Failed to process company {$company['id']}: {$e->getMessage()}";
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Sync failed: {$e->getMessage()}";
        }

        return [
            'customers' => $customers,
            'errors' => $errors,
        ];
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
        IntegrationCustomer::updateOrCreate(
            [
                'integration_id' => $this->integration->integration_id,
                'external_id' => $customer['external_id'],
            ],
            [
                'customer_name' => $customer['name'],
                'customer_metadata' => $customer['metadata'],
                'active' => $customer['active'],
                'last_seen_at' => now(),
            ]
        );
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
