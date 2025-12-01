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
        // HARDCODED FOR TESTING - Always include all sections
        $includeSections = ['uptime', 'tickets', 'backups', 'security'];
        \Log::info('Comprehensive report generating with sections: ' . json_encode($includeSections));
        
        $reportData = [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer,
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
            'included_reports' => [],
        ];
        
        // Generate each individual report and include its full data
        $registry = app(\App\Services\ReportRegistry::class);
        
        // Include Uptime Report if requested
        if (in_array('uptime', $includeSections)) {
            try {
                $uptimeReport = $registry->get('uptime_monthly', $customer->company_id);
                if ($uptimeReport) {
                    $uptimeData = $uptimeReport->generate($customer, $startDate, $endDate);
                    $reportData['included_reports'][] = [
                        'type' => 'uptime',
                        'title' => 'Uptime Report',
                        'data' => $uptimeData,
                        'template' => 'Reports/UptimeReport/template',
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('Failed to include uptime report: ' . $e->getMessage());
            }
        }
        
        // Include Ticket Summary if requested
        if (in_array('tickets', $includeSections)) {
            try {
                $ticketReport = $registry->get('ticket_summary', $customer->company_id);
                if ($ticketReport) {
                    $ticketData = $ticketReport->generate($customer, $startDate, $endDate);
                    $reportData['included_reports'][] = [
                        'type' => 'tickets',
                        'title' => 'Ticket Summary',
                        'data' => $ticketData,
                        'template' => 'Reports/TicketSummary/template',
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('Failed to include ticket report: ' . $e->getMessage());
            }
        }
        
        // Include Backup Status if requested
        if (in_array('backups', $includeSections)) {
            try {
                $backupReport = $registry->get('backup_status', $customer->company_id);
                if ($backupReport) {
                    $backupData = $backupReport->generate($customer, $startDate, $endDate);
                    $reportData['included_reports'][] = [
                        'type' => 'backups',
                        'title' => 'Backup Status',
                        'data' => $backupData,
                        'template' => 'Reports/BackupStatus/template',
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('Failed to include backup report: ' . $e->getMessage());
            }
        }
        
        // Include Security Summary if requested
        if (in_array('security', $includeSections)) {
            try {
                $securityReport = $registry->get('security_summary', $customer->company_id);
                if ($securityReport) {
                    $securityData = $securityReport->generate($customer, $startDate, $endDate);
                    $reportData['included_reports'][] = [
                        'type' => 'security',
                        'title' => 'Security Summary',
                        'data' => $securityData,
                        'template' => 'Reports/SecuritySummary/template',
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('Failed to include security report: ' . $e->getMessage());
            }
        }
        
        \Log::info('Comprehensive report generated with ' . count($reportData['included_reports']) . ' reports');
        foreach ($reportData['included_reports'] as $report) {
            \Log::info('  - ' . $report['title'] . ' (template: ' . $report['template'] . ')');
        }
        
        return $reportData;
    }
    
    private function calculateHealthScore(array $data): int
    {
        $weights = [];
        
        // Uptime score (30% weight)
        if (!empty($data['uptime'])) {
            $uptimePercent = floatval($data['uptime']['average_uptime'] ?? 0);
            $scores[] = $uptimePercent;
            $weights[] = 30;
        }
        
        // Backup score (25% weight)
        if (!empty($data['backups'])) {
            $backupRate = floatval($data['backups']['success_rate'] ?? 0);
            $scores[] = $backupRate;
            $weights[] = 25;
        }
        
        // Ticket score (25% weight) - based on closure rate
        if (!empty($data['tickets'])) {
            $ticketsOpened = $data['tickets']['tickets_opened'] ?? 1;
            $ticketsClosed = $data['tickets']['tickets_closed'] ?? 0;
            $ticketRate = $ticketsOpened > 0 ? ($ticketsClosed / $ticketsOpened) * 100 : 100;
            $scores[] = $ticketRate;
            $weights[] = 25;
        }
        
        // Security score (20% weight) - inverse of critical alerts
        if (!empty($data['security'])) {
            $criticalAlerts = $data['security']['critical_alerts'] ?? 0;
            $securityScore = max(0, 100 - ($criticalAlerts * 10)); // Each critical alert reduces score by 10
            $scores[] = $securityScore;
            $weights[] = 20;
        }
        
        // Calculate weighted average
        if (empty($scores)) {
            return 0;
        }
        
        $totalWeight = array_sum($weights);
        $weightedSum = 0;
        
        for ($i = 0; $i < count($scores); $i++) {
            $weightedSum += $scores[$i] * $weights[$i];
        }
        
        return (int) round($weightedSum / $totalWeight);
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
