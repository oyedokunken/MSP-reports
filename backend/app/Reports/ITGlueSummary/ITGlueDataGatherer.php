<?php

declare(strict_types=1);

namespace App\Reports\ITGlueSummary;

use App\Integrations\Contracts\IntegrationDataType;
use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ITGlueDataGatherer
{
    /**
     * Gather license data (flexible assets) for a customer
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherLicenses(Customer $customer, array $integrationCustomerIds, ?Carbon $endDate = null, int $activityWindowDays = 5): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $query = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', IntegrationDataType::FLEXIBLE_ASSETS)
            ->whereIn('integration_customer_id', $integrationCustomerIds);

        if ($endDate) {
            $query->where('data_timestamp', '>=', $endDate->copy()->subDays($activityWindowDays)->startOfDay())
                  ->where('data_timestamp', '<=', $endDate->copy()->endOfDay());
        }

        $rawData = $query->orderBy('data_timestamp', 'desc')->get();
        // Filter for only Microsoft License flexible asset types

        // Filter for only Microsoft License flexible asset types
        $licenseData = $rawData->filter(function($item) {
            $typeName = strtolower($item->data_payload['type_name'] ?? '');
            $assetName = strtolower($item->data_payload['name'] ?? '');
            
            // If the type name or asset name contains license keyword, it's likely a license
            $isLicenseType = str_contains($typeName, 'license') || str_contains($assetName, 'license');
            $isMicrosoftProduct = str_contains($typeName, 'microsoft') || str_contains($typeName, 'office 365') || str_contains($typeName, 'o365') ||
                                 str_contains($assetName, 'microsoft') || str_contains($assetName, 'office 365') || str_contains($assetName, 'o365');

            return $isLicenseType || ($isMicrosoftProduct && !empty($item->data_payload['traits']));
        });

        return $licenseData->unique(function($item) {
            return $item->data_payload['asset_id'] ?? $item->integration_data_id;
        })->map(function($item) {
            $payload = $item->data_payload;
            $traits = $payload['traits'] ?? [];
            
            // ITGlue uses various trait names: active (total), consumed (used), unused (available)
            // Common variations include: seats, purchased, total-seats, etc.
            $active = $this->extractValue($traits['active'] ?? $traits['purchased'] ?? $traits['total-seats'] ?? $traits['seats'] ?? $traits['licenses'] ?? null);
            $consumed = $this->extractValue($traits['consumed'] ?? $traits['assigned'] ?? $traits['used-seats'] ?? $traits['in-use'] ?? null);
            $unused = $this->extractValue($traits['unused'] ?? $traits['available'] ?? null);
            $licenseName = $this->extractValue($traits['license-name'] ?? $traits['product'] ?? $payload['name'] ?? 'Unknown License');
            
            // Calculate utilization using the EXTRACTED values, not traits
            $utilization = $this->calculateUtilizationFromValues($active, $consumed);
            $status = $this->determineStatusFromUtilization($utilization);
            
            return [
                'name' => $licenseName,
                'purchased' => $active,
                'assigned' => $consumed,
                'available' => $unused,
                'utilization_percentage' => $utilization,
                'status' => $status,
            ];
        })->values();
    }

    /**
     * Gather SSL certificate data for a customer
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param array<string, mixed> $options
     * @return Collection<int, mixed>
     */
    public function gatherCertificates(Customer $customer, array $integrationCustomerIds, array $options = [], ?Carbon $endDate = null, int $activityWindowDays = 5): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $query = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', IntegrationDataType::SSL_CERTIFICATE)
            ->whereIn('integration_customer_id', $integrationCustomerIds);

        if ($endDate) {
            $query->where('data_timestamp', '>=', $endDate->copy()->subDays($activityWindowDays)->startOfDay())
                  ->where('data_timestamp', '<=', $endDate->copy()->endOfDay());
        }

        $rawData = $query->orderBy('data_timestamp', 'desc')->get();

        $timezone = $options['timezone'] ?? config('app.timezone');

        return $rawData->unique(function($item) {
            return $item->data_payload['certificate_id'] ?? $item->integration_data_id;
        })->map(function($item) use ($timezone) {
            $payload = $item->data_payload;
            // SSL certificates stored as 'expires_on' in database (mapped from 'valid-until')
            $expiryDate = $this->safeParseDate($payload['expires_on'] ?? null);
            
            // Calculate relative to client's "now"
            $clientNow = now()->setTimezone($timezone);
            $daysUntilExpiry = $expiryDate ? $clientNow->diffInDays($expiryDate, false) : null;
            
            $severity = 'healthy';
            if ($daysUntilExpiry !== null) {
                if ($daysUntilExpiry <= 0) $severity = 'expired';
                elseif ($daysUntilExpiry <= 7) $severity = 'critical';
                elseif ($daysUntilExpiry <= 30) $severity = 'warning';
            }
            
            // Format for display in client's timezone
            $formattedExpiry = $expiryDate ? $expiryDate->setTimezone($timezone)->format('M d, Y') : 'Unknown';
            
            return [
                'domain' => $payload['name'] ?? 'Unknown',
                'common_name' => $payload['host'] ?? null,
                'issuer' => $payload['issuer_organization'] ?? 'Not Available',
                'issued_by' => $payload['issued_by'] ?? null,
                'issuer_country' => $payload['issuer_country'] ?? null,
                'organization_name' => $payload['organization_name'] ?? null,
                'expiry_date_formatted' => $formattedExpiry,
                'days_until_expiry' => (int) $daysUntilExpiry,
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
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param array<string, mixed> $options
     * @return Collection<int, mixed>
     */
    public function gatherDomains(Customer $customer, array $integrationCustomerIds, array $options = [], ?Carbon $endDate = null, int $activityWindowDays = 5): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $query = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', IntegrationDataType::DOMAINS)
            ->whereIn('integration_customer_id', $integrationCustomerIds);

        if ($endDate) {
            $query->where('data_timestamp', '>=', $endDate->copy()->subDays($activityWindowDays)->startOfDay())
                  ->where('data_timestamp', '<=', $endDate->copy()->endOfDay());
        }

        $rawData = $query->orderBy('data_timestamp', 'desc')->get();

        $timezone = $options['timezone'] ?? config('app.timezone');

        return $rawData->unique(function($item) {
            return $item->data_payload['domain_id'] ?? $item->integration_data_id;
        })->map(function($item) use ($timezone) {
            $payload = $item->data_payload;
            $expiryDate = $this->safeParseDate($payload['expires_on'] ?? null);
            
            // Calculate relative to client's "now"
            $clientNow = now()->setTimezone($timezone);
            $daysUntilExpiry = $expiryDate ? $clientNow->diffInDays($expiryDate, false) : null;
            
            $severity = 'healthy';
            if ($daysUntilExpiry !== null) {
                if ($daysUntilExpiry <= 0) $severity = 'expired';
                elseif ($daysUntilExpiry <= 14) $severity = 'critical';
                elseif ($daysUntilExpiry <= 30) $severity = 'warning';
            }
            
            $formattedExpiry = $expiryDate ? $expiryDate->setTimezone($timezone)->format('M d, Y') : 'Unknown';
            
            return [
                'domain_name' => $payload['name'] ?? 'Unknown',
                'registrar' => $payload['registrar'] ?? null,
                'organization_name' => $payload['organization_name'] ?? null,
                'expiry_date_formatted' => $formattedExpiry,
                'days_until_expiry' => (int) $daysUntilExpiry,
                'created_at' => $payload['created_at'] ?? null,
                'updated_at' => $payload['updated_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'resource_url' => $payload['resource_url'] ?? null,
                'severity' => $severity,
            ];
        })->sortBy('days_until_expiry')->values();
    }

    /**
     * Safely parse a date string, returning null if invalid
     */
    private function safeParseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }
        
        // Skip known invalid values
        $invalidValues = ['processing', 'pending', 'unknown', 'n/a', 'tba', 'tbd'];
        if (in_array(strtolower(trim($dateString)), $invalidValues, true)) {
            return null;
        }
        
        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            \Log::warning('Failed to parse date', ['date' => $dateString]);
            return null;
        }
    }

    /**
     * Extract first value from array or return as-is
     * 
     * @param mixed $value
     * @return mixed
     */
    private function extractValue($value)
    {
        if (is_array($value)) {
            return !empty($value) ? $value[0] : null;
        }
        return $value;
    }

    /**
     * Calculate license utilization percentage from extracted values
     * 
     * @param mixed $total
     * @param mixed $used
     * @return int
     */
    private function calculateUtilizationFromValues($total, $used): int
    {
        // Convert to float, handling null/empty values
        $totalFloat = is_numeric($total) ? (float)$total : 0;
        $usedFloat = is_numeric($used) ? (float)$used : 0;
        
        // Edge case: If we have assigned licenses but no total, treat as 100% utilized
        if ($totalFloat <= 0) {
            if ($usedFloat > 0) {
                \Log::warning('ITGlue license has assigned seats but no total', [
                    'used' => $usedFloat,
                    'total' => $totalFloat,
                ]);
                return 100; // Fully utilized (or over-utilized)
            }
            return 0; // No licenses at all
        }
        
        $utilization = (int)round(($usedFloat / $totalFloat) * 100);
        
        // Cap at 100% for display purposes (though can be >100% if over-allocated)
        return min($utilization, 100);
    }

    /**
     * Determine license status based on utilization percentage
     */
    private function determineStatusFromUtilization(int $utilization): string
    {
        // If no licenses are involved at all, it's well utilized (nothing to do)
        if ($utilization === 0) return 'well_utilized';

        // Under 70% = too many unused licenses
        if ($utilization < 70) return 'over_provisioned';
        
        // Over 100% = more assigned than purchased (need more licenses)
        if ($utilization > 100) return 'under_provisioned';
        
        // 70-100% = healthy utilization range
        return 'well_utilized';
    }
}
