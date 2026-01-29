<?php

namespace App\Reports\UptimeReport;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class UptimeReport extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['uptime_metric'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $dataGatherer = new UptimeDataGatherer($customer, $startDate, $endDate);
        $metrics = $dataGatherer->getUptimeMetrics();
        $deviceBreakdown = $dataGatherer->getDeviceBreakdown();

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer, // Pass the full customer object for template
            'customer_data' => [
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
                    'label' => 'Average Uptime',
                    'value' => $this->formatPercentage($metrics['average_uptime']),
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Total Downtime',
                    'value' => round($metrics['total_downtime_minutes'] / 60, 1),
                    'unit' => 'hours',
                ],
                [
                    'label' => 'Worst Day',
                    'value' => $this->formatPercentage($metrics['worst_day_uptime']),
                    'unit' => 'percentage',
                ],
                [
                    'label' => 'Best Day',
                    'value' => $this->formatPercentage($metrics['best_day_uptime']),
                    'unit' => 'percentage',
                ],
            ],
            'data' => [
                'metrics' => $metrics,
                'devices' => $deviceBreakdown,
                'daily_breakdown' => $metrics['daily_breakdown'] ?? [],
            ],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/UptimeReport/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'uptime_monthly',
            'name' => 'Monthly Uptime Report',
            'description' => 'Device uptime metrics and availability tracking across all monitoring systems',
            'category' => 'infrastructure',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
        ];
    }

    private function formatPercentage(float $value): string
    {
        return round($value, 2) . '%';
    }
}
