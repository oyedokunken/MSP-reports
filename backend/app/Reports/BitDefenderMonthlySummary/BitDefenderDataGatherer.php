<?php

namespace App\Reports\BitDefenderMonthlySummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;

class BitDefenderDataGatherer
{
    /**
     * Fetch endpoint data for the customer's mapped BitDefender integration customers.
     */
    public function gatherEndpoints(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'bitdefender_endpoint')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }

    /**
     * Fetch threat detection data.
     */
    public function gatherThreatDetections(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'bitdefender_threat_detection')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }
}
