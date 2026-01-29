<?php

namespace App\Reports\SendmarcSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;

class SendmarcDataGatherer
{
    /**
     * Fetch sender statistics for the customer's mapped Sendmarc integration customers.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherSenderStatistics(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'sender_statistics')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->get()
            ->map(fn ($row): array => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }

    /**
     * Fetch volume totals per domain.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherVolumeTotals(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'volume_totals')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->get()
            ->map(fn ($row): array => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }

    /**
     * Fetch volume timeline entries.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherVolumeTimeline(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'volume_timeline')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }

    /**
     * Fetch IP address statistics (sources).
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherIpAddressStatistics(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'ip_address_statistics')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }
}
