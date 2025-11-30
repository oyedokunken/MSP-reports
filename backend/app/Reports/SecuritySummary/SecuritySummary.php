<?php

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

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // TODO: Update to use BaseDataGatherer
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
            'branding' => $this->getBranding(),
            'metrics' => [
                [
                    'label' => 'Critical Alerts',
                    'value' => '0',
                    'unit' => 'alerts',
                ],
                [
                    'label' => 'Vulnerabilities',
                    'value' => '2',
                    'unit' => 'issues',
                ],
                [
                    'label' => 'Patch Compliance',
                    'value' => '98%',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Threat Level',
                    'value' => 'Low',
                    'unit' => 'status',
                ],
            ],
            'data' => [],
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
            'frequencies' => ['weekly', 'monthly'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
