<?php

namespace App\Reports\MicrosoftLicenseUtilization;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class MicrosoftLicenseUtilization extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['flexible_asset'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 1; // Only need current data
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has any flexible asset data from ITGlue
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'ITGlue')
                      ->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            \Log::info("Microsoft License Report validation - No ITGlue integration customers", [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        // Check if there's any flexible asset data for these integration customers
        $hasData = \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'flexible_asset')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        \Log::info("Microsoft License Report validation", [
            'customer_id' => $customer->customer_id,
            'integration_customer_ids' => $integrationCustomerIds,
            'has_data' => $hasData,
        ]);

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $dataGatherer = new MicrosoftLicenseUtilizationDataGatherer($customer, $startDate, $endDate);
        
        $licenses = $dataGatherer->getLicenses();
        $summary = $dataGatherer->getSummary($licenses);
        $recommendations = $dataGatherer->getRecommendations($licenses);

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
            'summary' => [
                'total_licenses' => $summary['total_licenses'],
                'total_seats' => $summary['total_seats'],
                'used_seats' => $summary['used_seats'],
                'available_seats' => $summary['available_seats'],
                'utilization_percentage' => $summary['utilization_percentage'],
                'total_monthly_cost' => $summary['total_monthly_cost'],
                'wasted_cost' => $summary['wasted_cost'],
            ],
            'licenses' => $licenses,
            'recommendations' => $recommendations,
            'data' => [
                'all_licenses' => $licenses,
            ],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/MicrosoftLicenseUtilization/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'microsoft_license_utilization',
            'name' => 'Microsoft License Utilization Report',
            'description' => 'Track Microsoft 365 license consumption and cost optimization',
            'category' => 'licensing',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly'],
            'formats' => ['pdf', 'html', 'xlsx', 'csv'],
        ];
    }
}
