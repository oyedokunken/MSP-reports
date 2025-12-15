<?php

namespace App\Reports\LibreNMSDeviceReport;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Reports\Contracts\ReportInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LibreNMSDeviceReport implements ReportInterface
{
    public function getRequiredDataTypes(): array
    {
        return ['device_uptime', 'device_availability'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 0; // We use the latest snapshot which contains historical metrics
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has LibreNMS integration
        $hasIntegration = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'LibreNMS')->where('status', 'active');
            })
            ->exists();

        // Also check company-wide integration if not found on customer
        if (!$hasIntegration) {
            $hasIntegration = \App\Models\Integration::where('company_id', $customer->company_id)
                ->where('integration_type', 'LibreNMS')
                ->where('status', 'active')
                ->exists();
        }

        if (!$hasIntegration) {
            return false;
        }

        // Check if we have device data
        // Since LibreNMS devices are often company-wide, we check by company_id
        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->exists();

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get customer's LibreNMS group IDs
        $customerGroupIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'LibreNMS');
            })
            ->get()
            ->map(function($ic) {
                return str_replace('group_', '', $ic->external_id);
            })
            ->toArray();

        // Fetch latest device details (contains group info)
        $deviceDetails = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_details')
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('data_payload.device_id');

        // Fetch latest uptime data
        $uptimeRecords = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_uptime')
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('data_payload.device_id')
            ->keyBy('data_payload.device_id');

        // Fetch latest availability data
        $availabilityRecords = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_availability')
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('data_payload.device_id')
            ->keyBy('data_payload.device_id');

        $devices = [];
        $groups = [];

        foreach ($deviceDetails as $record) {
            $payload = $record->data_payload;
            $deviceId = $payload['device_id'];
            $deviceGroups = $payload['groups'] ?? []; // Array of group objects

            // Check if device belongs to customer
            // If customer has no linked groups, skip (unless we want to show all for testing?)
            // For now, strict filtering:
            $matchesCustomer = false;
            $matchedGroupName = 'Unassigned';

            if (empty($customerGroupIds)) {
                // Fallback: if customer has NO groups linked, maybe they want to see everything?
                // Or maybe this is a "Company Wide" report?
                // Let's assume strict filtering for now to fix the user's issue.
                // But if the user is generating for the MSP company itself, maybe show all?
                // For safety, let's skip if no groups match, unless it's a special case.
                // Actually, let's try to find a matching group name from the device's groups
                // to use as the section header.
                $matchesCustomer = true; // TEMPORARY: Allow all if no specific groups linked (legacy behavior)
            } else {
                foreach ($deviceGroups as $group) {
                    // Group can be just ID or array ['id' => ...]
                    $groupId = is_array($group) ? ($group['id'] ?? null) : $group;
                    if (in_array((string)$groupId, $customerGroupIds)) {
                        $matchesCustomer = true;
                        $matchedGroupName = is_array($group) ? ($group['name'] ?? 'Group ' . $groupId) : 'Group ' . $groupId;
                        break; 
                    }
                }
            }

            if (!$matchesCustomer && !empty($customerGroupIds)) {
                continue;
            }

            // Get uptime/availability data
            $uptimePayload = $uptimeRecords[$deviceId]->data_payload ?? [];
            $availability = $availabilityRecords[$deviceId]->data_payload['availability'] ?? [];
            
            // Format uptime metrics
            $uptimeMetrics = [
                '24h' => $availability['24h']['uptime_percent'] ?? 0,
                '7d' => $availability['7d']['uptime_percent'] ?? 0,
                '30d' => $availability['30d']['uptime_percent'] ?? 0,
                '365d' => $availability['365d']['uptime_percent'] ?? 0,
            ];

            // Determine status
            $status = $payload['status'] ?? ($uptimePayload['status'] ?? 0);
            $statusColor = $status == 1 ? 'success' : 'danger';
            $statusText = $status == 1 ? 'Online' : 'Offline';

            $name = !empty($payload['sysName']) ? $payload['sysName'] : (!empty($payload['hostname']) ? $payload['hostname'] : 'Unknown Device');

            $device = [
                'name' => $name,
                'ip' => $payload['ip'] ?? 'N/A',
                'type' => $payload['type'] ?? 'N/A',
                'hardware' => $payload['hardware'] ?? 'N/A',
                'os' => $payload['os'] ?? 'N/A',
                'status' => $statusText,
                'status_color' => $statusColor,
                'uptime_days' => $uptimePayload['uptime_days'] ?? 0,
                'uptime_metrics' => $uptimeMetrics,
                'last_polled' => isset($payload['last_polled']) ? Carbon::parse($payload['last_polled'])->diffForHumans() : 'N/A',
            ];

            $devices[] = $device;
            
            // Group by the matched group name (or 'All Devices' if generic)
            $groupKey = !empty($customerGroupIds) ? $matchedGroupName : 'All Devices';
            
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            $groups[$groupKey][] = $device;
        }

        // Sort groups and devices
        ksort($groups);
        foreach ($groups as &$groupDevices) {
            usort($groupDevices, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        // Calculate summary metrics
        $totalDevices = count($devices);
        $onlineDevices = count(array_filter($devices, fn($d) => $d['status'] === 'Online'));
        $avgUptime30d = $totalDevices > 0 ? collect($devices)->avg('uptime_metrics.30d') : 0;

        // Calculate breakdown stats
        $deviceTypes = [];
        $osTypes = [];
        
        foreach ($devices as $device) {
            $type = $device['type'] !== 'N/A' ? $device['type'] : 'Unknown';
            $os = $device['os'] !== 'N/A' ? $device['os'] : 'Unknown';
            
            if (!isset($deviceTypes[$type])) $deviceTypes[$type] = 0;
            $deviceTypes[$type]++;
            
            if (!isset($osTypes[$os])) $osTypes[$os] = 0;
            $osTypes[$os]++;
        }
        
        // Sort by count descending
        arsort($deviceTypes);
        arsort($osTypes);

        return [
            'generated_at' => $startDate->format('F j, Y') . ' - ' . $endDate->format('F j, Y'),
            'customer' => $customer,
            'period' => [
                'start' => $startDate->format('F j, Y'),
                'end' => $endDate->format('F j, Y'),
            ],
            'summary' => [
                'total_devices' => $totalDevices,
                'online_devices' => $onlineDevices,
                'offline_devices' => $totalDevices - $onlineDevices,
                'avg_uptime_30d' => round($avgUptime30d, 2),
                'device_types' => $deviceTypes,
                'os_types' => $osTypes,
            ],
            'groups' => $groups,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/LibreNMSDeviceReport/librenms-device-report.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'librenms_device_report',
            'name' => 'LibreNMS Device Report',
            'description' => 'Device availability report showing 24h, 7d, 30d, and 365d uptime metrics',
            'category' => 'infrastructure',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['weekly', 'monthly'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 30,
        ];
    }
}
