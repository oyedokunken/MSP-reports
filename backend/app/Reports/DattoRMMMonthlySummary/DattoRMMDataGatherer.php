<?php

namespace App\Reports\DattoRMMMonthlySummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DattoRMMDataGatherer
{
    /**
     * Gather device data for a customer within a date range
     */
    public function gatherDevices(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        // Try to get data within date range
        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_status')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->orderBy('data_timestamp', 'desc')
            ->get();

        // Fallback: if no data in range, get the most recent device status snapshots
        if ($rawData->isEmpty()) {
            $rawData = IntegrationData::where('company_id', $customer->company_id)
                ->where('data_type', 'device_status')
                ->whereIn('integration_customer_id', $integrationCustomerIds)
                ->orderBy('data_timestamp', 'desc')
                ->limit(100) // Get recent snapshots
                ->get();
        }

        return $rawData->unique(function($item) {
            return $item->data_payload['device_uid'] ?? null;
        })->map(function($item) {
            $payload = $item->data_payload;
            $lastSeen = isset($payload['last_seen']) ? Carbon::parse($payload['last_seen']) : null;
            $lastReboot = isset($payload['last_reboot']) ? Carbon::parse($payload['last_reboot']) : null;
            
            // Parse antivirus data
            $antivirusData = $payload['antivirus_status'] ?? [];
            $antivirusProduct = $antivirusData['antivirusProduct'] ?? 'Not Installed';
            $antivirusStatus = $antivirusData['antivirusStatus'] ?? 'Unknown';
            
            // Parse patch management data
            $patchData = $payload['patch_status'] ?? [];
            $patchStatus = $patchData['patchStatus'] ?? 'Unknown';
            $patchesApprovedPending = $patchData['patchesApprovedPending'] ?? 0;
            $patchesNotApproved = $patchData['patchesNotApproved'] ?? 0;
            $patchesInstalled = $patchData['patchesInstalled'] ?? 0;
            
            return [
                'device_uid' => $payload['device_uid'] ?? null,
                'hostname' => $payload['hostname'] ?? 'Unknown',
                'site_name' => $payload['site_name'] ?? 'Unknown',
                'device_type' => $payload['device_type'] ?? 'Unknown',
                'device_category' => $payload['device_type'] ?? 'Unknown',
                'operating_system' => $payload['operating_system'] ?? 'Unknown',
                'int_ip_address' => $payload['ip_address'] ?? null,
                'ext_ip_address' => $payload['external_ip'] ?? null,
                'domain' => $payload['domain'] ?? null,
                'online' => $payload['online'] ?? false,
                'suspended' => $payload['suspended'] ?? false,
                'reboot_required' => $payload['reboot_required'] ?? false,
                'last_seen' => $lastSeen ? $lastSeen->format('M d, Y H:i') : 'Never',
                'last_seen_timestamp' => $lastSeen,
                'last_reboot' => $lastReboot ? $lastReboot->format('M d, Y H:i') : 'Unknown',
                // Antivirus
                'antivirus_product' => $antivirusProduct,
                'antivirus_status' => $antivirusStatus,
                // Patch Management
                'patch_status' => $patchStatus,
                'patches_approved_pending' => $patchesApprovedPending,
                'patches_not_approved' => $patchesNotApproved,
                'patches_installed' => $patchesInstalled,
                // Software Compliance
                'software_status' => 'Unknown', // Not provided by Datto mapper
            ];
        })->values();
    }

    /**
     * Gather alert data for a customer within a date range
     */
    public function gatherAlerts(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
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

        return $rawData->map(function($item) {
            $payload = $item->data_payload;
            $timestamp = $item->data_timestamp;
            $resolvedAt = isset($payload['resolved_at']) ? Carbon::parse($payload['resolved_at']) : null;
            
            $resolutionMinutes = null;
            if ($timestamp && $resolvedAt) {
                $resolutionMinutes = $timestamp->diffInMinutes($resolvedAt);
            }
            
            return [
                'alert_uid' => $payload['alert_uid'] ?? null,
                'priority' => $payload['priority'] ?? 'Unknown',
                'priority_level' => $this->getPriorityLevel($payload['priority'] ?? null),
                'diagnostics' => $payload['description'] ?? 'No details',
                'resolved' => $payload['resolved'] ?? false,
                'resolved_on' => $resolvedAt ? $resolvedAt->format('M d, Y H:i') : null,
                'muted' => $payload['muted'] ?? false,
                'ticket_number' => $payload['ticket_number'] ?? null,
                'timestamp' => $timestamp ? $timestamp->format('M d, Y H:i') : 'Unknown',
                'timestamp_obj' => $timestamp,
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
     */
    public function gatherUptimeMetrics(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
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

        return $rawData->map(function($item) {
            $payload = $item->data_payload;
            
            return [
                'device_uid' => $payload['device_uid'] ?? null,
                'device_name' => $payload['device_name'] ?? 'Unknown',
                'uptime_percentage' => $payload['uptime_percentage'] ?? 0,
                'total_hours' => $payload['total_hours'] ?? 0,
                'online_hours' => $payload['online_hours'] ?? 0,
                'offline_hours' => $payload['offline_hours'] ?? 0,
                'timestamp' => isset($payload['timestamp']) ? Carbon::parse($payload['timestamp'])->format('M d, Y') : 'Unknown',
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
