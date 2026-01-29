<?php

declare(strict_types=1);

namespace App\Reports\Contracts;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

abstract class BaseDataGatherer
{
    protected Customer $customer;
    protected Carbon $startDate;
    protected Carbon $endDate;
    protected int $companyId;

    public function __construct(Customer $customer, Carbon $startDate, Carbon $endDate)
    {
        $this->customer = $customer;
        $this->startDate = $startDate->startOfDay();
        $this->endDate = $endDate->endOfDay();
        $this->companyId = $customer->company_id;
    }

    /**
     * Query IntegrationData for specific data type
     * Returns query builder for further customization
     * 
     * @param string $dataType
     * @return \Illuminate\Database\Eloquent\Builder<IntegrationData>
     */
    protected function queryDataType(string $dataType)
    {
        return IntegrationData::where('company_id', $this->companyId)
            ->whereHas('integrationCustomer.customerMappings', function ($query) {
                $query->where('customer_id', $this->customer->customer_id);
            })
            ->where('data_type', $dataType)
            ->whereBetween('data_timestamp', [$this->startDate, $this->endDate])
            ->orderBy('data_timestamp');
    }

    /**
     * Use TimescaleDB continuous aggregates for performance
     * Queries daily rollups instead of raw data
     * 
     * @param string $dataType
     * @return \Illuminate\Database\Query\Builder
     */
    protected function queryDailyAggregate(string $dataType)
    {
        return DB::table('integration_data_daily')
            ->where('company_id', $this->companyId)
            ->where('customer_id', $this->customer->customer_id)
            ->where('data_type', $dataType)
            ->whereBetween('bucket', [$this->startDate, $this->endDate])
            ->orderBy('bucket');
    }

    /**
     * Use TimescaleDB monthly aggregates
     * 
     * @param string $dataType
     * @return \Illuminate\Database\Query\Builder
     */
    protected function queryMonthlyAggregate(string $dataType)
    {
        return DB::table('integration_data_monthly')
            ->where('company_id', $this->companyId)
            ->where('customer_id', $this->customer->customer_id)
            ->where('data_type', $dataType)
            ->whereBetween('bucket', [$this->startDate, $this->endDate])
            ->orderBy('bucket');
    }

    /**
     * Get all integration customer IDs for this customer
     * Useful for complex queries
     * 
     * @return array<int, int>
     */
    protected function getIntegrationCustomerIds(): array
    {
        return DB::table('customer_mappings')
            ->where('customer_id', $this->customer->customer_id)
            ->pluck('integration_customer_id')
            ->toArray();
    }

    /**
     * Helper to calculate average from data payload field
     */
    protected function calculateAverage(string $dataType, string $field): float
    {
        $data = $this->queryDataType($dataType)->get();
        
        if ($data->isEmpty()) {
            return 0;
        }

        $values = $data->map(function ($item) use ($field) {
            return data_get($item->data_payload, $field, 0);
        })->filter();

        return $values->avg() ?? 0;
    }

    /**
     * Helper to calculate sum from data payload field
     */
    protected function calculateSum(string $dataType, string $field): float
    {
        $data = $this->queryDataType($dataType)->get();
        
        if ($data->isEmpty()) {
            return 0;
        }

        return $data->sum(function ($item) use ($field) {
            return data_get($item->data_payload, $field, 0);
        });
    }

    /**
     * Helper to count records
     */
    protected function countRecords(string $dataType): int
    {
        return $this->queryDataType($dataType)->count();
    }

    /**
     * Helper to group data by a payload field
     * 
     * @param string $dataType
     * @param string $field
     * @return array<string, int>
     */
    protected function groupByField(string $dataType, string $field): array
    {
        $data = $this->queryDataType($dataType)->get();
        
        return $data->groupBy(function ($item) use ($field): string {
            return (string)($item->data_payload[$field] ?? 'unknown');
        })->map(function ($group) {
            return $group->count();
        })->toArray();
    }

    /**
     * Helper to get min value from payload field
     */
    protected function getMin(string $dataType, string $field): float
    {
        $data = $this->queryDataType($dataType)->get();
        
        if ($data->isEmpty()) {
            return 0;
        }

        $values = $data->map(function ($item) use ($field) {
            return data_get($item->data_payload, $field, 0);
        })->filter();

        return $values->min() ?? 0;
    }

    /**
     * Helper to get max value from payload field
     */
    protected function getMax(string $dataType, string $field): float
    {
        $data = $this->queryDataType($dataType)->get();
        
        if ($data->isEmpty()) {
            return 0;
        }

        $values = $data->map(function ($item) use ($field) {
            return data_get($item->data_payload, $field, 0);
        })->filter();

        return $values->max() ?? 0;
    }

    /**
     * Helper to format percentage
     */
    protected function formatPercentage(float $value): string
    {
        return round($value, 2) . '%';
    }

    /**
     * Helper to format number with commas
     */
    protected function formatNumber(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals);
    }
}
