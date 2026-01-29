<?php

namespace App\Reports\LibreNMSSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LibreNMSSummary extends BaseReport
{
    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
    }

    public function getRequiredDataTypes(): array
    {
        return ['device_uptime', 'device_availability', 'device_details'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days for trends
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $hasIntegration = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'LibreNMS')->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->exists();

        if (!$hasIntegration) {
            $hasIntegration = \App\Models\Integration::where('company_id', $customer->company_id)
                ->where('integration_type', 'LibreNMS')
                ->whereIn('status', ['active', 'importing_historical', 'syncing'])
                ->exists();
        }

        if (!$hasIntegration) {
            return false;
        }

        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->exists();

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get customer's LibreNMS group IDs (same as Device Report)
        $customerGroupIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'LibreNMS');
            })
            ->get()
            ->map(function(\App\Models\IntegrationCustomer $ic) {
                return str_replace('group_', '', (string)$ic->external_id);
            })
            ->toArray();

        // Fetch latest data (limit to last 7 days for performance)
        $deviceDetails = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_details')
            ->where('data_timestamp', '>=', now()->subDays(7))
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('data_payload.device_id');

        $uptimeRecords = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_uptime')
            ->where('data_timestamp', '>=', now()->subDays(7))
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('data_payload.device_id')
            ->keyBy('data_payload.device_id');

        $availabilityRecords = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'device_availability')
            ->where('data_timestamp', '>=', now()->subDays(7))
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('data_payload.device_id')
            ->keyBy('data_payload.device_id');

        $devices = [];
        foreach ($deviceDetails as $record) {
            $payload = $record->data_payload;
            $deviceId = $payload['device_id'];
            $deviceGroups = $payload['groups'] ?? [];

            $matchesCustomer = false;
            if (empty($customerGroupIds)) {
                $matchesCustomer = true; 
            } else {
                foreach ($deviceGroups as $group) {
                    $groupId = is_array($group) ? ($group['id'] ?? null) : $group;
                    if (in_array((string)$groupId, $customerGroupIds)) {
                        $matchesCustomer = true;
                        break; 
                    }
                }
            }

            if (!$matchesCustomer && !empty($customerGroupIds)) {
                continue;
            }

            $uptimePayload = $uptimeRecords[$deviceId]->data_payload ?? [];
            $availability = $availabilityRecords[$deviceId]->data_payload['availability'] ?? [];
            
            $status = $payload['status'] ?? ($uptimePayload['status'] ?? 0);
            
            $devices[] = [
                'name' => !empty($payload['sysName']) ? $payload['sysName'] : ($payload['hostname'] ?? 'Unknown'),
                'status' => $status == 1 ? 'Online' : 'Offline',
                'type' => $payload['type'] ?? 'Unknown',
                'os' => $payload['os'] ?? 'Unknown',
                'uptime_30d' => $availability['30d']['uptime_percent'] ?? 0,
            ];
        }

        $totalDevices = count($devices);
        $onlineDevices = count(array_filter($devices, fn($d) => $d['status'] === 'Online'));
        $avgUptime30d = $totalDevices > 0 ? collect($devices)->avg('uptime_30d') : 0;

        // Breakdown stats
        $deviceTypes = [];
        $osTypes = [];
        foreach ($devices as $device) {
            $type = $device['type'] ?: 'Unknown';
            $os = $device['os'] ?: 'Unknown';
            $deviceTypes[$type] = ($deviceTypes[$type] ?? 0) + 1;
            $osTypes[$os] = ($osTypes[$os] ?? 0) + 1;
        }
        arsort($deviceTypes);
        arsort($osTypes);

        // Top Issues (Lowest 30d uptime)
        $topIssues = collect($devices)
            ->filter(fn($d) => $d['uptime_30d'] < 100)
            ->sortBy('uptime_30d')
            ->take(10)
            ->values()
            ->toArray();

        // Build recommendations
        $recommendations = [];
        if ($totalDevices > 0 && $onlineDevices < $totalDevices) {
            $offlineCount = $totalDevices - $onlineDevices;
            $recommendations[] = [
                'severity' => 'high',
                'title' => "$offlineCount Devices Offline",
                'description' => "Currently, $offlineCount out of $totalDevices devices are reporting as offline.",
                'action' => 'Check device power, connectivity, and SNMP polling status for offline units.'
            ];
        }

        if ($avgUptime30d < 99.9) {
            $recommendations[] = [
                'severity' => 'medium',
                'title' => "Sub-optimal Network Availability",
                'description' => "Average 30-day uptime is " . round($avgUptime30d, 2) . "%, which is below the 99.9% target.",
                'action' => 'Review top issues below to identify problematic hardware or connection segments.'
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'severity' => 'success',
                'title' => 'Network Healthy',
                'description' => 'All monitored devices are online and average uptime is at 100%.',
                'action' => 'No actions required. Maintain current monitoring policies.'
            ];
        }

        $reportData = [
            'generated_at' => now()->format('F j, Y'),
            'customer' => $customer,
            'period' => [
                'start' => $startDate->format('F j, Y'),
                'end' => $endDate->format('F j, Y'),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'summary' => [
                'total_devices' => $totalDevices,
                'online_devices' => $onlineDevices,
                'offline_devices' => $totalDevices - $onlineDevices,
                'avg_uptime_30d' => round($avgUptime30d, 2),
                'online_percent' => $totalDevices > 0 ? round(($onlineDevices / $totalDevices) * 100, 2) : 0,
            ],
            'infrastructure' => [
                'device_types' => $deviceTypes,
                'os_types' => $osTypes,
            ],
            'top_issues' => $topIssues,
            'recommendations' => $recommendations,
        ];

        return $this->filterSections($reportData, $options);
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/LibreNMSSummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'librenms_summary',
            'name' => 'LibreNMS Executive Summary',
            'description' => 'High-level overview of network availability and device health',
            'category' => 'infrastructure',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 45,
        ];
    }
}
