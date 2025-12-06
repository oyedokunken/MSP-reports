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
     * Calculate license utilization percentage
     */
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

    /**
     * Determine license status based on utilization
     */
    private function determineLicenseStatus(array $traits): string
    {
        $utilization = $this->calculateUtilization($traits);
        if ($utilization < 70) return 'over_provisioned';
        if ($utilization >= 90) return 'well_utilized';
        return 'normal';
    }
}
