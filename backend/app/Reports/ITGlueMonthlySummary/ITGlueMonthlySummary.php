<?php

namespace App\Reports\ITGlueMonthlySummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;

class ITGlueMonthlySummary extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['flexible_asset', 'ssl_certificate', 'domain'];
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

        $hasData = \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', ['flexible_asset', 'ssl_certificate', 'domain'])
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

        // Gather licenses
        $licenses = $this->gatherLicenses($customer, $integrationCustomerIds);
        $licenseSummary = $this->getLicenseSummary($licenses);
        $licenseRecommendations = $this->getLicenseRecommendations($licenses);

        // Gather SSL certificates
        $certificates = $this->gatherCertificates($customer, $integrationCustomerIds);
        $sslSummary = $this->getCertificateSummary($certificates);
        $sslAlerts = $this->getCertificateAlerts($certificates);

        // Gather domains
        $domains = $this->gatherDomains($customer, $integrationCustomerIds);
        $domainSummary = $this->getDomainSummary($domains);
        $domainAlerts = $this->getDomainAlerts($domains);

        // Combine all alerts
        $allAlerts = array_merge($sslAlerts, $domainAlerts);

        // Create executive summary
        $executiveSummary = [
            'licenses' => [
                'total' => $licenseSummary['total_licenses'] ?? 0,
                'over_provisioned' => $licenseSummary['over_provisioned'] ?? 0,
                'well_utilized' => $licenseSummary['well_utilized'] ?? 0,
            ],
            'ssl_certificates' => [
                'total' => $sslSummary['total_certificates'] ?? 0,
                'critical' => $sslSummary['critical'] ?? 0,
                'warning' => $sslSummary['warning'] ?? 0,
            ],
            'domains' => [
                'total' => $domainSummary['total_domains'] ?? 0,
                'critical' => $domainSummary['critical'] ?? 0,
                'warning' => $domainSummary['warning'] ?? 0,
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
        return resource_path('views/Reports/ITGlueMonthlySummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 60, // seconds
        ];
    }

    // Helper methods for data gathering
    private function gatherLicenses(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'flexible_asset')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['asset_id'] ?? $item->integration_data_id;
        })->map(function($item) {
            $payload = $item->data_payload;
            $traits = $payload['traits'] ?? [];
            
            // ITGlue uses: active (total), consumed (used), unused (available)
            $active = $traits['active'] ?? $traits['purchased'] ?? $traits['total-seats'] ?? null;
            $consumed = $traits['consumed'] ?? $traits['assigned'] ?? $traits['used-seats'] ?? null;
            $unused = $traits['unused'] ?? $traits['available'] ?? null;
            
            return [
                'name' => $traits['license-name'] ?? $payload['name'] ?? 'Unknown License',
                'purchased' => $active,
                'assigned' => $consumed,
                'available' => $unused,
                'utilization_percentage' => $this->calculateUtilization($traits),
                'status' => $this->determineLicenseStatus($traits),
            ];
        })->values();
    }

    private function calculateUtilization(array $traits): int
    {
        // ITGlue uses: active (total), consumed (used)
        $active = $traits['active'] ?? $traits['purchased'] ?? $traits['total-seats'] ?? null;
        $consumed = $traits['consumed'] ?? $traits['assigned'] ?? $traits['used-seats'] ?? null;
        
        $total = is_numeric($active) ? (float)$active : 0;
        $used = is_numeric($consumed) ? (float)$consumed : 0;
        
        if ($total <= 0) return 0;
        return (int)round(($used / $total) * 100);
    }

    private function determineLicenseStatus(array $traits): string
    {
        $utilization = $this->calculateUtilization($traits);
        if ($utilization < 70) return 'over_provisioned';
        if ($utilization >= 90) return 'well_utilized';
        return 'normal';
    }

    private function getLicenseSummary(Collection $licenses): array
    {
        return [
            'total_licenses' => $licenses->count(),
            'over_provisioned' => $licenses->where('status', 'over_provisioned')->count(),
            'well_utilized' => $licenses->where('status', 'well_utilized')->count(),
        ];
    }

    private function getLicenseRecommendations(Collection $licenses): array
    {
        $recommendations = [];
        $overProvisioned = $licenses->where('status', 'over_provisioned');
        
        if ($overProvisioned->count() > 0) {
            $recommendations[] = "Consider reducing {$overProvisioned->count()} over-provisioned license(s) to optimize costs.";
        }
        
        return $recommendations;
    }

    private function gatherCertificates(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'ssl_certificate')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['certificate_id'] ?? $item->integration_data_id;
        })->map(function($item) {
            $payload = $item->data_payload;
            // SSL certificates stored as 'expires_on' in database (mapped from 'valid-until')
            $expiryDate = isset($payload['expires_on']) ? Carbon::parse($payload['expires_on']) : null;
            $daysUntilExpiry = $expiryDate ? now()->diffInDays($expiryDate, false) : null;
            
            $severity = 'healthy';
            if ($daysUntilExpiry !== null) {
                if ($daysUntilExpiry <= 0) $severity = 'expired';
                elseif ($daysUntilExpiry <= 7) $severity = 'critical';
                elseif ($daysUntilExpiry <= 30) $severity = 'warning';
            }
            
            return [
                'domain' => $payload['name'] ?? 'Unknown',
                'common_name' => $payload['host'] ?? null,
                'issuer' => $payload['issuer_organization'] ?? 'Not Available',
                'issued_by' => $payload['issued_by'] ?? null,
                'issuer_country' => $payload['issuer_country'] ?? null,
                'organization_name' => $payload['organization_name'] ?? null,
                'expiry_date_formatted' => $expiryDate ? $expiryDate->format('M d, Y') : 'Unknown',
                'days_until_expiry' => $daysUntilExpiry,
                'created_at' => $payload['created_at'] ?? null,
                'updated_at' => $payload['updated_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'resource_url' => $payload['resource_url'] ?? null,
                'severity' => $severity,
            ];
        })->sortBy('days_until_expiry')->values();
    }

    private function getCertificateSummary(Collection $certificates): array
    {
        return [
            'total_certificates' => $certificates->count(),
            'critical' => $certificates->whereIn('severity', ['expired', 'critical'])->count(),
            'warning' => $certificates->where('severity', 'warning')->count(),
        ];
    }

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

    private function gatherDomains(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'domain')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['domain_id'] ?? $item->integration_data_id;
        })->map(function($item) {
            $payload = $item->data_payload;
            $expiryDate = isset($payload['expires_on']) ? Carbon::parse($payload['expires_on']) : null;
            $daysUntilExpiry = $expiryDate ? now()->diffInDays($expiryDate, false) : null;
            
            $severity = 'healthy';
            if ($daysUntilExpiry !== null) {
                if ($daysUntilExpiry <= 0) $severity = 'expired';
                elseif ($daysUntilExpiry <= 14) $severity = 'critical';
                elseif ($daysUntilExpiry <= 30) $severity = 'warning';
            }
            
            return [
                'domain_name' => $payload['name'] ?? 'Unknown',
                'registrar' => $payload['registrar'] ?? null,
                'organization_name' => $payload['organization_name'] ?? null,
                'expiry_date_formatted' => $expiryDate ? $expiryDate->format('M d, Y') : 'Unknown',
                'days_until_expiry' => $daysUntilExpiry,
                'created_at' => $payload['created_at'] ?? null,
                'updated_at' => $payload['updated_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'resource_url' => $payload['resource_url'] ?? null,
                'severity' => $severity,
            ];
        })->sortBy('days_until_expiry')->values();
    }

    private function getDomainSummary(Collection $domains): array
    {
        return [
            'total_domains' => $domains->count(),
            'critical' => $domains->whereIn('severity', ['expired', 'critical'])->count(),
            'warning' => $domains->where('severity', 'warning')->count(),
        ];
    }

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
