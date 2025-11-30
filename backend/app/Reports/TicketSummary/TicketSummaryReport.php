<?php

namespace App\Reports\TicketSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class TicketSummaryReport extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['ticket_event', 'time_entry'];
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
                    'label' => 'Tickets Opened',
                    'value' => '25',
                    'unit' => 'tickets',
                ],
                [
                    'label' => 'Tickets Closed',
                    'value' => '22',
                    'unit' => 'tickets',
                ],
                [
                    'label' => 'Avg Response Time',
                    'value' => '2.5',
                    'unit' => 'hours',
                ],
                [
                    'label' => 'SLA Compliance',
                    'value' => '95%',
                    'unit' => 'percentage',
                ],
            ],
            'data' => [],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/TicketSummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'ticket_summary',
            'name' => 'Ticket Summary Report',
            'description' => 'Ticket statistics, response times, and SLA compliance metrics from PSA systems',
            'category' => 'service_desk',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['weekly', 'monthly'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
