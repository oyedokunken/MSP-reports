<?php

namespace App\Reports\DattoRMMSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Reports\Contracts\ReportInterface;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class DattoRMMSummary extends BaseReport
{
    private DattoRMMDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new DattoRMMDataGatherer();
    }

    public function getRequiredDataTypes(): array
    {
        return ['device_status', 'alert', 'uptime_metric'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days for monthly report
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has Datto integration
        $hasIntegration = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Datto')->where('status', 'active');
            })
            ->exists();

        if (!$hasIntegration) {
            return false;
        }

        // Check if we have device data
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Datto')->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->exists();

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get integration customer IDs
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Datto')->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Gather all data sections
        $devices = $this->dataGatherer->gatherDevices($customer, $integrationCustomerIds, $startDate, $endDate);
        $alerts = $this->dataGatherer->gatherAlerts($customer, $integrationCustomerIds, $startDate, $endDate);
        $uptimeMetrics = $this->dataGatherer->gatherUptimeMetrics($customer, $integrationCustomerIds, $startDate, $endDate);

        // Calculate summaries
        $deviceSummary = $this->getDeviceSummary($devices);
        $alertSummary = $this->getAlertSummary($alerts);
        $complianceSummary = $this->getComplianceSummary($devices);
        $securitySummary = $this->getSecuritySummary($devices);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($devices, $alerts, $complianceSummary, $securitySummary);

        // Create executive summary
        $executiveSummary = [
            'devices' => [
                'total' => $deviceSummary['total_devices'] ?? 0,
                'online' => $deviceSummary['online_devices'] ?? 0,
                'offline' => $deviceSummary['offline_devices'] ?? 0,
                'uptime_percentage' => $deviceSummary['overall_uptime'] ?? 0,
            ],
            'alerts' => [
                'total' => $alertSummary['total_alerts'] ?? 0,
                'critical' => $alertSummary['critical_alerts'] ?? 0,
                'high' => $alertSummary['high_alerts'] ?? 0,
                'resolved' => $alertSummary['resolved_alerts'] ?? 0,
                'avg_resolution_time' => $alertSummary['avg_resolution_minutes'] ?? 0,
            ],
            'compliance' => [
                'patch_compliance' => $complianceSummary['patch_compliance_percentage'] ?? 0,
                'antivirus_coverage' => $securitySummary['antivirus_coverage_percentage'] ?? 0,
                'devices_needing_reboot' => $complianceSummary['devices_needing_reboot'] ?? 0,
            ],
            'total_recommendations' => count($recommendations),
        ];

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer,
            'customer_data' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'executive_summary' => $executiveSummary,
            'devices' => [
                'data' => $devices->toArray(),
                'summary' => $deviceSummary,
            ],
            'alerts' => [
                'data' => $alerts->toArray(),
                'summary' => $alertSummary,
            ],
            'compliance' => [
                'summary' => $complianceSummary,
            ],
            'security' => [
                'summary' => $securitySummary,
            ],
            'uptime' => [
                'data' => $uptimeMetrics->toArray(),
            ],
            'recommendations' => $recommendations,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/DattoRMMSummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'datto_rmm_summary',
            'name' => 'Datto RMM Summary',
            'description' => 'Comprehensive monthly report covering device health, alerts, compliance, security, and uptime metrics from Datto RMM',
            'category' => 'infrastructure',
            'data_types' => $this->getRequiredDataTypes(),

            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 90,
        ];
    }

    /**
     * Calculate device summary statistics
     */
    private function getDeviceSummary($devices): array
    {
        $total = $devices->count();
        $online = $devices->where('online', true)->count();
        $offline = $total - $online;
        $suspended = $devices->where('suspended', true)->count();
        $rebootRequired = $devices->where('reboot_required', true)->count();

        // Device type breakdown
        $deviceTypes = $devices->groupBy('device_category')->map->count()->toArray();

        // OS breakdown
        $osBreakdown = $devices->groupBy(function($device) {
            $os = $device['operating_system'];
            if (str_contains($os, 'Windows 11')) return 'Windows 11';
            if (str_contains($os, 'Windows 10')) return 'Windows 10';
            if (str_contains($os, 'Windows Server')) return 'Windows Server';
            return 'Other';
        })->map->count()->toArray();

        // Calculate overall uptime
        $overallUptime = $total > 0 ? round(($online / $total) * 100, 1) : 0;

        return [
            'total_devices' => $total,
            'online_devices' => $online,
            'offline_devices' => $offline,
            'suspended_devices' => $suspended,
            'devices_needing_reboot' => $rebootRequired,
            'overall_uptime' => $overallUptime,
            'device_types' => $deviceTypes,
            'os_breakdown' => $osBreakdown,
        ];
    }

    /**
     * Calculate alert summary statistics
     */
    private function getAlertSummary($alerts): array
    {
        $total = $alerts->count();
        $resolved = $alerts->where('resolved', true)->count();
        $open = $total - $resolved;

        // Priority breakdown
        $critical = $alerts->where('priority', 'Critical')->count();
        $high = $alerts->where('priority', 'High')->count();
        $warning = $alerts->where('priority', 'Warning')->count();
        $info = $alerts->where('priority', 'Information')->count();

        // Calculate average resolution time
        $resolvedAlerts = $alerts->where('resolved', true)->whereNotNull('resolution_minutes');
        $avgResolution = $resolvedAlerts->count() > 0 
            ? round($resolvedAlerts->avg('resolution_minutes'), 0)
            : 0;

        // Top alert types
        $topAlerts = $alerts->groupBy('diagnostics')
            ->map(function($group) {
                return [
                    'message' => $group->first()['diagnostics'],
                    'count' => $group->count(),
                    'priority' => $group->first()['priority'],
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();

        return [
            'total_alerts' => $total,
            'resolved_alerts' => $resolved,
            'open_alerts' => $open,
            'critical_alerts' => $critical,
            'high_alerts' => $high,
            'warning_alerts' => $warning,
            'info_alerts' => $info,
            'avg_resolution_minutes' => $avgResolution,
            'top_alerts' => $topAlerts,
        ];
    }

    /**
     * Calculate compliance summary
     */
    private function getComplianceSummary($devices): array
    {
        $total = $devices->count();
        
        // Patch compliance
        $fullyPatched = $devices->where('patch_status', 'FullyPatched')->count();
        $patchCompliance = $total > 0 ? round(($fullyPatched / $total) * 100, 1) : 0;

        // Devices by patch status
        $patchStatusBreakdown = $devices->groupBy('patch_status')->map->count()->toArray();

        // Devices needing attention
        $devicesNeedingReboot = $devices->where('reboot_required', true)->count();
        $devicesWithPendingPatches = $devices->where('patches_approved_pending', '>', 0)->count();

        return [
            'patch_compliance_percentage' => $patchCompliance,
            'fully_patched_devices' => $fullyPatched,
            'devices_needing_reboot' => $devicesNeedingReboot,
            'devices_with_pending_patches' => $devicesWithPendingPatches,
            'patch_status_breakdown' => $patchStatusBreakdown,
        ];
    }

    /**
     * Calculate security summary
     */
    private function getSecuritySummary($devices): array
    {
        $total = $devices->count();
        
        // Antivirus coverage
        $withAntivirus = $devices->where('antivirus_product', '!=', 'Not Installed')->count();
        $antivirusUpToDate = $devices->where('antivirus_status', 'RunningAndUpToDate')->count();
        $antivirusCoverage = $total > 0 ? round(($withAntivirus / $total) * 100, 1) : 0;

        // Antivirus product breakdown
        $antivirusProducts = $devices->groupBy('antivirus_product')->map->count()->toArray();

        // Software compliance
        $softwareCompliant = $devices->where('software_status', 'Compliant')->count();
        $softwareCompliance = $total > 0 ? round(($softwareCompliant / $total) * 100, 1) : 0;

        return [
            'antivirus_coverage_percentage' => $antivirusCoverage,
            'devices_with_antivirus' => $withAntivirus,
            'antivirus_up_to_date' => $antivirusUpToDate,
            'antivirus_products' => $antivirusProducts,
            'software_compliance_percentage' => $softwareCompliance,
            'software_compliant_devices' => $softwareCompliant,
        ];
    }

    /**
     * Generate recommendations based on data
     */
    private function generateRecommendations($devices, $alerts, $complianceSummary, $securitySummary): array
    {
        $recommendations = [];

        // Patch compliance recommendations
        if ($complianceSummary['patch_compliance_percentage'] < 90) {
            $recommendations[] = [
                'severity' => 'high',
                'category' => 'Compliance',
                'title' => 'Patch Compliance Below Target',
                'description' => sprintf(
                    'Only %d%% of devices are fully patched. Target is 90%% or higher.',
                    $complianceSummary['patch_compliance_percentage']
                ),
                'action' => 'Review and approve pending patches, schedule maintenance windows for updates.',
            ];
        }

        // Reboot recommendations
        if ($complianceSummary['devices_needing_reboot'] > 0) {
            $recommendations[] = [
                'severity' => 'medium',
                'category' => 'Maintenance',
                'title' => 'Devices Require Reboot',
                'description' => sprintf(
                    '%d device(s) require a reboot to complete updates.',
                    $complianceSummary['devices_needing_reboot']
                ),
                'action' => 'Schedule reboots during maintenance windows to complete patch installation.',
            ];
        }

        // Antivirus recommendations
        if ($securitySummary['antivirus_coverage_percentage'] < 100) {
            $recommendations[] = [
                'severity' => 'critical',
                'category' => 'Security',
                'title' => 'Incomplete Antivirus Coverage',
                'description' => sprintf(
                    '%d%% antivirus coverage. %d device(s) without antivirus protection.',
                    $securitySummary['antivirus_coverage_percentage'],
                    $devices->count() - $securitySummary['devices_with_antivirus']
                ),
                'action' => 'Install and configure antivirus software on all devices immediately.',
            ];
        }

        // Critical alerts
        $criticalAlerts = $alerts->where('priority', 'Critical')->where('resolved', false)->count();
        if ($criticalAlerts > 0) {
            $recommendations[] = [
                'severity' => 'critical',
                'category' => 'Alerts',
                'title' => 'Unresolved Critical Alerts',
                'description' => sprintf('%d critical alert(s) require immediate attention.', $criticalAlerts),
                'action' => 'Review and resolve critical alerts as priority.',
            ];
        }

        // Offline devices
        $offlineDevices = $devices->where('online', false)->count();
        if ($offlineDevices > ($devices->count() * 0.1)) { // More than 10% offline
            $recommendations[] = [
                'severity' => 'medium',
                'category' => 'Availability',
                'title' => 'High Number of Offline Devices',
                'description' => sprintf('%d device(s) are currently offline.', $offlineDevices),
                'action' => 'Investigate offline devices and restore connectivity.',
            ];
        }

        return $recommendations;
    }

}
