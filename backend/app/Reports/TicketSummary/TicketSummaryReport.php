<?php

namespace App\Reports\TicketSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class TicketSummaryReport extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['ticket', 'time_entry'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Use TicketSummaryDataGatherer to get real data
        $dataGatherer = new TicketSummaryDataGatherer($customer);
        $reportData = $dataGatherer->getReportData([
            'start' => $startDate,
            'end' => $endDate,
        ]);

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer, // Pass full customer object
            'customer_data' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'branding' => $this->getBranding(),
            'reportData' => $reportData, // Real ticket data
            'data' => $reportData, // Also pass as 'data' for template compatibility
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
