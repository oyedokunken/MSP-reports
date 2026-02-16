<?php

declare(strict_types=1);

namespace App\Reports\DattoRMMSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DattoRMMDataGatherer
{
    public function gatherDevices(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate, array $options = []): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $timezone = $options['timezone'] ?? config('app.timezone');

        // Professional MSP Approach: 
        // We report on the "Managed Fleet" - any device seen in the last 30 days.
        // This ensures the Total Device count is stable and matches billing/inventory,
        // even if the report is run over a weekend when devices are off.
        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_status')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->where('data_timestamp', '>', now()->subDays(30))
            ->orderBy('external_id')
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['device_uid'] ?? $item->external_id;
        })->map(function($item) use ($timezone) {
            $payload = $item->data_payload;
            $timestamp = $item->data_timestamp;
            $lastSeen = isset($payload['last_seen']) ? Carbon::parse($payload['last_seen']) : $timestamp;
            $lastReboot = isset($payload['last_reboot']) ? Carbon::parse($payload['last_reboot']) : null;
            
            // Check online status - can be in 'online' (bool) or 'status' (string)
            $online = $payload['online'] ?? false;
            if (isset($payload['status'])) {
                $online = $payload['status'] === 'online';
            }
            
            return [
                'device_uid' => $payload['device_uid'] ?? $item->external_id,
                'hostname' => $payload['hostname'] ?? 'Unknown',
                'site_name' => $payload['site_name'] ?? 'Unknown',
                'device_type' => $payload['type'] ?? $payload['device_type'] ?? 'Unknown',
                'device_category' => $payload['type'] ?? $payload['device_type'] ?? 'Unknown',
                'operating_system' => $payload['os'] ?? $payload['operating_system'] ?? 'Unknown',
                'int_ip_address' => $payload['ip_address'] ?? null,
                'ext_ip_address' => $payload['external_ip'] ?? null,
                'domain' => $payload['domain'] ?? null,
                'online' => $online,
                'suspended' => $payload['suspended'] ?? false,
                'reboot_required' => $payload['reboot_required'] ?? false,
                'last_seen' => $lastSeen ? $lastSeen->setTimezone($timezone)->format('M d, Y g:i A') : 'Never',
                'last_seen_timestamp' => $lastSeen,
                'last_reboot' => $lastReboot ? $lastReboot->setTimezone($timezone)->format('M d, Y g:i A') : 'Unknown',
                // Antivirus (default to payload or N/A)
                'antivirus_product' => $payload['antivirus_status']['antivirusProduct'] ?? $payload['antivirus_product'] ?? 'N/A',
                'antivirus_status' => $payload['antivirus_status']['antivirusStatus'] ?? $payload['antivirus_status'] ?? 'N/A',
                // Patch Management
                'patch_status' => $payload['patch_status']['patchStatus'] ?? $payload['patch_status'] ?? 'N/A',
                'patches_approved_pending' => $payload['patch_status']['patchesApprovedPending'] ?? $payload['patches_approved_pending'] ?? 0,
                'patches_not_approved' => $payload['patch_status']['patchesNotApproved'] ?? $payload['patches_not_approved'] ?? 0,
                'patches_installed' => $payload['patch_status']['patchesInstalled'] ?? $payload['patches_installed'] ?? 0,
                'software_status' => 'N/A',
            ];
        })->values();
    }

    /**
     * Gather alert data for a customer within a date range
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherAlerts(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate, array $options = []): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'alert')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->orderBy('data_timestamp', 'desc')
            ->get();

        $timezone = $options['timezone'] ?? config('app.timezone');

        return $rawData->map(function($item) use ($timezone) {
            $payload = $item->data_payload;
            $timestamp = $item->data_timestamp;
            $resolvedAt = isset($payload['resolved_at']) ? Carbon::parse($payload['resolved_at']) : null;
            
            $resolutionMinutes = null;
            if ($resolvedAt) {
                $resolutionMinutes = $timestamp->diffInMinutes($resolvedAt);
            }
            
            return [
                'alert_uid' => $payload['alert_uid'] ?? null,
                'priority' => $payload['priority'] ?? 'Unknown',
                'priority_level' => $this->getPriorityLevel($payload['priority'] ?? null),
                'diagnostics' => $payload['description'] ?? 'No details',
                'resolved' => $payload['resolved'] ?? false,
                'resolved_on' => $resolvedAt ? $resolvedAt->setTimezone($timezone)->format('M d, Y g:i A') : null,
                'muted' => $payload['muted'] ?? false,
                'ticket_number' => $payload['ticket_number'] ?? null,
                'timestamp' => $timestamp->setTimezone($timezone)->format('M d, Y g:i A'),
                'timestamp_obj' => $timestamp->setTimezone($timezone),
                'resolution_minutes' => $resolutionMinutes,
                'device_uid' => $payload['device_uid'] ?? null,
                'device_name' => $payload['device_name'] ?? 'Unknown',
                'event_type' => $payload['event_type'] ?? null,
                'source' => $payload['source'] ?? null,
            ];
        })->values();
    }

    /**
     * Gather uptime metrics for devices
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherUptimeMetrics(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate, array $options = []): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'uptime_metric')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->orderBy('data_timestamp', 'desc')
            ->get();

        $timezone = $options['timezone'] ?? config('app.timezone');

        return $rawData->map(function($item) use ($timezone) {
            $payload = $item->data_payload;
            $timestamp = $item->data_timestamp;
            
            return [
                'device_uid' => $payload['device_uid'] ?? null,
                'device_name' => $payload['device_name'] ?? 'Unknown',
                'uptime_percentage' => $payload['uptime_percentage'] ?? 0,
                'total_hours' => $payload['total_hours'] ?? 0,
                'online_hours' => $payload['online_hours'] ?? 0,
                'offline_hours' => $payload['offline_hours'] ?? 0,
                'timestamp' => isset($payload['timestamp']) ? Carbon::parse($payload['timestamp'])->setTimezone($timezone)->format('M d, Y') : $timestamp->setTimezone($timezone)->format('M d, Y'),
            ];
        })->values();
    }

    /**
     * Get numeric priority level for sorting
     */
    private function getPriorityLevel(?string $priority): int
    {
        $levels = [
            'Critical' => 1,
            'High' => 2,
            'Warning' => 3,
            'Information' => 4,
        ];
        
        return $levels[$priority] ?? 5;
    }
}
