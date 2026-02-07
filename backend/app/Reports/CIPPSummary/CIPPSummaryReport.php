<?php

declare(strict_types=1);

namespace App\Reports\CIPPSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class CIPPSummaryReport extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['user', 'license', 'security_alert', 'security_incident', 'conditional_access', 'defender_state', 'mailbox_usage', 'exchange_protection'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30;
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $gatherer = new CIPPDataGatherer($customer);
        return $gatherer->hasData();
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $gatherer = new CIPPDataGatherer($customer);

        $userData = $gatherer->gatherUserData($startDate, $endDate);
        $licenseData = $gatherer->gatherLicenseData($startDate, $endDate);
        $securityData = $gatherer->gatherSecurityData($startDate, $endDate);
        $defenderData = $gatherer->gatherDefenderData($startDate, $endDate);
        $conditionalAccessData = $gatherer->gatherConditionalAccessData($startDate, $endDate);
        $emailData = $gatherer->gatherEmailData($startDate, $endDate);

        // Build executive summary
        $executiveSummary = $this->buildExecutiveSummary($userData, $licenseData, $securityData, $defenderData, $conditionalAccessData, $emailData);

        // Build recommendations
        $recommendations = $this->buildRecommendations($userData, $securityData, $defenderData, $conditionalAccessData);

        $reportData = [
            'executive_summary' => $executiveSummary,
            'user_identity' => $userData,
            'license_utilization' => $licenseData,
            'security_overview' => $securityData,
            'defender_status' => $defenderData,
            'conditional_access' => $conditionalAccessData,
            'email_protection' => $emailData,
            'recommendations' => $recommendations,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'cover_title' => 'Microsoft 365 Summary Report',
            'cover_subtitle' => 'Comprehensive overview of tenant security, identity, licensing, and compliance posture.',
            'options' => $options,
        ];

        return $this->filterSections($reportData, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExecutiveSummary(array $userData, array $licenseData, array $securityData, array $defenderData, array $conditionalAccessData, array $emailData): array
    {
        return [
            'total_users' => $userData['total_users'],
            'mfa_percentage' => $userData['mfa_percentage'],
            'mfa_enabled' => $userData['mfa_enabled'],
            'mfa_disabled' => $userData['mfa_disabled'],
            'total_license_types' => $licenseData['total_license_types'],
            'total_assigned_licenses' => $licenseData['total_assigned'],
            'total_available_licenses' => $licenseData['total_available'],
            'total_alerts' => $securityData['total_alerts'],
            'total_incidents' => $securityData['total_incidents'],
            'high_severity_alerts' => $securityData['severity_counts']['high'] ?? 0,
            'defender_protection_rate' => $defenderData['protection_rate'],
            'defender_devices' => $defenderData['total_devices'],
            'ca_policies_enabled' => $conditionalAccessData['enabled'],
            'ca_policies_total' => $conditionalAccessData['total_policies'],
            'total_mailboxes' => $emailData['total_mailboxes'],
            'total_storage_gb' => $emailData['total_storage_gb'],
        ];
    }

    /**
     * @return array<int, array{severity: string, title: string, description: string, action: string}>
     */
    private function buildRecommendations(array $userData, array $securityData, array $defenderData, array $conditionalAccessData): array
    {
        $recommendations = [];

        // MFA coverage
        if ($userData['mfa_percentage'] < 100) {
            $severity = $userData['mfa_percentage'] < 50 ? 'critical' : ($userData['mfa_percentage'] < 80 ? 'high' : 'medium');
            $recommendations[] = [
                'severity' => $severity,
                'title' => 'MFA Not Fully Deployed',
                'description' => "{$userData['mfa_disabled']} of {$userData['total_users']} users ({$userData['mfa_percentage']}% coverage) do not have MFA enabled.",
                'action' => 'Enable MFA for all user accounts. Prioritize admin accounts and users with access to sensitive data.',
            ];
        }

        // High severity security alerts
        $highAlerts = $securityData['severity_counts']['high'] ?? 0;
        if ($highAlerts > 0) {
            $recommendations[] = [
                'severity' => 'critical',
                'title' => 'High Severity Security Alerts',
                'description' => "{$highAlerts} high severity security alert(s) detected during this period.",
                'action' => 'Investigate and remediate all high severity alerts immediately. Review affected accounts and resources.',
            ];
        }

        // Security incidents
        if ($securityData['total_incidents'] > 0) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'Active Security Incidents',
                'description' => "{$securityData['total_incidents']} security incident(s) recorded during this period.",
                'action' => 'Review all incidents, ensure proper incident response procedures are followed, and document remediation steps.',
            ];
        }

        // Defender protection gaps
        if ($defenderData['unprotected'] > 0) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'Unprotected Devices Detected',
                'description' => "{$defenderData['unprotected']} device(s) are not protected by Microsoft Defender.",
                'action' => 'Deploy Defender to all unmanaged devices. Verify device enrollment and compliance policies.',
            ];
        }

        // Outdated Defender definitions
        if ($defenderData['outdated_definitions'] > 0) {
            $recommendations[] = [
                'severity' => 'medium',
                'title' => 'Outdated Defender Definitions',
                'description' => "{$defenderData['outdated_definitions']} device(s) have outdated virus definitions.",
                'action' => 'Ensure automatic updates are enabled. Investigate devices that are not receiving definition updates.',
            ];
        }

        // Conditional access
        if ($conditionalAccessData['total_policies'] === 0) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'No Conditional Access Policies',
                'description' => 'No conditional access policies are configured for this tenant.',
                'action' => 'Implement conditional access policies to enforce MFA, device compliance, and location-based access controls.',
            ];
        } elseif ($conditionalAccessData['disabled'] > 0) {
            $recommendations[] = [
                'severity' => 'low',
                'title' => 'Disabled Conditional Access Policies',
                'description' => "{$conditionalAccessData['disabled']} conditional access policy/policies are disabled.",
                'action' => 'Review disabled policies and enable or remove them as appropriate.',
            ];
        }

        // Positive message if no issues
        if (empty($recommendations)) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'Healthy Tenant Configuration',
                'description' => 'No significant security or configuration concerns detected during this period.',
                'action' => 'Continue monitoring and maintain current security policies. Review this report regularly.',
            ];
        }

        return $recommendations;
    }

    public function getTemplate(): string
    {
        return 'Reports.CIPPSummary.template';
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'cipp_summary',
            'name' => 'Microsoft 365 Summary Report',
            'description' => 'Comprehensive Microsoft 365 tenant report including user & MFA status, license utilization, security alerts, Defender status, conditional access policies, and email protection',
            'category' => 'cloud',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['weekly', 'monthly', 'yearly', 'on-demand'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 45,
        ];
    }
}
