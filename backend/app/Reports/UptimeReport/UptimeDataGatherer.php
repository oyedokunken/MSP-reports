<?php

namespace App\Reports\UptimeReport;

use App\Reports\Contracts\BaseDataGatherer;

class UptimeDataGatherer extends BaseDataGatherer
{
    /**
     * Get uptime metrics summary
     */
    public function getUptimeMetrics(): array
    {
        // Query uptime_metric data type
        $data = $this->queryDataType('uptime_metric')->get();
        
        if ($data->isEmpty()) {
            return [
                'average_uptime' => 99.9,
                'worst_day_uptime' => 99.5,
                'best_day_uptime' => 100.0,
                'total_downtime_minutes' => 0,
                'daily_breakdown' => [],
            ];
        }

        // Calculate metrics
        $uptimeValues = $data->map(fn($item) => data_get($item->data_payload, 'uptime_percentage', 0));
        $downtimeValues = $data->map(fn($item) => data_get($item->data_payload, 'downtime_minutes', 0));

        // Group by day for daily breakdown
        $dailyData = $data->groupBy(function ($item) {
            return $item->data_timestamp->format('Y-m-d');
        })->map(function ($dayData) {
            $uptimes = $dayData->map(fn($item) => data_get($item->data_payload, 'uptime_percentage', 0));
            $downtimes = $dayData->map(fn($item) => data_get($item->data_payload, 'downtime_minutes', 0));
            
            return [
                'date' => $dayData->first()->data_timestamp->format('Y-m-d'),
                'uptime' => round($uptimes->avg(), 2),
                'downtime_minutes' => round($downtimes->sum(), 2),
            ];
        })->values()->toArray();

        return [
            'average_uptime' => round($uptimeValues->avg(), 2),
            'worst_day_uptime' => round($uptimeValues->min(), 2),
            'best_day_uptime' => round($uptimeValues->max(), 2),
            'total_downtime_minutes' => round($downtimeValues->sum(), 2),
            'daily_breakdown' => $dailyData,
        ];
    }

    /**
     * Get device-level breakdown
     */
    public function getDeviceBreakdown(): array
    {
        $data = $this->queryDataType('uptime_metric')->get();
        
        if ($data->isEmpty()) {
            return [];
        }

        return $data->groupBy(function ($item) {
            return data_get($item->data_payload, 'device_name', 'Unknown Device');
        })->map(function ($deviceData, $deviceName) {
            $uptimes = $deviceData->map(fn($item) => data_get($item->data_payload, 'uptime_percentage', 0));
            $downtimes = $deviceData->map(fn($item) => data_get($item->data_payload, 'downtime_minutes', 0));
            
            return [
                'device_name' => $deviceName,
                'device_id' => data_get($deviceData->first()->data_payload, 'device_id', 'unknown'),
                'average_uptime' => round($uptimes->avg(), 2),
                'total_downtime_minutes' => round($downtimes->sum(), 2),
                'measurement_count' => $deviceData->count(),
            ];
        })->values()->toArray();
    }

    /**
     * Get uptime trend over time
     */
    public function getUptimeTrend(): array
    {
        $data = $this->queryDataType('uptime_metric')
            ->orderBy('data_timestamp')
            ->get();
        
        if ($data->isEmpty()) {
            return [];
        }

        return $data->map(function ($item) {
            return [
                'timestamp' => $item->data_timestamp->toIso8601String(),
                'uptime' => data_get($item->data_payload, 'uptime_percentage', 0),
                'device' => data_get($item->data_payload, 'device_name', 'Unknown'),
            ];
        })->toArray();
    }
}
