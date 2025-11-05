<?php

namespace App\Reports\TicketSummary;

use App\Models\Company;
use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;

class TicketSummary implements ReportInterface
{
    public static function getRequiredDataSources(): array
    {
        return ['tickets', 'ticket_events'];
    }

    public static function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days of ticket history
    }

    public function validate(Customer $customer): bool
    {
        // Always return true for now - will implement proper validation later
        return true;
    }

    public function generate(Customer $customer, $period): array
    {
        $dataGatherer = new TicketDataGatherer($customer);
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

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        // TODO: Implement deliver method
    }
}
