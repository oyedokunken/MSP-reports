<?php

namespace App\Reports\TicketSummary;

use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;
use Carbon\Carbon;

class TicketSummary implements ReportInterface
{
    public function getRequiredDataTypes(): array
    {
        return ['ticket', 'ticket_event'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days of ticket history
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Always return true for now - will implement proper validation later
        return true;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $dataGatherer = new TicketDataGatherer($customer);
        $data = $dataGatherer->getAggregatedData([
            'start' => $startDate,
            'end' => $endDate,
        ]);

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => $startDate->format('F j, Y'),
                'end' => $endDate->format('F j, Y'),
                'formatted' => $startDate->format('F j, Y') . ' - ' . $endDate->format('F j, Y'),
            ],
            'metrics' => [
                [
                    'label' => 'Tickets Opened',
                    'value' => $data['tickets_opened'] ?? '0',
                    'unit' => 'tickets',
                ],
                [
                    'label' => 'Tickets Closed',
                    'value' => $data['tickets_closed'] ?? '0',
                    'unit' => 'tickets',
                ],
                [
                    'label' => 'Avg Response Time',
                    'value' => $data['avg_response_time_hours'] ?? '0',
                    'unit' => 'hours',
                ],
                [
                    'label' => 'SLA Compliance',
                    'value' => $data['sla_compliance_rate'] ?? '0',
                    'unit' => 'percentage',
                ],
            ],
            'data' => $data,
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
            'description' => 'Summary of ticket activity, response times, and SLA compliance',
            'category' => 'tickets',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['daily', 'weekly', 'monthly'],
            'formats' => ['pdf', 'html'],
        ];
    }
}
