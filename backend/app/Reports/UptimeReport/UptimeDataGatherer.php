<?php

declare(strict_types=1);

namespace App\Reports\UptimeReport;

use App\Reports\Contracts\BaseDataGatherer;

class UptimeDataGatherer extends BaseDataGatherer
{
    /**
     * Get uptime metrics summary
     * 
     * @return array{average_uptime: float, worst_day_uptime: float, best_day_uptime: float, total_downtime_minutes: float, daily_breakdown: array<int, array{date: string, uptime: float, downtime_minutes: float}>}
     */
    public function getUptimeMetrics(): array
    {
        // Query uptime_metric data type
        $data = $this->queryDataType('uptime_metric')
            ->get()
            ->unique(function ($item) {
                return ($item->data_payload['device_uid'] ?? '') . '_' . $item->data_timestamp->timestamp;
            });
        
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
     * 
     * @return array<int, array{device_name: string, device_id: string, average_uptime: float, total_downtime_minutes: float, measurement_count: int}>
     */
    public function getDeviceBreakdown(): array
    {
        $data = $this->queryDataType('uptime_metric')
            ->get()
            ->unique(function ($item) {
                return ($item->data_payload['device_uid'] ?? '') . '_' . $item->data_timestamp->timestamp;
            });
        
        if ($data->isEmpty()) {
            return [];
        }

        return $data->groupBy(function ($item): string {
            return (string)($item->data_payload['device_uid'] ?? 'unknown');
        })->map(function ($deviceData, $deviceUid) {
            $uptimes = $deviceData->map(fn($item) => data_get($item->data_payload, 'uptime_percentage', 0));
            $downtimes = $deviceData->map(fn($item) => data_get($item->data_payload, 'downtime_minutes', 0));
            
            return [
                'device_name' => data_get($deviceData->first()->data_payload, 'device_name', 'Unknown Device'),
                'device_id' => data_get($deviceData->first()->data_payload, 'device_id', 'unknown'),
                'average_uptime' => round($uptimes->avg(), 2),
                'total_downtime_minutes' => round($downtimes->sum(), 2),
                'measurement_count' => $deviceData->count(),
            ];
        })->values()->toArray();
    }

    /**
     * Get uptime trend over time
     * 
     * @return array<int, array{timestamp: string, uptime: float, device: string}>
     */
    public function getUptimeTrend(): array
    {
        $data = $this->queryDataType('uptime_metric')
            ->orderBy('data_timestamp')
            ->get()
            ->unique(function ($item) {
                return ($item->data_payload['device_uid'] ?? '') . '_' . $item->data_timestamp->timestamp;
            });
        
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
