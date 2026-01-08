<?php

namespace App\Reports\MonthlyComprehensive;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class MonthlyComprehensive extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        // Dynamic - depends on which integrations the customer has
        // Returns empty array since we'll check data availability per integration
        return [];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30;
    }

    /**
     * Override check to return true if ANY of the underlying integration reports have data
     */
    protected static function hasDataInRange(int $customerId, Carbon $startDate, Carbon $endDate): bool
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return false;
        }

        $registry = app(\App\Services\ReportRegistry::class);
        
        // Get all customer's integrations
        $customerIntegrations = $customer->integrationCustomers()
            ->with('integration')
            ->get()
            ->pluck('integration')
            ->unique('integration_id')
            ->filter();
            
        $integrationReportMap = [
            'Autotask' => 'autotask_summary',
            'Datto' => 'datto_rmm_summary',
            'DattoRMM' => 'datto_rmm_summary',
            'BitDefender' => 'bitdefender_summary',
            'ITGlue' => 'itglue_summary',
            'SaaS' => 'saas_summary',
            'Sendmarc' => 'sendmarc_summary',
        ];
        
        foreach ($customerIntegrations as $integration) {
            $integrationType = $integration->integration_type;
            
            if (isset($integrationReportMap[$integrationType])) {
                $reportId = $integrationReportMap[$integrationType];
                
                try {
                    $report = $registry->get($reportId, $customer->company_id);
                    
                    if ($report && $report->validate($customer, $startDate, $endDate)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        return false;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
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
            'included_reports' => [],
        ];
        
        $registry = app(\App\Services\ReportRegistry::class);
        
        // Get all customer's integrations
        $customerIntegrations = $customer->integrationCustomers()
            ->with('integration')
            ->get()
            ->pluck('integration')
            ->unique('integration_id')
            ->filter();
        

        

        $integrationReportMap = [
            'Autotask' => 'autotask_summary',
            'Datto' => 'datto_rmm_summary',
            'DattoRMM' => 'datto_rmm_summary',
            'BitDefender' => 'bitdefender_summary',
            'ITGlue' => 'itglue_summary',
            'SaaS' => 'saas_summary',
            'Sendmarc' => 'sendmarc_summary',
        ];
        
        foreach ($customerIntegrations as $integration) {
            $integrationType = $integration->integration_type;
            
            // Check if we have a monthly summary report for this integration
            if (isset($integrationReportMap[$integrationType])) {
                $reportId = $integrationReportMap[$integrationType];
                
                try {
                    $report = $registry->get($reportId, $customer->company_id);
                    
                    if ($report && $report->validate($customer, $startDate, $endDate)) {
                        $config = $registry->getConfig($reportId);
                        $reportInfo = [
                            'id' => $reportId,
                            'type' => strtolower($integrationType),
                            'title' => $config['name'] ?? ucfirst($integrationType) . ' Monthly Summary',
                        ];
                        
                        $reportData['included_reports'][] = $this->generateSubReport(
                            $report,
                            $reportInfo,
                            $customer,
                            $startDate,
                            $endDate
                        );
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to include integration monthly summary: ' . $e->getMessage(), [
                        'integration_type' => $integrationType,
                        'report_id' => $reportId,
                        'customer_id' => $customer->customer_id,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
        
        return $reportData;
    }
    
    /**
     * Generate a sub-report and return its data structure
     */
    private function generateSubReport($report, array $reportInfo, Customer $customer, Carbon $startDate, Carbon $endDate): array
    {
        $reportData = $report->generate($customer, $startDate, $endDate);
        
        // Determine template path from report ID
        $reportId = $reportInfo['id'];
        $templateMap = [
            'uptime_monthly' => 'Reports/UptimeReport/template',
            'backup_status' => 'Reports/BackupStatus/template',
            'security_summary' => 'Reports/SecuritySummary/template',
            'autotask_summary' => 'Reports/AutotaskSummary/template',
            'datto_rmm_summary' => 'Reports/DattoRMMSummary/template',
            'bitdefender_summary' => 'Reports/BitDefenderSummary/template',
            'itglue_summary' => 'Reports/ITGlueSummary/template',
            'saas_summary' => 'Reports/SaaSSummary/template',
            'sendmarc_summary' => 'Reports/SendmarcSummary/template',
        ];
        
        $template = $templateMap[$reportId] ?? 'Reports/' . ucfirst($reportInfo['type']) . '/template';
        
        return [
            'type' => $reportInfo['type'],
            'title' => $reportInfo['title'],
            'data' => $reportData,
            'template' => $template,
        ];
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
            'description' => 'Dynamic comprehensive report that includes all available monthly summaries from customer\'s integrations',
            'category' => 'executive',
            'data_types' => $this->getRequiredDataTypes(),

            'formats' => ['pdf', 'html'],
        ];
    }
}
