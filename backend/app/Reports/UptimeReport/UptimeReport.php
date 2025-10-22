<?php

namespace App\Reports\UptimeReport;

use App\Models\Company;
use App\Models\Customer;
use App\Reports\Contracts\ReportInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReportDeliveryMail;

class UptimeReport implements ReportInterface
{
    public static function getRequiredDataSources(): array
    {
        return ['uptime', 'device_health'];
    }

    public static function getRequiredHistoricalRange(): int
    {
        return 30; 
    }

    public function validate(Customer $customer): bool
    {
        
        return true;
    }

    public function generate(Customer $customer, $period): array
    {
        $dataGatherer = new UptimeDataGatherer($customer);
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
                    'label' => 'Average Uptime',
                    'value' => $data['avg_uptime'] ?? '99.9%',
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Total Downtime',
                    'value' => $data['total_downtime'] ?? '2',
                    'unit' => 'hours',
                ],
                [
                    'label' => 'Incidents',
                    'value' => $data['incident_count'] ?? '3',
                    'unit' => 'events',
                ],
                [
                    'label' => 'MTTR',
                    'value' => $data['mttr'] ?? '45',
                    'unit' => 'minutes',
                ],
            ],
            'details' => $data,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/UptimeReport/template.blade.php');
    }

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        // TODO: Implement deliver method   
    }
}
