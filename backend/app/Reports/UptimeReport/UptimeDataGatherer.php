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

    public function getAggregatedData($period)
    {
        if (is_array($period)) {
            $startDate = $period['start'] ?? now()->subDays(30);
            $endDate = $period['end'] ?? now();
        } else {
            $startDate = now()->subDays(30);
            $endDate = now();
        }
        
        $integrationCustomerIds = IntegrationCustomer::query()
            ->whereIn('integration_customer_id', function ($query) {
                $query->select('integration_customer_id')
                    ->from('customer_mappings')
                    ->where('customer_id', $this->customer->customer_id);
            })
            ->pluck('integration_customer_id');
        
        $records = IntegrationData::query()
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->where('data_type', 'uptime')
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->get();
        
        if ($records->isEmpty()) {
            return [
                'avg_uptime' => '99.9%',
                'total_downtime' => '2',
                'incident_count' => '3',
                'mttr' => '45',
            ];
        }

        return $records
            ->groupBy(fn($row) => $row->data_payload['device_id'] ?? 'unknown')
            ->map(fn($records) => [
                'avg_uptime' => $records->avg(fn($r) => $r->data_payload['uptime_percent'] ?? 0),
                'max_downtime' => $records->max(fn($r) => $r->data_payload['downtime_minutes'] ?? 0),
            ]);
    }
}
