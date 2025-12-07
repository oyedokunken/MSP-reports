<?php

namespace App\Reports\BitDefenderMonthlySummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BitDefenderMonthlySummary extends BaseReport
{
    protected BitDefenderDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new BitDefenderDataGatherer();
    }

    public function getRequiredDataTypes(): array
    {
        return ['bitdefender_endpoint', 'bitdefender_threat_detection'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 31; // monthly
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $hasBitDefender = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'BitDefender')->where('status', 'active');
            })
            ->exists();
        
        if (!$hasBitDefender) {
            \Log::info('BitDefender Monthly Summary validation - No BitDefender integration', [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'BitDefender')->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'BitDefender')->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Gather endpoint data (BitDefender stores snapshots)
        $rawEndpoints = $this->dataGatherer->gatherEndpoints($customer, $integrationCustomerIds);
        $endpoints = $rawEndpoints->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
        if ($endpoints->isEmpty() && $rawEndpoints->isNotEmpty()) {
            $endpoints = $rawEndpoints;
        }

        $rawThreats = $this->dataGatherer->gatherThreatDetections($customer, $integrationCustomerIds);
        $threats = $rawThreats->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
        if ($threats->isEmpty() && $rawThreats->isNotEmpty()) {
            $threats = $rawThreats;
        }

        // Analyze endpoints
        $endpointSummary = $this->analyzeEndpoints($endpoints);
        $policySummary = $this->analyzePolicies($endpoints);
        $osSummary = $this->analyzeOperatingSystems($endpoints);
        $threatSummary = $this->analyzeThreats($threats);

        $executiveSummary = [
            'total_endpoints' => $endpointSummary['total_endpoints'] ?? 0,
            'managed_endpoints' => $endpointSummary['managed_endpoints'] ?? 0,
            'unmanaged_endpoints' => $endpointSummary['unmanaged_endpoints'] ?? 0,
            'policy_compliance_rate' => $policySummary['compliance_rate'] ?? 0,
            'total_threats' => $threatSummary['total_threats'] ?? 0,
            'unique_policies' => $policySummary['unique_policies'] ?? 0,
        ];

        $recommendations = $this->buildRecommendations($endpointSummary, $policySummary, $threatSummary);

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
            'endpoint_summary' => $endpointSummary,
            'policy_summary' => $policySummary,
            'os_summary' => $osSummary,
            'threat_summary' => $threatSummary,
            'endpoints_list' => $endpointSummary['endpoints_list'] ?? [],
            'policy_details' => $policySummary['policy_details'] ?? [],
            'os_distribution' => $osSummary['os_distribution'] ?? [],
            'alerts' => $recommendations,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/BitDefenderMonthlySummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 60,
        ];
    }

    private function inRange($timestamp, Carbon $start, Carbon $end): bool
    {
        if (!$timestamp) return false;
        $ts = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);
        return $ts->between($start->copy()->startOfDay(), $end->copy()->endOfDay());
    }

    private function analyzeEndpoints(Collection $endpoints): array
    {
        $total = $endpoints->count();
        $managed = $endpoints->filter(fn ($e) => ($e['payload']['is_managed'] ?? false) === true)->count();
        $unmanaged = $total - $managed;
        
        $endpointsList = $endpoints->map(function ($endpoint) {
            $p = $endpoint['payload'];
            return [
                'name' => $p['name'] ?? 'Unknown',
                'fqdn' => $p['fqdn'] ?? null,
                'ip' => $p['ip'] ?? null,
                'os' => $p['operating_system'] ?? 'Unknown',
                'is_managed' => $p['is_managed'] ?? false,
                'policy_name' => $p['policy']['name'] ?? 'No Policy',
                'policy_applied' => $p['policy']['applied'] ?? false,
                'machine_type' => $this->getMachineTypeName($p['machine_type'] ?? null),
            ];
        })->sortBy('name')->values()->toArray();

        return [
            'total_endpoints' => $total,
            'managed_endpoints' => $managed,
            'unmanaged_endpoints' => $unmanaged,
            'management_rate' => $total > 0 ? round(($managed / $total) * 100, 2) : 0,
            'endpoints_list' => $endpointsList,
        ];
    }

    private function analyzePolicies(Collection $endpoints): array
    {
        $policyCounts = [];
        $totalWithPolicy = 0;
        $totalPolicyApplied = 0;

        foreach ($endpoints as $endpoint) {
            $p = $endpoint['payload'];
            $policyName = $p['policy']['name'] ?? 'No Policy';
            $policyApplied = $p['policy']['applied'] ?? false;

            if (!isset($policyCounts[$policyName])) {
                $policyCounts[$policyName] = [
                    'name' => $policyName,
                    'total' => 0,
                    'applied' => 0,
                ];
            }

            $policyCounts[$policyName]['total']++;
            if ($policyApplied) {
                $policyCounts[$policyName]['applied']++;
                $totalPolicyApplied++;
            }

            if ($policyName !== 'No Policy') {
                $totalWithPolicy++;
            }
        }

        $total = $endpoints->count();
        $complianceRate = $total > 0 ? round(($totalPolicyApplied / $total) * 100, 2) : 0;

        return [
            'unique_policies' => count($policyCounts) - (isset($policyCounts['No Policy']) ? 1 : 0),
            'endpoints_with_policy' => $totalWithPolicy,
            'endpoints_without_policy' => $total - $totalWithPolicy,
            'policy_applied_count' => $totalPolicyApplied,
            'compliance_rate' => $complianceRate,
            'policy_details' => array_values($policyCounts),
        ];
    }

    private function analyzeOperatingSystems(Collection $endpoints): array
    {
        $osCounts = [];

        foreach ($endpoints as $endpoint) {
            $os = $endpoint['payload']['operating_system'] ?? 'Unknown';
            if (!isset($osCounts[$os])) {
                $osCounts[$os] = 0;
            }
            $osCounts[$os]++;
        }

        arsort($osCounts);

        $osDistribution = [];
        foreach ($osCounts as $os => $count) {
            $osDistribution[] = [
                'os' => $os,
                'count' => $count,
                'percentage' => $endpoints->count() > 0 ? round(($count / $endpoints->count()) * 100, 2) : 0,
            ];
        }

        return [
            'os_distribution' => $osDistribution,
            'total_os_types' => count($osCounts),
        ];
    }

    private function analyzeThreats(Collection $threats): array
    {
        // Placeholder for threat analysis when threat detection data is available
        return [
            'total_threats' => $threats->count(),
            'threats_list' => [],
        ];
    }

    private function buildRecommendations(array $endpointSummary, array $policySummary, array $threatSummary): array
    {
        $recs = [];

        if (($endpointSummary['unmanaged_endpoints'] ?? 0) > 0) {
            $recs[] = "Enable management on {$endpointSummary['unmanaged_endpoints']} unmanaged endpoint(s) to ensure security coverage.";
        }

        if (($policySummary['endpoints_without_policy'] ?? 0) > 0) {
            $recs[] = "Assign security policies to {$policySummary['endpoints_without_policy']} endpoint(s) currently without policies.";
        }

        $complianceRate = $policySummary['compliance_rate'] ?? 0;
        if ($complianceRate < 90) {
            $notApplied = ($policySummary['endpoints_with_policy'] ?? 0) - ($policySummary['policy_applied_count'] ?? 0);
            if ($notApplied > 0) {
                $recs[] = "Apply pending policies on {$notApplied} endpoint(s) to improve compliance from {$complianceRate}% to target 90%+.";
            }
        }

        if (empty($recs)) {
            $recs[] = 'Endpoint security posture is healthy. All endpoints are managed with policies applied.';
        }

        return $recs;
    }

    private function getMachineTypeName(?int $type): string
    {
        return match($type) {
            1 => 'Workstation',
            2 => 'Server',
            3 => 'Mobile',
            default => 'Unknown',
        };
    }
}
