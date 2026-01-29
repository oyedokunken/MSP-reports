<?php

declare(strict_types=1);

namespace App\Reports\SecuritySummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class SecuritySummary extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['security_alert', 'security_incident'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 7;
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Get integration customer IDs for this customer
        $integrationCustomerIds = $customer->integrationCustomers()
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        // Check if there's any security data
        $hasData = \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        return $hasData;
    }

    /**
     * @param Customer $customer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get integration customer IDs for this customer
        $integrationCustomerIds = $customer->integrationCustomers()
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // 1. Fetch Security Alerts from IntegrationData
        $alerts = \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'security_alert')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(function ($item) {
                return $item->data_payload;
            });

        // 2. Calculate Metrics
        $totalAlerts = $alerts->count();
        $criticalAlerts = $alerts->whereIn('severity', ['critical', 'high'])->count();
        
        // Calculate Threat Level based on critical alerts
        $threatLevel = 'Low';
        if ($criticalAlerts > 0) $threatLevel = 'High';
        elseif ($totalAlerts > 10) $threatLevel = 'Medium';

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'metrics' => [
                [
                    'label' => 'Total Alerts',
                    'value' => (string)$totalAlerts,
                    'unit' => 'alerts',
                ],
                [
                    'label' => 'Critical Alerts',
                    'value' => (string)$criticalAlerts,
                    'unit' => 'alerts',
                ],
                [
                    'label' => 'Threat Level',
                    'value' => $threatLevel,
                    'unit' => 'status',
                ],
            ],
            'data' => [
                'alerts' => $alerts->toArray(),
            ],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/SecuritySummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'security_summary',
            'name' => 'Security Summary Report',
            'description' => 'Security alerts, incidents, and threat analysis from security monitoring systems',
            'category' => 'security',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
