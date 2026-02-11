<?php

declare(strict_types=1);

namespace App\Reports\VonahiSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class VonahiDataGatherer
{
    /**
     * Fetch assessment data.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherAssessments(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'vonahi_assessment')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
                'external_id' => $row->external_id,
            ]);
    }

    /**
     * Fetch findings data.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherFindings(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'vonahi_finding')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
                'external_id' => $row->external_id,
            ]);
    }

    /**
     * Fetch agent data.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherAgents(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        // Deduplicate agents by external_id, keeping latest
        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'vonahi_agent')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('external_id')
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
                'external_id' => $row->external_id,
            ]);
    }

    /**
     * Fetch report data.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherReports(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'vonahi_report')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
                'external_id' => $row->external_id,
            ]);
    }
}
