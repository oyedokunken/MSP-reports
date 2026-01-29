<?php

namespace App\Reports\SaaSSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class SaaSSummary extends BaseReport
{
    protected SaaSDataGatherer $dataGatherer;

    public function __construct()
    {
        $this->dataGatherer = new SaaSDataGatherer();
    }

    public function getRequiredDataTypes(): array
    {
        return ['saas_security_event'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30; // days
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $integrationCustomerIds = $customer->integrationCustomers()
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        // Check if there's any SaaS security event data
        $hasData = \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->where('integration_id', 6)
            ->where('data_type', 'saas_security_event')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get integration customer IDs
        $integrationCustomerIds = $customer->integrationCustomers()
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Gather all security events
        $events = $this->dataGatherer->gatherSecurityEvents($customer, $integrationCustomerIds, $startDate, $endDate);

        // If no events in date range, try to get any available data from the last 3 months
        $actualStartDate = $startDate;
        $actualEndDate = $endDate;
        if ($events->isEmpty()) {
            $fallbackStart = $startDate->copy()->subMonths(3);
            $events = $this->dataGatherer->gatherSecurityEvents(
                $customer,
                $integrationCustomerIds,
                $fallbackStart,
                $endDate
            );
            
            // Update actual date range to reflect the data we found
            if ($events->isNotEmpty()) {
                $actualStartDate = $fallbackStart;
            }
        }

        // Gather analytics
        $userActivity = $this->dataGatherer->gatherUserActivity($events);
        $geographicData = $this->dataGatherer->gatherGeographicData($events);
        $applicationUsage = $this->dataGatherer->gatherApplicationUsage($events);
        $threatStats = $this->dataGatherer->gatherThreatStatistics($events);
        $deviceStats = $this->dataGatherer->gatherDeviceStatistics($events);
        $dailyTrends = $this->dataGatherer->gatherDailyTrends($events, $actualStartDate, $actualEndDate);

        // Build executive summary
        $executiveSummary = $this->buildExecutiveSummary($events, $threatStats, $deviceStats, $userActivity);
        $executiveSummary['daily_trends'] = $dailyTrends;

        // Build recommendations
        $recommendations = $this->buildRecommendations($threatStats, $userActivity, $geographicData);

        $reportData = [
            'executive_summary' => $executiveSummary,
            'recommendations' => $recommendations,
            'user_activity' => $userActivity,
            'geographic_data' => $geographicData,
            'application_usage' => $applicationUsage,
            'threat_statistics' => $threatStats,
            'device_statistics' => $deviceStats,
            'total_events' => $events->count(),
            'date_range' => [
                'start' => $actualStartDate->format('F j, Y'),
                'end' => $actualEndDate->format('F j, Y'),
            ],
            'period' => [
                'start' => $actualStartDate->format('Y-m-d'),
                'end' => $actualEndDate->format('Y-m-d'),
                'formatted' => $actualStartDate->format('F j, Y') . ' - ' . $actualEndDate->format('F j, Y'),
            ],
            'cover_title' => 'SaaS Alerts Security Report',
            'cover_subtitle' => 'Comprehensive analysis of SaaS platform activity, threat detection, and user access patterns.',
            'options' => $options,
        ];

        return $this->filterSections($reportData, $options);
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $events
     * @param array<string, mixed> $threatStats
     * @param array<string, mixed> $deviceStats
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $userActivity
     * @return array<string, mixed>
     */
    private function buildExecutiveSummary($events, $threatStats, $deviceStats, $userActivity): array
    {
        $totalEvents = $events->count();
        $uniqueUsers = $userActivity->count();
        $failedLogins = $threatStats['failed_logins'];
        $threatEvents = $threatStats['threat_events'];

        return [
            'total_events' => $totalEvents,
            'unique_users' => $uniqueUsers,
            'failed_logins' => $failedLogins,
            'threat_events' => $threatEvents,
            'mobile_usage_percent' => $deviceStats['mobile_percentage'],
            'vpn_usage' => $threatStats['vpn_events'],
        ];
    }

    /**
     * @param array<string, mixed> $threatStats
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $userActivity
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $geographicData
     * @return array<int, array{severity: string, title: string, description: string, action: string}>
     */
    private function buildRecommendations($threatStats, $userActivity, $geographicData): array
    {
        $recommendations = [];

        // High failed login rate
        if ($threatStats['failed_login_percentage'] > 10) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'High Failed Login Rate',
                'description' => "{$threatStats['failed_login_percentage']}% of login attempts failed. This may indicate credential stuffing attacks or users with password issues.",
                'action' => 'Review failed login attempts, enforce MFA, and consider implementing account lockout policies.',
            ];
        }

        // Threat events detected
        if ($threatStats['threat_events'] > 0) {
            $recommendations[] = [
                'severity' => 'critical',
                'title' => 'Security Threats Detected',
                'description' => "{$threatStats['threat_events']} events from known threat sources detected.",
                'action' => 'Investigate threat events immediately, block malicious IPs, and review user account security.',
            ];
        }

        // VPN usage
        if ($threatStats['vpn_events'] > 100) {
            $recommendations[] = [
                'severity' => 'medium',
                'title' => 'High VPN Usage',
                'description' => "{$threatStats['vpn_events']} events detected from VPN connections.",
                'action' => 'Review VPN usage policy and ensure corporate VPN is being used for remote access.',
            ];
        }

        // Users with high failed login counts
        $usersWithFailures = $userActivity->filter(function ($user) {
            return $user['failed_logins'] > 5;
        });

        if ($usersWithFailures->count() > 0) {
            $recommendations[] = [
                'severity' => 'medium',
                'title' => 'Users with Multiple Failed Logins',
                'description' => "{$usersWithFailures->count()} user(s) have more than 5 failed login attempts.",
                'action' => 'Contact affected users to verify account security and reset passwords if necessary.',
            ];
        }

        // International access
        $internationalAccess = $geographicData->filter(function ($location) {
            return !in_array($location['country'], ['AU', 'NZ']); // Adjust for customer's expected countries
        });

        if ($internationalAccess->count() > 0) {
            $countries = $internationalAccess->pluck('country')->take(5)->implode(', ');
            $recommendations[] = [
                'severity' => 'low',
                'title' => 'International Access Detected',
                'description' => "Access detected from {$internationalAccess->count()} international location(s): {$countries}",
                'action' => 'Verify international access is legitimate and consider implementing geo-blocking if not expected.',
            ];
        }

        // If no recommendations, add a positive message
        if (empty($recommendations)) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'No Security Concerns',
                'description' => 'No significant security issues detected during this period.',
                'action' => 'Continue monitoring user activity and maintain current security policies.',
            ];
        }

        return $recommendations;
    }

    public function getTemplate(): string
    {
        return 'Reports.SaaSSummary.template';
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'saas_summary',
            'name' => 'SaaS Alerts Security Report',
            'description' => 'Comprehensive security analysis including user activity, threat detection, geographic access patterns, and application usage from SaaS Alerts',
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
}
