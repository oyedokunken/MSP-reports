<?php

declare(strict_types=1);

namespace App\Reports\ITGlueSummary;

use App\Integrations\Contracts\IntegrationDataType;
use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;

class ITGlueSummary extends BaseReport
{
    protected ITGlueDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new ITGlueDataGatherer();
    }
    public function getRequiredDataTypes(): array
    {
        return IntegrationDataType::itglue();
    }

    public function getRequiredHistoricalRange(): int
    {
        return 1; // Only need current data
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has any ITGlue integration
        $hasITGlue = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'ITGlue')
                      ->where('status', 'active');
            })
            ->exists();

        if (!$hasITGlue) {
            \Log::info("ITGlue Monthly Summary validation - No ITGlue integration", [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        // Check if there's any ITGlue data (flexible assets, SSL certs, or domains)
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'ITGlue')
                      ->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', IntegrationDataType::itglue())
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        \Log::info("ITGlue Monthly Summary validation", [
            'customer_id' => $customer->customer_id,
            'integration_customer_ids' => $integrationCustomerIds,
            'has_data' => $hasData,
        ]);

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get integration customer IDs
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'ITGlue')->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Gather licenses using DataGatherer
        $licenses = $this->dataGatherer->gatherLicenses($customer, $integrationCustomerIds);
        $licenseSummary = $this->getLicenseSummary($licenses);
        $licenseRecommendations = $this->getLicenseRecommendations($licenses);

        // Gather SSL certificates using DataGatherer
        $certificates = $this->dataGatherer->gatherCertificates($customer, $integrationCustomerIds);
        $sslSummary = $this->getCertificateSummary($certificates);
        $sslAlerts = $this->getCertificateAlerts($certificates);

        // Gather domains using DataGatherer
        $domains = $this->dataGatherer->gatherDomains($customer, $integrationCustomerIds);
        $domainSummary = $this->getDomainSummary($domains);
        $domainAlerts = $this->getDomainAlerts($domains);

        // Combine all alerts
        $allAlerts = array_merge($sslAlerts, $domainAlerts);

        // Create executive summary
        $executiveSummary = [
            'licenses' => [
                'total' => $licenseSummary['total_licenses'],
                'over_provisioned' => $licenseSummary['over_provisioned'],
                'well_utilized' => $licenseSummary['well_utilized'],
            ],
            'ssl_certificates' => [
                'total' => $sslSummary['total_certificates'],
                'critical' => $sslSummary['critical'],
                'warning' => $sslSummary['warning'],
            ],
            'domains' => [
                'total' => $domainSummary['total_domains'],
                'critical' => $domainSummary['critical'],
                'warning' => $domainSummary['warning'],
            ],
            'total_alerts' => count($allAlerts),
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
            'licenses' => [
                'data' => $licenses->toArray(),
                'summary' => $licenseSummary,
                'recommendations' => $licenseRecommendations,
            ],
            'ssl_certificates' => [
                'data' => $certificates->toArray(),
                'summary' => $sslSummary,
                'alerts' => $sslAlerts,
            ],
            'domains' => [
                'data' => $domains->toArray(),
                'summary' => $domainSummary,
                'alerts' => $domainAlerts,
            ],
            'all_alerts' => $allAlerts,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/ITGlueSummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'itglue_summary',
            'name' => 'ITGlue Asset Summary',
            'description' => 'Review of managed licenses, SSL certificates, and domain registrations from ITGlue documentation',
            'category' => 'documentation',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 60, // seconds
        ];
    }

    // Data gathering is now handled by ITGlueDataGatherer

    /**
     * @param Collection<int, mixed> $licenses
     * @return array{total_licenses: int, over_provisioned: int, under_provisioned: int, well_utilized: int}
     */
    private function getLicenseSummary(Collection $licenses): array
    {
        return [
            'total_licenses' => $licenses->count(),
            'over_provisioned' => $licenses->where('status', 'over_provisioned')->count(),
            'under_provisioned' => $licenses->where('status', 'under_provisioned')->count(),
            'well_utilized' => $licenses->where('status', 'well_utilized')->count(),
        ];
    }

    /**
     * @param Collection<int, mixed> $licenses
     * @return array<int, string>
     */
    private function getLicenseRecommendations(Collection $licenses): array
    {
        $recommendations = [];
        $overProvisioned = $licenses->where('status', 'over_provisioned');
        $underProvisioned = $licenses->where('status', 'under_provisioned');
        
        if ($underProvisioned->count() > 0) {
            $recommendations[] = "Consider purchasing additional licenses for {$underProvisioned->count()} under-provisioned license(s) to avoid capacity issues.";
        }
        
        if ($overProvisioned->count() > 0) {
            $recommendations[] = "Consider reducing {$overProvisioned->count()} over-provisioned license(s) to optimize costs.";
        }
        
        return $recommendations;
    }


    /**
     * @param Collection<int, mixed> $certificates
     * @return array{total_certificates: int, critical: int, warning: int}
     */
    private function getCertificateSummary(Collection $certificates): array
    {
        return [
            'total_certificates' => $certificates->count(),
            'critical' => $certificates->whereIn('severity', ['expired', 'critical'])->count(),
            'warning' => $certificates->where('severity', 'warning')->count(),
        ];
    }

    /**
     * @param Collection<int, mixed> $certificates
     * @return array<int, array{type: string, severity: string, title: string, description: string}>
     */
    private function getCertificateAlerts(Collection $certificates): array
    {
        $alerts = [];
        $critical = $certificates->whereIn('severity', ['expired', 'critical']);
        
        if ($critical->count() > 0) {
            $alerts[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'title' => 'Critical: SSL Certificates Expired or Expiring Soon',
                'description' => "{$critical->count()} certificate(s) require immediate renewal to prevent service disruption.",
            ];
        }
        
        return $alerts;
    }


    /**
     * @param Collection<int, mixed> $domains
     * @return array{total_domains: int, critical: int, warning: int}
     */
    private function getDomainSummary(Collection $domains): array
    {
        return [
            'total_domains' => $domains->count(),
            'critical' => $domains->whereIn('severity', ['expired', 'critical'])->count(),
            'warning' => $domains->where('severity', 'warning')->count(),
        ];
    }

    /**
     * @param Collection<int, mixed> $domains
     * @return array<int, array{type: string, severity: string, title: string, description: string}>
     */
    private function getDomainAlerts(Collection $domains): array
    {
        $alerts = [];
        $critical = $domains->whereIn('severity', ['expired', 'critical']);
        
        if ($critical->count() > 0) {
            $alerts[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'title' => 'Critical: Domains Expired or Expiring Soon',
                'description' => "{$critical->count()} domain(s) require immediate renewal to prevent loss of ownership.",
            ];
        }
        
        return $alerts;
    }
}
