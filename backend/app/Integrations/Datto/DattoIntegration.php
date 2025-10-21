<?php

namespace App\Integrations\Datto;

use App\Integrations\Contracts\IntegrationInterface;
use App\Models\Integration;
use Carbon\Carbon;

class DattoIntegration implements IntegrationInterface
{
    protected Integration $integration;
    protected DattoClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new DattoClient($integration->credentials);
    }

    /**
     * Authenticate and validate credentials.
     */
    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * Pull latest current-state data from the integration.
     */
    public function sync(): array
    {
        $customers = [];
        $errors = [];

        try {
            // Get customers from Datto
            $dattoCustomers = $this->client->getCustomers();

            foreach ($dattoCustomers as $dattoCustomer) {
                try {
                    // Transform to standardized format
                    $standardized = DattoMapper::mapCustomer($dattoCustomer);
                    $customers[] = $standardized;

                    // Store in IntegrationCustomer model
                    $this->storeCustomer($standardized);

                } catch (\Exception $e) {
                    $errors[] = "Failed to process customer {$dattoCustomer['id']}: {$e->getMessage()}";
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

    /**
     * Get customer list in standardized format.
     */
    public function getCustomers(): array
    {
        $dattoCustomers = $this->client->getCustomers();
        return array_map(fn($customer) => DattoMapper::mapCustomer($customer), $dattoCustomers);
    }

    /**
     * Pull historical data for a specific date range.
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $errors = [];

        try {
            // Get historical backup data
            $backupData = $this->client->getBackupHistory($startDate, $endDate);

            foreach ($backupData as $record) {
                try {
                    $standardized = DattoMapper::mapBackupData($record);
                    $data[] = $standardized;

                    // Store in IntegrationData model
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

    /**
     * Query how far back historical data is available.
     */
    public function getAvailableHistoryRange(): ?Carbon
    {
        // Datto typically keeps 90 days of backup history
        return now()->subDays(90);
    }

    /**
     * Get metadata about what data fields this integration provides.
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Datto',
            'description' => 'Datto RMM and Backup Platform',
            'data_types' => [
                'backup_status',
                'device_status',
                'uptime_metrics',
            ],
            'supports_historical' => true,
            'historical_range_days' => 90,
        ];
    }

    /**
     * Clean up connection and revoke tokens if applicable.
     */
    public function disconnect(): void
    {
        // Datto uses API keys, no token revocation needed
        // Just clear cached data if any
    }

    /**
     * Test if connection is still valid.
     */
    public function healthCheck(): bool
    {
        try {
            return $this->client->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Declare API rate limits for this integration.
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    /**
     * Store customer in database.
     */
    private function storeCustomer(array $customer): void
    {
        \App\Models\IntegrationCustomer::updateOrCreate(
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

    /**
     * Store historical data in database.
     */
    private function storeHistoricalData(array $data): void
    {
        \App\Models\IntegrationData::create([
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
