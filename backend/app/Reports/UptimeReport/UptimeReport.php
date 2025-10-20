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
        return 30; // monthly range
    }

    public function validate(Customer $customer): bool
    {
        // TODO: Check if required uptime data exists
        return true;
    }

    public function generate(Customer $customer, string $period): string
    {
        $dataGatherer = new UptimeDataGatherer($customer);
        $data = $dataGatherer->getAggregatedData($period);

        $fileName = "UptimeReport_{$customer->id}_" . now()->format('Ymd') . ".pdf";
        $filePath = storage_path("app/private/reports/{$fileName}");

        $html = view('Reports.UptimeReport.template', [
            'customer' => $customer,
            'data' => $data,
        ])->render();

        $pdf = Pdf::loadHTML($html);

        // Ensure directory exists
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $pdf->save($filePath);

        return $filePath;
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/UptimeReport/template.blade.php');
    }

    public function deliver(Company $company, Customer $customer, string $filePath): void
    {
        // Delivery temporarily disabled
        // $destination = $company->settings['reports']['default_report_destination'] ?? null;

        // if ($destination) {
        //     Mail::to($destination)->send(
        //         new ReportDeliveryMail($company, $customer, $filePath)
        //     );
        // }
    }
}
