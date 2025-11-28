<?php

namespace App\Services;

use App\Models\Company;
use App\Models\License;

class LicenseChecker
{
    public function hasLicense(Company $company, string $productSku): bool
    {
        $licenseEnforcement = $company->settings['licensing']['license_enforcement'] ?? false;

        if (!$licenseEnforcement) {
            
            return true;
        }

        return $company->licenses()
            ->where('product_sku', $productSku)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->exists();
    }

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

    public function canExtendRetention(Company $company, int $requestedMonths): array
    { 
        // Check for unlimited license first
        $unlimitedLicense = $company->licenses()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('product_sku', 'unlimited')
                      ->orWhere('license_type', 'unlimited');
            })
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->first();

        if ($unlimitedLicense) {
            return ['allowed' => true, 'reason' => null];
        }

        // Check for specific retention license
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
        
        $allowedMonths = $retentionLicense->metadata['max_retention_months'] ?? 12;

        if ($requestedMonths > $allowedMonths) {
            return [
                'allowed' => false,
                'reason' => "Retention period exceeds license limit of {$allowedMonths} months.",
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

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

    public function canCreateUser(Company $company): array
    {
        $licenseEnforcement = $company->settings['licensing']['license_enforcement'] ?? false;

        if (!$licenseEnforcement) {
            return ['allowed' => true, 'reason' => null];
        }

        $userLicense = $company->licenses()
            ->where('license_type', 'users')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->first();

        if (!$userLicense) {
            return [
                'allowed' => false,
                'reason' => 'No active user license. Contact sales to add more users.',
            ];
        }

        if ($userLicense->product_sku === 'unlimited' || $userLicense->quantity === null) {
            return ['allowed' => true, 'reason' => null];
        }

        $currentUserCount = $company->users()->count();

        if ($currentUserCount >= $userLicense->quantity) {
            return [
                'allowed' => false,
                'reason' => "User limit reached ({$userLicense->quantity} users). Upgrade license to add more users.",
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function getUserLicenseStatus(Company $company): array
    {
        $userLicense = $company->licenses()
            ->where('license_type', 'users')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->first();

        $currentUserCount = $company->users()->count();

        if (!$userLicense) {
            return [
                'licensed' => false,
                'current_users' => $currentUserCount,
                'max_users' => 0,
                'remaining' => 0,
            ];
        }

        $maxUsers = $userLicense->quantity ?? PHP_INT_MAX;
        $isUnlimited = $userLicense->product_sku === 'unlimited' || $userLicense->quantity === null;

        return [
            'licensed' => true,
            'current_users' => $currentUserCount,
            'max_users' => $isUnlimited ? 'unlimited' : $maxUsers,
            'remaining' => $isUnlimited ? 'unlimited' : max(0, $maxUsers - $currentUserCount),
            'is_unlimited' => $isUnlimited,
        ];
    }

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
