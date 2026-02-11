<?php

declare(strict_types=1);

namespace App\Reports\VonahiSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VonahiSummary extends BaseReport
{
    protected VonahiDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new VonahiDataGatherer();
    }

    public function getRequiredDataTypes(): array
    {
        return ['vonahi_assessment', 'vonahi_finding', 'vonahi_report', 'vonahi_agent'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 90; // Default to 90 days for pen test historical context
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $hasIntegrations = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'Vonahi');
            })
            ->exists();

        if (!$hasIntegrations) {
            return false;
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->exists();
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'Vonahi');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Gather all data
        $allAssessments = $this->dataGatherer->gatherAssessments($customer, $integrationCustomerIds);
        $allFindings = $this->dataGatherer->gatherFindings($customer, $integrationCustomerIds);
        $allAgents = $this->dataGatherer->gatherAgents($customer, $integrationCustomerIds);
        $allReports = $this->dataGatherer->gatherReports($customer, $integrationCustomerIds);

        // Filter by date range
        $assessments = $allAssessments->filter(fn($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
        $findings = $allFindings->filter(fn($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
        $reports = $allReports->filter(fn($row) => $this->inRange($row['timestamp'], $startDate, $endDate));

        // If current range is empty but we have data, show the latest (standard behavior for some reports)
        if ($assessments->isEmpty() && $allAssessments->isNotEmpty()) {
            $assessments = $allAssessments->take(5);
        }
        if ($findings->isEmpty() && $allFindings->isNotEmpty()) {
            $findings = $allFindings->take(20);
        }

        // Analysis
        $assessmentSummary = $this->analyzeAssessments($assessments);
        $findingSummary = $this->analyzeFindings($findings, $options['min_severity_filter'] ?? 'informational');
        $agentSummary = $this->analyzeAgents($allAgents);
        
        // Build executive summary
        $executiveSummary = [
            'total_assessments' => $assessments->count(),
            'total_findings' => $findingSummary['total_count'],
            'critical_high_findings' => ($findingSummary['severity_counts']['critical'] ?? 0) + ($findingSummary['severity_counts']['high'] ?? 0),
            'active_agents' => $agentSummary['active_count'],
            'avg_findings_per_assessment' => $assessments->count() > 0 ? round($findingSummary['total_count'] / $assessments->count(), 1) : 0,
        ];

        $reportData = [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer,
            'period' => [
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'executive_summary' => $executiveSummary,
            'assessments' => $assessmentSummary['list'],
            'findings' => $findingSummary['list'],
            'severity_distribution' => $findingSummary['severity_counts'],
            'agents' => $agentSummary['list'],
            'latest_reports' => $reports->take(10)->values()->toArray(),
            'recommendations' => $this->buildRecommendations($findingSummary),
        ];

        return $this->filterSections($reportData, $options);
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/VonahiSummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        $config = include __DIR__ . '/config.php';
        return array_merge($config, [
            'id' => 'vonahi_summary',
        ]);
    }

    private function inRange($timestamp, Carbon $start, Carbon $end): bool
    {
        if (!$timestamp) return false;
        $ts = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);
        return $ts->between($start->copy()->startOfDay(), $end->copy()->endOfDay());
    }

    private function analyzeAssessments(Collection $assessments): array
    {
        return [
            'list' => $assessments->map(function($row) {
                $p = $row['payload'];
                return [
                    'name' => $p['assessment_display_name'] ?? $p['name'],
                    'type' => $p['name'],
                    'date' => Carbon::parse($p['created_at'])->format('M d, Y'),
                    'severity' => $p['severity'] ?? 'informational',
                    'remediation' => $p['remediation_scan'] ?? false,
                    'paused' => $p['paused'] ?? false,
                ];
            })->values()->toArray()
        ];
    }

    private function analyzeFindings(Collection $findings, string $minSeverity): array
    {
        $severityOrder = ['informational' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $minWeight = $severityOrder[strtolower($minSeverity)] ?? 0;

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0];
        $list = [];

        foreach ($findings as $row) {
            $p = $row['payload'];
            $severity = strtolower($p['severity'] ?? 'informational');
            
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }

            $weight = $severityOrder[$severity] ?? 0;
            if ($weight >= $minWeight) {
                $list[] = [
                    'name' => $p['name'],
                    'severity' => $severity,
                    'hosts_count' => $p['affected_hosts_count'] ?? 0,
                    'hosts_ips' => array_slice($p['affected_host_ips'] ?? [], 0, 5),
                    'impact' => Str::limit($p['security_impact'] ?? '', 150),
                    'recommendation' => Str::limit($p['recommendation'] ?? '', 150),
                ];
            }
        }

        // Sort list by severity weight desc
        usort($list, fn($a, $b) => ($severityOrder[$b['severity']] ?? 0) <=> ($severityOrder[$a['severity']] ?? 0));

        return [
            'total_count' => $findings->count(),
            'severity_counts' => $counts,
            'list' => array_slice($list, 0, 50), // Limit to top 50
        ];
    }

    private function analyzeAgents(Collection $agents): array
    {
        $active = 0;
        $list = $agents->map(function($row) use (&$active) {
            $p = $row['payload'];
            $status = strtolower($p['activation_status'] ?? 'unknown');
            if ($status === 'active' || $status === 'online') {
                $active++;
            }
            return [
                'name' => $p['name'],
                'ip' => $p['ip_address'] ?? 'N/A',
                'status' => $status,
                'os' => $p['os_version'] ?? 'Linux',
            ];
        })->values()->toArray();

        return [
            'active_count' => $active,
            'list' => $list,
        ];
    }

    private function buildRecommendations(array $findingSummary): array
    {
        $recs = [];
        $counts = $findingSummary['severity_counts'];

        if ($counts['critical'] > 0) {
            $recs[] = "URGENT: Address {$counts['critical']} Critical security vulnerability(ies) immediately to prevent potential system compromise.";
        }
        if ($counts['high'] > 0) {
            $recs[] = "High Risk: Remediation for {$counts['high']} high-severity finding(s) should be prioritized in the next patch cycle.";
        }
        if ($counts['medium'] > 0) {
            $recs[] = "Security Improvement: Evaluate and address {$counts['medium']} medium-severity vulnerability(ies) to reduce attack surface.";
        }

        if (empty($recs)) {
            $recs[] = "No critical or high severity vulnerabilities were identified in the latest assessments. Continue regular automated testing to maintain security posture.";
        }

        return $recs;
    }
}
