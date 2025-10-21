<?php

namespace App\Services;

use App\Models\Company;
use App\Models\License;

class LicenseChecker
{
    /**
     * Check if a company has a valid license for a specific feature.
     * 
     * @param Company $company
     * @param string $productSku
     * @return bool
     */
    public function hasLicense(Company $company, string $productSku): bool
    {
        // Check if license enforcement is enabled
        $licenseEnforcement = $company->settings['licensing']['license_enforcement'] ?? false;

        if (!$licenseEnforcement) {
            // License enforcement disabled (e.g., first customer with unlimited license)
            return true;
        }

        // Check for active license
        return $company->licenses()
            ->where('product_sku', $productSku)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->exists();
    }

    /**
     * Check if a company can activate an integration type.
     * 
     * @param Company $company
     * @param string $integrationType
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public function canActivateIntegration(Company $company, string $integrationType): array
    {
        $productSku = "integration_{$integrationType}";

        if (!$this->hasLicense($company, $productSku)) {
            return [
                'allowed' => false,
                'reason' => "No active license for {$integrationType} integration.",
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check if a company can extend data retention period.
     * 
     * @param Company $company
     * @param int $requestedMonths
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public function canExtendRetention(Company $company, int $requestedMonths): array
    {
        // Check for retention licenses
        $retentionLicense = $company->licenses()
            ->where('license_type', 'retention')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->first();

        if (!$retentionLicense) {
            return [
                'allowed' => false,
                'reason' => 'No active retention license.',
            ];
        }

        // Check if requested months exceeds license allowance
        $allowedMonths = $retentionLicense->metadata['max_retention_months'] ?? 12;

        if ($requestedMonths > $allowedMonths) {
            return [
                'allowed' => false,
                'reason' => "Retention period exceeds license limit of {$allowedMonths} months.",
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Get all active licenses for a company.
     * 
     * @param Company $company
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveLicenses(Company $company)
    {
        return $company->licenses()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->get();
    }

    /**
     * Check for expiring licenses and return warnings.
     * 
     * @param Company $company
     * @param int $daysThreshold
     * @return array
     */
    public function getExpiringLicenses(Company $company, int $daysThreshold = 30): array
    {
        $expiringLicenses = $company->licenses()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($daysThreshold)])
            ->get();

        return $expiringLicenses->map(function ($license) {
            return [
                'product_sku' => $license->product_sku,
                'license_type' => $license->license_type,
                'expiry_date' => $license->expiry_date,
                'days_until_expiry' => now()->diffInDays($license->expiry_date),
            ];
        })->toArray();
    }
}
