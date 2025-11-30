<?php

namespace App\Reports\MonthlyComprehensive;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class MonthlyComprehensive extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['uptime_metric', 'ticket_event', 'backup_status', 'security_alert'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30;
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
                    'label' => 'Overall Health Score',
                    'value' => '95%',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Uptime',
                    'value' => '99.9%',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Tickets Resolved',
                    'value' => '22',
                    'unit' => 'tickets',
                ],
                [
                    'label' => 'Backup Success Rate',
                    'value' => '95%',
                    'unit' => 'percentage',
                ],
            ],
            'data' => [],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/MonthlyComprehensive/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'monthly_comprehensive',
            'name' => 'Monthly Comprehensive Report',
            'description' => 'Complete monthly overview combining uptime, tickets, backups, and security metrics',
            'category' => 'executive',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
