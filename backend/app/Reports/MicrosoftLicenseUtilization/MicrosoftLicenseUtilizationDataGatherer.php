<?php

namespace App\Reports\MicrosoftLicenseUtilization;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MicrosoftLicenseUtilizationDataGatherer
{
    protected Customer $customer;
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct(Customer $customer, Carbon $startDate, Carbon $endDate)
    {
        $this->customer = $customer;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getLicenses(): Collection
    {
        // Get latest flexible asset data for this customer
        $licenses = IntegrationData::where('company_id', $this->customer->company_id)
            ->where('data_type', 'flexible_asset')
            ->whereHas('integrationCustomer', function($query) {
                $query->where('customer_id', $this->customer->customer_id);
            })
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique(function($item) {
                // Deduplicate by asset ID
                return $item->data_payload['asset_id'] ?? $item->integration_data_id;
            })
            ->filter(function($item) {
                // Filter for Microsoft licenses only
                $typeName = strtolower($item->data_payload['type_name'] ?? '');
                $name = strtolower($item->data_payload['name'] ?? '');
                
                return str_contains($typeName, 'microsoft') || 
                       str_contains($typeName, 'office') || 
                       str_contains($typeName, 'azure') ||
                       str_contains($typeName, 'license') ||
                       str_contains($name, 'microsoft') ||
                       str_contains($name, 'office') ||
                       str_contains($name, 'm365');
            })
            ->map(function($item) {
                $payload = $item->data_payload;
                $traits = $payload['traits'] ?? [];
                
                // Try different field names for seats (IT Glue flexible assets vary)
                // Handle both scalar and array values
                $totalSeats = $this->extractValue($traits, ['active', 'total_seats', 'seats', 'quantity', 'licenses']);
                $usedSeats = $this->extractValue($traits, ['consumed', 'used_seats', 'assigned', 'assigned_seats', 'in_use']);
                $monthlyCost = $this->extractValue($traits, ['monthly_cost', 'cost', 'price']);
                
                $availableSeats = max(0, $totalSeats - $usedSeats);
                $utilization = $totalSeats > 0 ? round(($usedSeats / $totalSeats) * 100, 2) : 0;
                $wastedCost = $totalSeats > 0 ? round(($availableSeats / $totalSeats) * $monthlyCost, 2) : 0;
                
                // Get license name from traits if available, otherwise use payload name
                $licenseName = $this->extractValue($traits, ['license-name', 'license_name', 'name']);
                if (empty($licenseName)) {
                    $licenseName = $payload['name'] ?? 'Unknown License';
                }
                
                return [
                    'asset_id' => $payload['asset_id'] ?? null,
                    'license_type' => $licenseName,
                    'type_name' => $payload['type_name'] ?? 'Unknown',
                    'total_seats' => (int) $totalSeats,
                    'used_seats' => (int) $usedSeats,
                    'available_seats' => (int) $availableSeats,
                    'utilization_percentage' => $utilization,
                    'monthly_cost' => (float) $monthlyCost,
                    'wasted_cost' => (float) $wastedCost,
                    'renewal_date' => $traits['renewal_date'] ?? $traits['expiry_date'] ?? null,
                    'last_updated' => $payload['last_updated_at'] ?? null,
                    'traits' => $traits, // Keep all traits for reference
                ];
            })
            ->sortByDesc('total_seats')
            ->values();

        return $licenses;
    }

    public function getSummary(Collection $licenses): array
    {
        $totalLicenses = $licenses->count();
        $totalSeats = $licenses->sum('total_seats');
        $usedSeats = $licenses->sum('used_seats');
        $availableSeats = $licenses->sum('available_seats');
        $totalMonthlyCost = $licenses->sum('monthly_cost');
        $wastedCost = $licenses->sum('wasted_cost');
        
        $utilizationPercentage = $totalSeats > 0 
            ? round(($usedSeats / $totalSeats) * 100, 2) 
            : 0;

        return [
            'total_licenses' => $totalLicenses,
            'total_seats' => $totalSeats,
            'used_seats' => $usedSeats,
            'available_seats' => $availableSeats,
            'utilization_percentage' => $utilizationPercentage,
            'total_monthly_cost' => $totalMonthlyCost,
            'wasted_cost' => $wastedCost,
        ];
    }

    public function getRecommendations(Collection $licenses): array
    {
        $recommendations = [];

        // Filter out free/unlimited licenses (they skew the data)
        $paidLicenses = $licenses->filter(function($license) {
            return $license['total_seats'] < 10000; // Assume licenses with 10k+ seats are free tiers
        });

        // Over-provisioned licenses (utilization < 70%)
        $overProvisioned = $paidLicenses->filter(function($license) {
            return $license['utilization_percentage'] < 70 && 
                   $license['utilization_percentage'] > 0 && 
                   $license['total_seats'] > 0;
        });

        if ($overProvisioned->count() > 0) {
            $wastedSeats = $overProvisioned->sum('available_seats');
            $recommendations[] = [
                'type' => 'reduce',
                'severity' => 'warning',
                'title' => 'Over-Provisioned Licenses',
                'description' => sprintf(
                    '%d license type(s) have utilization below 70%%. Consider reducing %d unused seats.',
                    $overProvisioned->count(),
                    $wastedSeats
                ),
                'wasted_seats' => $wastedSeats,
                'licenses' => $overProvisioned->values()->all(),
            ];
        }

        // Fully utilized licenses (utilization >= 95%)
        $fullyUtilized = $paidLicenses->filter(function($license) {
            return $license['utilization_percentage'] >= 95 && $license['total_seats'] > 0;
        });

        if ($fullyUtilized->count() > 0) {
            $recommendations[] = [
                'type' => 'increase',
                'severity' => 'info',
                'title' => 'Fully Utilized Licenses',
                'description' => sprintf(
                    '%d license type(s) are at or near full capacity (%s). Consider adding seats if needed.',
                    $fullyUtilized->count(),
                    $fullyUtilized->pluck('license_type')->take(3)->implode(', ')
                ),
                'licenses' => $fullyUtilized->values()->all(),
            ];
        }

        // Unused licenses (0 seats used) - exclude free tiers
        $unused = $paidLicenses->filter(function($license) {
            return $license['used_seats'] == 0 && $license['total_seats'] > 0;
        });

        if ($unused->count() > 0) {
            $wastedSeats = $unused->sum('total_seats');
            $recommendations[] = [
                'type' => 'remove',
                'severity' => 'high',
                'title' => 'Unused Licenses',
                'description' => sprintf(
                    '%d license type(s) (%d total seats) have zero usage. Consider canceling to eliminate waste.',
                    $unused->count(),
                    $wastedSeats
                ),
                'wasted_seats' => $wastedSeats,
                'licenses' => $unused->values()->all(),
            ];
        }

        return $recommendations;
    }

    /**
     * Extract value from traits, handling both scalar and array values
     * ITGlue stores some values as arrays with duplicate entries
     */
    private function extractValue(array $traits, array $possibleKeys): float
    {
        foreach ($possibleKeys as $key) {
            if (isset($traits[$key])) {
                $value = $traits[$key];
                
                // If it's an array, take the first non-empty value
                if (is_array($value)) {
                    $value = !empty($value) ? $value[0] : 0;
                }
                
                // Convert to float
                return (float) $value;
            }
        }
        
        return 0.0;
    }
}
