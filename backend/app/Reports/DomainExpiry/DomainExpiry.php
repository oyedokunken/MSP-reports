<?php

namespace App\Reports\DomainExpiry;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class DomainExpiry extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['domain'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 1; // Only need current data
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has any domain data from ITGlue
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'ITGlue')
                      ->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            \Log::info("Domain Expiry Report validation - No ITGlue integration customers", [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        // Check if there's any domain data for these integration customers
        $hasData = \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'domain')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        \Log::info("Domain Expiry Report validation", [
            'customer_id' => $customer->customer_id,
            'integration_customer_ids' => $integrationCustomerIds,
            'has_data' => $hasData,
        ]);

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $dataGatherer = new DomainExpiryDataGatherer($customer, $startDate, $endDate);
        
        $domains = $dataGatherer->getDomains();
        $summary = $dataGatherer->getSummary($domains);
        $alerts = $dataGatherer->getAlerts($domains);

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
                'total_domains' => $summary['total_domains'],
                'expiring_soon' => $summary['expiring_soon'],
                'critical' => $summary['critical'],
                'warning' => $summary['warning'],
                'healthy' => $summary['healthy'],
            ],
            'domains' => $domains->toArray(),
            'alerts' => $alerts,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/DomainExpiry/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 30, // seconds
        ];
    }
}
