<?php

namespace App\Reports\SecuritySummary;

use App\Models\Company;
use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;

class SecuritySummary implements ReportInterface
{
    public static function getRequiredDataSources(): array
    {
        return ['security_alerts', 'vulnerabilities'];
    }

    public static function getRequiredHistoricalRange(): int
    {
        return 7; 
    }

    public function validate(Customer $customer): bool
    {
        
        return true;
    }

    public function generate(Customer $customer, $period): array
    {
        $dataGatherer = new SecurityDataGatherer($customer);
        $data = $dataGatherer->getAggregatedData($period);

        
        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => is_array($period) ? $period['start']->format('F j, Y') : $period,
                'end' => is_array($period) ? $period['end']->format('F j, Y') : now()->format('F j, Y'),
            ],
            'metrics' => [
                [
                    'label' => 'Critical Alerts',
                    'value' => $data['critical_alerts'] ?? '0',
                    'unit' => 'alerts',
                ],
                [
                    'label' => 'Vulnerabilities',
                    'value' => $data['vulnerability_count'] ?? '2',
                    'unit' => 'issues',
                ],
                [
                    'label' => 'Patch Compliance',
                    'value' => $data['patch_compliance'] ?? '98%',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Threat Level',
                    'value' => $data['threat_level'] ?? 'Low',
                    'unit' => 'status',
                ],
            ],
            'details' => $data,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/SecuritySummary/template.blade.php');
    }

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        
    }
}
