<?php

namespace App\Reports\UptimeReport;

use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;

class UptimeDataGatherer
{
    protected Customer $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function getAggregatedData(string $period)
    {
        // Step 1: Get all integration_customer_ids linked to this customer
        $integrationCustomerIds = IntegrationCustomer::query()
            ->whereIn('integration_customer_id', function ($query) {
                $query->select('integration_customer_id')
                    ->from('customer_mappings')
                    ->where('customer_id', $this->customer->customer_id);
            })
            ->pluck('integration_customer_id');

        // Step 2: Fetch uptime data for those integration customers
        $records = IntegrationData::query()
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->where('data_type', 'uptime')
            ->whereBetween('data_timestamp', [
                now()->subDays(30),
                now(),
            ])
            ->get();

        // Step 3: Aggregate results
        return $records
            ->groupBy(fn($row) => $row->data_payload['device_id'] ?? 'unknown')
            ->map(fn($records) => [
                'avg_uptime' => $records->avg(fn($r) => $r->data_payload['uptime_percent'] ?? 0),
                'max_downtime' => $records->max(fn($r) => $r->data_payload['downtime_minutes'] ?? 0),
            ]);
    }
}
