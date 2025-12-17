<?php

namespace App\Reports\ITGlueMonthlySummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ITGlueDataGatherer
{
    /**
     * Gather license data (flexible assets) for a customer
     */
    public function gatherLicenses(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'flexible_asset')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get();

        // Filter for only Microsoft License flexible asset types
        $licenseData = $rawData->filter(function($item) {
            $typeName = strtolower($item->data_payload['type_name'] ?? '');
            // Only include assets that are explicitly license types
            return str_contains($typeName, 'microsoft') && str_contains($typeName, 'license') ||
                   str_contains($typeName, 'office 365') && str_contains($typeName, 'license') ||
                   str_contains($typeName, 'o365') && str_contains($typeName, 'license');
        });

        return $licenseData->unique(function($item) {
            return $item->data_payload['asset_id'] ?? $item->integration_data_id;
        })->map(function($item) {
            $payload = $item->data_payload;
            $traits = $payload['traits'] ?? [];
            
            // ITGlue uses: active (total), consumed (used), unused (available)
            $active = $this->extractValue($traits['active'] ?? $traits['purchased'] ?? $traits['total-seats'] ?? null);
            $consumed = $this->extractValue($traits['consumed'] ?? $traits['assigned'] ?? $traits['used-seats'] ?? null);
            $unused = $this->extractValue($traits['unused'] ?? $traits['available'] ?? null);
            $licenseName = $this->extractValue($traits['license-name'] ?? $payload['name'] ?? 'Unknown License');
            
            return [
                'name' => $licenseName,
                'purchased' => $active,
                'assigned' => $consumed,
                'available' => $unused,
                'utilization_percentage' => $this->calculateUtilization($traits),
                'status' => $this->determineLicenseStatus($traits),
            ];
        })->values();
    }

    /**
     * Gather SSL certificate data for a customer
     */
    public function gatherCertificates(Customer $customer, array $integrationCustomerIds): Collection
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

    /**
     * Gather domain registration data for a customer
     */
    public function gatherDomains(Customer $customer, array $integrationCustomerIds): Collection
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

    /**
     * Extract first value from array or return as-is
     */
    private function extractValue($value)
    {
        if (is_array($value)) {
            return !empty($value) ? $value[0] : null;
        }
        return $value;
    }

    /**
     * Calculate license utilization percentage
     */
    private function calculateUtilization(array $traits): int
    {
        // ITGlue uses: active (total), consumed (used)
        $activeRaw = $traits['active'] ?? $traits['purchased'] ?? $traits['total-seats'] ?? null;
        $consumedRaw = $traits['consumed'] ?? $traits['assigned'] ?? $traits['used-seats'] ?? null;
        
        // Extract values from arrays if needed (ITGlue sometimes returns arrays)
        $active = $this->extractValue($activeRaw);
        $consumed = $this->extractValue($consumedRaw);
        
        $total = is_numeric($active) ? (float)$active : 0;
        $used = is_numeric($consumed) ? (float)$consumed : 0;
        
        if ($total <= 0) return 0;
        return (int)round(($used / $total) * 100);
    }

    /**
     * Determine license status based on utilization
     */
    private function determineLicenseStatus(array $traits): string
    {
        $utilization = $this->calculateUtilization($traits);
        if ($utilization < 70) return 'over_provisioned';
        if ($utilization >= 90) return 'under_provisioned';
        return 'well_utilized';
    }
}
