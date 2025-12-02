<?php

namespace App\Reports\BackupStatus;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class BackupStatus extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['backup_status'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // TODO: Update to use BaseDataGatherer when BackupDataGatherer is refactored
        // For now, return placeholder data
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
            // Branding is now handled by ReportGenerationService::applyBranding()
            'metrics' => [
                [
                    'label' => 'Success Rate',
                    'value' => '95%',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Total Backups',
                    'value' => '30',
                    'unit' => 'backups',
                ],
                [
                    'label' => 'Failed Backups',
                    'value' => '2',
                    'unit' => 'failures',
                ],
                [
                    'label' => 'Storage Used',
                    'value' => '500',
                    'unit' => 'GB',
                ],
            ],
            'data' => [],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/BackupStatus/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'backup_status',
            'name' => 'Backup Status Report',
            'description' => 'Backup success rates, failures, and storage metrics from backup solutions',
            'category' => 'backup',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['daily', 'weekly', 'monthly'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
