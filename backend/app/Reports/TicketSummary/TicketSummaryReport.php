<?php

namespace App\Reports\TicketSummary;

use App\Models\Company;
use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;

class TicketSummaryReport implements ReportInterface
{
    public static function getRequiredDataSources(): array
    {
        return ['tickets', 'time_entries'];
    }

    public static function getRequiredHistoricalRange(): int
    {
        return 30; // 30 days of historical data
    }

    public function validate(Customer $customer): bool
    {
        // Check if customer has Autotask integration
        $hasAutotask = $customer->company->integrations()
            ->where('integration_type', 'Autotask')
            ->where('status', 'active')
            ->exists();

        if (!$hasAutotask) {
            \Log::warning('Customer company does not have active Autotask integration', [
                'customer_id' => $customer->customer_id,
                'company_id' => $customer->company_id,
            ]);
            return false;
        }

        return true;
    }

    public function generate(Customer $customer, $period): array
    {
        $dataGatherer = new TicketSummaryDataGatherer($customer);
        $data = $dataGatherer->getReportData($period);

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => is_array($period) ? $period['start']->format('F j, Y') : now()->subMonth()->format('F j, Y'),
                'end' => is_array($period) ? $period['end']->format('F j, Y') : now()->format('F j, Y'),
            ],
            'summary' => $data['summary'],
            'tickets' => $data['tickets'],
            'time_entries' => $data['time_entries'],
            'top_issues' => $data['top_issues'],
            'technicians' => $data['technicians'],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/TicketSummary/template.blade.php');
    }

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        // TODO: Implement email delivery
    }
}
