<?php

namespace App\Reports\MonthlyComprehensive;

use App\Models\Company;
use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;

class MonthlyComprehensive implements ReportInterface
{
    public static function getRequiredDataSources(): array
    {
        return ['uptime', 'tickets', 'backup_status', 'security_alerts'];
    }

    public static function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days for monthly report
    }

    public function validate(Customer $customer): bool
    {
        // Always return true for now - will implement proper validation later
        return true;
    }

    public function generate(Customer $customer, $period): array
    {
        $dataGatherer = new ComprehensiveDataGatherer($customer);
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
                    'label' => 'Overall Health Score',
                    'value' => $data['health_score'] ?? '0',
                    'unit' => 'score',
                ],
                [
                    'label' => 'Average Uptime',
                    'value' => $data['summary']['avg_uptime'] ?? '0',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Tickets Resolved',
                    'value' => $data['summary']['tickets_resolved'] ?? '0',
                    'unit' => 'tickets',
                ],
                [
                    'label' => 'Backup Success Rate',
                    'value' => $data['summary']['backup_success_rate'] ?? '0',
                    'unit' => 'percentage',
                ],
            ],
            'data' => $data,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/MonthlyComprehensive/template.blade.php');
    }

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        // TODO: Implement deliver method
    }
}
