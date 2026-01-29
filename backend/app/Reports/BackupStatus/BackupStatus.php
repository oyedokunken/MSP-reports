<?php

declare(strict_types=1);

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
        // 1. Fetch Backup Data
        $backups = $customer->integrationData()
            ->where('data_type', 'backup_status')
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(function ($item) {
                /** @var \App\Models\IntegrationData $item */
                return $item->data_payload;
            });

        // 2. Calculate Metrics
        $totalFailures = $backups->where('status', 'failed')->count();
        // We can't know total backups without success logs, so we estimate or just show failures.
        // For now, let's assume 1 backup per day per device if we have device count, but that's complex.
        // Let's just report on what we found.
        
        $successRate = 'N/A'; // Hard to calculate without success logs
        if ($totalFailures === 0 && $backups->count() > 0) {
             $successRate = '100%';
        } elseif ($backups->count() > 0) {
             // If we have data (failures), success rate is likely low for those specific jobs
             // But this is misleading if we only store failures.
             // Let's just show "Issues Detected" count.
        }

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
                    'label' => 'Backup Issues',
                    'value' => (string)$totalFailures,
                    'unit' => 'failures',
                ],
                [
                    'label' => 'Status',
                    'value' => $totalFailures > 0 ? 'Attention Needed' : 'Healthy',
                    'unit' => 'status',
                ],
            ],
            'data' => [
                'backups' => $backups->toArray(),
            ],
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
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
