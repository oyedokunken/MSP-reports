<?php

namespace App\Reports\BackupStatus;

use App\Models\Company;
use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;

class BackupStatus implements ReportInterface
{
    public static function getRequiredDataSources(): array
    {
        return ['backup_status', 'backup_jobs'];
    }

    public static function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days of backup history
    }

    public function validate(Customer $customer): bool
    {
        // Always return true for now - will implement proper validation later
        return true;
    }

    public function generate(Customer $customer, $period): array
    {
        $dataGatherer = new BackupDataGatherer($customer);
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
                    'label' => 'Success Rate',
                    'value' => $data['success_rate'] ?? '0',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Total Backups',
                    'value' => $data['total_backups'] ?? '0',
                    'unit' => 'backups',
                ],
                [
                    'label' => 'Failed Backups',
                    'value' => $data['failed_backups'] ?? '0',
                    'unit' => 'failures',
                ],
                [
                    'label' => 'Storage Used',
                    'value' => $data['storage_used_gb'] ?? '0',
                    'unit' => 'GB',
                ],
            ],
            'data' => $data,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/BackupStatus/template.blade.php');
    }

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        // TODO: Implement deliver method
    }
}
