<?php

declare(strict_types=1);

namespace App\Reports\ThreatLockerSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ThreatLockerSummary extends BaseReport
{
    protected ThreatLockerDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new ThreatLockerDataGatherer();
    }

    public function getRequiredDataTypes(): array
    {
        return [
            'threatlocker_computer',
            'threatlocker_policy',
            'threatlocker_application',
            'threatlocker_storage_policy',
            'threatlocker_storage_device',
            'threatlocker_audit',
        ];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 31;
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $hasThreatLocker = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'ThreatLocker')
                  ->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->exists();

        if (!$hasThreatLocker) {
            \Log::info('ThreatLocker Summary validation - No ThreatLocker integration', [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        $integrationCustomerIds = $this->getIntegrationCustomerIds($customer);

        if (empty($integrationCustomerIds)) {
            return false;
        }

        return $this->dataGatherer->hasData($customer, $integrationCustomerIds);
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $integrationCustomerIds = $this->getIntegrationCustomerIds($customer);

        // Gather all data
        $computers = $this->dataGatherer->gatherComputers($customer, $integrationCustomerIds);
        $policies = $this->dataGatherer->gatherPolicies($customer, $integrationCustomerIds);
        $applications = $this->dataGatherer->gatherApplications($customer, $integrationCustomerIds);
        $storagePolicies = $this->dataGatherer->gatherStoragePolicies($customer, $integrationCustomerIds);
        $storageDevices = $this->dataGatherer->gatherStorageDevices($customer, $integrationCustomerIds);
        $auditLogs = $this->dataGatherer->gatherAuditLogs($customer, $integrationCustomerIds, $startDate, $endDate);

        // Analyze
        $computerSummary = $this->analyzeComputers($computers);
        $policySummary = $this->analyzePolicies($policies);
        $applicationSummary = $this->analyzeApplications($applications);
        $storageSummary = $this->analyzeStorage($storagePolicies, $storageDevices);
        $auditSummary = $this->analyzeAuditLogs($auditLogs);

        $executiveSummary = [
            'total_endpoints' => $computerSummary['total_computers'],
            'online_endpoints' => $computerSummary['online_computers'],
            'offline_endpoints' => $computerSummary['offline_computers'],
            'total_policies' => $policySummary['total_policies'],
            'enabled_policies' => $policySummary['enabled_policies'],
            'total_applications' => $applicationSummary['total_applications'],
            'total_storage_policies' => $storageSummary['total_storage_policies'],
            'total_storage_devices' => $storageSummary['total_storage_devices'],
            'total_audit_events' => $auditSummary['total_events'],
            'denied_events' => $auditSummary['denied_events'],
        ];

        $recommendations = $this->buildRecommendations($computerSummary, $policySummary, $auditSummary);

        $reportData = [
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
            'computer_summary' => $computerSummary,
            'policy_summary' => $policySummary,
            'application_summary' => $applicationSummary,
            'storage_summary' => $storageSummary,
            'audit_summary' => $auditSummary,
            'computers_list' => $computerSummary['computers_list'] ?? [],
            'policies_list' => $policySummary['policies_list'] ?? [],
            'applications_list' => $applicationSummary['applications_list'] ?? [],
            'audit_events' => $auditSummary['events_list'] ?? [],
            'alerts' => $recommendations,
        ];

        \Log::info('Generating ThreatLocker report', [
            'customer_id' => $customer->customer_id,
            'computers' => $computerSummary['total_computers'],
            'policies' => $policySummary['total_policies'],
        ]);

        return $this->filterSections($reportData, $options);
    }

    public function getTemplate(): string
    {
        return 'Reports.ThreatLockerSummary.template';
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'threatlocker_summary',
            'name' => 'ThreatLocker Executive Summary',
            'description' => 'Application control posture, endpoint security, and policy compliance from ThreatLocker',
            'category' => 'security',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 60,
        ];
    }

    /**
     * @param Customer $customer
     * @return array<int, int>
     */
    private function getIntegrationCustomerIds(Customer $customer): array
    {
        return $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'ThreatLocker')
                  ->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();
    }

    /**
     * @param Collection<int, mixed> $computers
     * @return array<string, mixed>
     */
    private function analyzeComputers(Collection $computers): array
    {
        $total = $computers->count();
        $online = $computers->filter(fn ($c) => ($c['payload']['is_online'] ?? false) === true)->count();
        $offline = $total - $online;
        $servers = $computers->filter(fn ($c) => ($c['payload']['is_server'] ?? false) === true)->count();
        $workstations = $total - $servers;

        $osCounts = [];
        foreach ($computers as $computer) {
            $os = $computer['payload']['os_name'] ?? 'Unknown';
            $osCounts[$os] = ($osCounts[$os] ?? 0) + 1;
        }
        arsort($osCounts);

        $computersList = $computers->map(function ($c) {
            $p = $c['payload'];
            return [
                'name' => $p['computer_name'] ?? 'Unknown',
                'os' => $p['os_name'] ?? 'Unknown',
                'is_online' => $p['is_online'] ?? false,
                'is_server' => $p['is_server'] ?? false,
                'last_check_in' => $p['last_check_in'] ?? null,
                'agent_version' => $p['threat_locker_version'] ?? $p['agent_version'] ?? null,
                'mode' => $p['mode'] ?? null,
                'deny_count_1d' => $p['deny_count_1d'] ?? 0,
                'deny_count_7d' => $p['deny_count_7d'] ?? 0,
                'group_name' => $p['computer_group_name'] ?? null,
                'domain' => $p['domain'] ?? null,
                'logged_on_user' => $p['logged_on_user'] ?? null,
                'tamper_protection_disabled' => $p['is_tamper_protection_disabled'] ?? false,
                'isolation_mode' => $p['is_isolation_mode'] ?? false,
                'lockdown_mode' => $p['is_lock_down_mode'] ?? false,
            ];
        })->sortBy('name')->values()->toArray();

        return [
            'total_computers' => $total,
            'online_computers' => $online,
            'offline_computers' => $offline,
            'servers' => $servers,
            'workstations' => $workstations,
            'online_rate' => $total > 0 ? round(($online / $total) * 100, 1) : 0,
            'os_distribution' => $osCounts,
            'computers_list' => $computersList,
        ];
    }

    /**
     * @param Collection<int, mixed> $policies
     * @return array<string, mixed>
     */
    private function analyzePolicies(Collection $policies): array
    {
        $total = $policies->count();
        $enabled = $policies->filter(fn ($p) => ($p['payload']['is_enabled'] ?? true) === true)->count();
        $disabled = $total - $enabled;

        $byAction = [];
        foreach ($policies as $policy) {
            $action = $policy['payload']['action'] ?? 'Unknown';
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;
        }

        $policiesList = $policies->map(function ($p) {
            $d = $p['payload'];
            return [
                'name' => $d['policy_name'] ?? 'Unknown',
                'is_enabled' => $d['is_enabled'] ?? true,
                'action' => $d['action'] ?? null,
                'application_name' => $d['application_name'] ?? null,
                'computer_group_name' => $d['computer_group_name'] ?? null,
                'status' => $d['status'] ?? null,
            ];
        })->sortBy('name')->values()->toArray();

        return [
            'total_policies' => $total,
            'enabled_policies' => $enabled,
            'disabled_policies' => $disabled,
            'policies_by_action' => $byAction,
            'policies_list' => $policiesList,
        ];
    }

    /**
     * @param Collection<int, mixed> $applications
     * @return array<string, mixed>
     */
    private function analyzeApplications(Collection $applications): array
    {
        $total = $applications->count();
        $builtIn = $applications->filter(fn ($a) => ($a['payload']['is_built_in'] ?? false) === true)->count();
        $custom = $total - $builtIn;

        $applicationsList = $applications->map(function ($a) {
            $d = $a['payload'];
            return [
                'name' => $d['application_name'] ?? 'Unknown',
                'is_built_in' => $d['is_built_in'] ?? false,
                'file_count' => $d['file_count'] ?? null,
                'category' => $d['category'] ?? null,
                'os_type' => $d['os_type'] ?? null,
            ];
        })->sortBy('name')->values()->toArray();

        return [
            'total_applications' => $total,
            'built_in_applications' => $builtIn,
            'custom_applications' => $custom,
            'applications_list' => $applicationsList,
        ];
    }

    /**
     * @param Collection<int, mixed> $storagePolicies
     * @param Collection<int, mixed> $storageDevices
     * @return array<string, mixed>
     */
    private function analyzeStorage(Collection $storagePolicies, Collection $storageDevices): array
    {
        $totalPolicies = $storagePolicies->count();
        $enabledPolicies = $storagePolicies->filter(fn ($p) => ($p['payload']['is_enabled'] ?? true) === true)->count();

        $totalDevices = $storageDevices->count();
        $approvedDevices = $storageDevices->filter(fn ($d) => ($d['payload']['is_approved'] ?? false) === true)->count();

        return [
            'total_storage_policies' => $totalPolicies,
            'enabled_storage_policies' => $enabledPolicies,
            'total_storage_devices' => $totalDevices,
            'approved_devices' => $approvedDevices,
            'unapproved_devices' => $totalDevices - $approvedDevices,
        ];
    }

    /**
     * @param Collection<int, mixed> $auditLogs
     * @return array<string, mixed>
     */
    private function analyzeAuditLogs(Collection $auditLogs): array
    {
        $total = $auditLogs->count();

        $byAction = [];
        $denied = 0;
        foreach ($auditLogs as $log) {
            $action = $log['payload']['action'] ?? 'Unknown';
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;
            if (stripos($action, 'denied') !== false || stripos($action, 'block') !== false) {
                $denied++;
            }
        }
        arsort($byAction);

        $eventsList = $auditLogs->take(50)->map(function ($l) {
            $d = $l['payload'];
            return [
                'date' => $d['date'] ?? null,
                'computer_name' => $d['computer_name'] ?? null,
                'action' => $d['action'] ?? null,
                'kind_of_action' => $d['kind_of_action'] ?? null,
                'application' => $d['application'] ?? null,
                'process_name' => $d['process_name'] ?? null,
                'file_path' => $d['file_path'] ?? null,
                'user' => $d['user'] ?? null,
                'policy_name' => $d['policy_name'] ?? null,
            ];
        })->values()->toArray();

        return [
            'total_events' => $total,
            'denied_events' => $denied,
            'events_by_action' => $byAction,
            'events_list' => $eventsList,
        ];
    }

    /**
     * @param array<string, mixed> $computerSummary
     * @param array<string, mixed> $policySummary
     * @param array<string, mixed> $auditSummary
     * @return array<int, string>
     */
    private function buildRecommendations(array $computerSummary, array $policySummary, array $auditSummary): array
    {
        $recs = [];

        $offlineRate = ($computerSummary['total_computers'] > 0)
            ? (($computerSummary['offline_computers'] / $computerSummary['total_computers']) * 100)
            : 0;

        if ($offlineRate > 20) {
            $recs[] = "{$computerSummary['offline_computers']} endpoint(s) are offline ({$offlineRate}%). Investigate connectivity or agent health.";
        }

        if (($policySummary['disabled_policies'] ?? 0) > 0) {
            $recs[] = "{$policySummary['disabled_policies']} application control policies are disabled. Review and enable where appropriate.";
        }

        $deniedEvents = $auditSummary['denied_events'] ?? 0;
        if ($deniedEvents > 50) {
            $recs[] = "High volume of denied events ({$deniedEvents}). Review audit logs for false positives or policy tuning opportunities.";
        } elseif ($deniedEvents > 0) {
            $recs[] = "{$deniedEvents} application denial(s) detected. Review to ensure legitimate applications are not being blocked.";
        }

        // Check for tamper protection disabled
        $tamperDisabled = collect($computerSummary['computers_list'] ?? [])
            ->filter(fn ($c) => ($c['tamper_protection_disabled'] ?? false) === true)
            ->count();
        if ($tamperDisabled > 0) {
            $recs[] = "Tamper protection is disabled on {$tamperDisabled} endpoint(s). Re-enable for maximum security.";
        }

        if (empty($recs)) {
            $recs[] = 'ThreatLocker security posture is healthy. All endpoints are protected with application control policies active.';
        }

        return $recs;
    }
}
