<?php

namespace App\Reports\AutotaskMonthlySummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;

class AutotaskMonthlySummary extends BaseReport
{
    protected AutotaskDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new AutotaskDataGatherer();
    }
    public function getRequiredDataTypes(): array
    {
        return ['ticket', 'time_entry', 'resource', 'contract'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 30; // Need at least 30 days for monthly report
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has any Autotask integration
        $hasAutotask = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Autotask')
                      ->where('status', 'active');
            })
            ->exists();

        if (!$hasAutotask) {
            \Log::info("Autotask Monthly Summary validation - No Autotask integration", [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        // Check if there's any Autotask data (tickets or time entries)
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Autotask')
                      ->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', ['ticket', 'time_entry'])
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->exists();

        \Log::info("Autotask Monthly Summary validation", [
            'customer_id' => $customer->customer_id,
            'integration_customer_ids' => $integrationCustomerIds,
            'has_data' => $hasData,
        ]);

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get integration customer IDs
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Autotask')->where('status', 'active');
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Gather all data sections using DataGatherer
        $tickets = $this->dataGatherer->gatherTickets($customer, $integrationCustomerIds, $startDate, $endDate);
        $timeEntries = $this->dataGatherer->gatherTimeEntries($customer, $integrationCustomerIds, $startDate, $endDate);
        $resources = $this->dataGatherer->gatherResources($customer);
        $contracts = $this->dataGatherer->gatherContracts($customer, $integrationCustomerIds);

        // Calculate summaries
        $ticketSummary = $this->getTicketSummary($tickets);
        $timeSummary = $this->getTimeSummary($timeEntries);
        $technicianSummary = $this->getTechnicianSummary($timeEntries, $tickets, $resources);
        $contractSummary = $this->getContractSummary($contracts);

        // Generate alerts
        $alerts = $this->generateAlerts($tickets, $contracts, $timeSummary);

        // Create executive summary
        $executiveSummary = [
            'tickets' => [
                'total' => $ticketSummary['total_tickets'] ?? 0,
                'completed' => $ticketSummary['completed_tickets'] ?? 0,
                'open' => $ticketSummary['open_tickets'] ?? 0,
            ],
            'time' => [
                'total_hours' => $timeSummary['total_hours'] ?? 0,
                'billable_hours' => $timeSummary['billable_hours'] ?? 0,
                'billing_efficiency' => $timeSummary['billing_efficiency'] ?? 0,
            ],
            'contracts' => [
                'total' => $contractSummary['total_contracts'] ?? 0,
                'expiring_soon' => $contractSummary['expiring_soon'] ?? 0,
            ],
            'total_alerts' => count($alerts),
        ];

        return [
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
            'executive_summary' => $executiveSummary,
            'tickets' => [
                'data' => $tickets->toArray(),
                'summary' => $ticketSummary,
            ],
            'time_entries' => [
                'data' => $timeEntries->toArray(),
                'summary' => $timeSummary,
            ],
            'technicians' => [
                'data' => $technicianSummary,
            ],
            'contracts' => [
                'data' => $contracts->toArray(),
                'summary' => $contractSummary,
            ],
            'alerts' => $alerts,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/AutotaskMonthlySummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'autotask_monthly_summary',
            'name' => 'Autotask Monthly Summary',
            'description' => 'Comprehensive monthly report combining tickets, time entries, technician performance, and contracts from Autotask PSA',
            'category' => 'service_desk',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 90,
        ];
    }

    // Data gathering is now handled by AutotaskDataGatherer

    // ==================== Summary Calculation Methods ====================

    private function getTicketSummary(Collection $tickets): array
    {
        $totalTickets = $tickets->count();
        $completedTickets = $tickets->where('completed_date', '!=', null)->count();
        $openTickets = $totalTickets - $completedTickets;
        
        // Calculate average resolution time
        $resolvedTickets = $tickets->filter(fn($t) => $t['resolution_hours'] !== null);
        $avgResolutionHours = $resolvedTickets->count() > 0 
            ? round($resolvedTickets->avg('resolution_hours'), 1) 
            : 0;
        
        // Priority breakdown
        $priorityBreakdown = $tickets->groupBy('priority')->map->count()->toArray();
        
        // Top issues
        $topIssues = $tickets->groupBy('title')
            ->map(function($group) {
                return [
                    'title' => $group->first()['title'],
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();

        return [
            'total_tickets' => $totalTickets,
            'completed_tickets' => $completedTickets,
            'open_tickets' => $openTickets,
            'avg_resolution_hours' => $avgResolutionHours,
            'priority_breakdown' => $priorityBreakdown,
            'top_issues' => $topIssues,
        ];
    }

    private function getTimeSummary(Collection $timeEntries): array
    {
        $totalHours = $timeEntries->sum('hours_worked');
        $billableHours = $timeEntries->where('billable', true)->sum('hours_to_bill');
        $nonBillableHours = $timeEntries->where('billable', false)->sum('hours_worked');
        
        $billingEfficiency = $totalHours > 0 
            ? round(($billableHours / $totalHours) * 100, 1) 
            : 0;

        return [
            'total_hours' => round($totalHours, 2),
            'billable_hours' => round($billableHours, 2),
            'non_billable_hours' => round($nonBillableHours, 2),
            'billing_efficiency' => $billingEfficiency,
        ];
    }

    private function getTechnicianSummary(Collection $timeEntries, Collection $tickets, Collection $resources): array
    {
        $resourceMap = $resources->keyBy('resource_id')->toArray();
        
        // Group time entries by resource
        $technicianData = $timeEntries->groupBy('resource_id')
            ->map(function($entries, $resourceId) use ($resourceMap, $tickets) {
                $resource = $resourceMap[$resourceId] ?? null;
                $name = $resource ? $resource['name'] : "Resource {$resourceId}";
                $title = $resource ? $resource['title'] : 'Technician';
                
                $totalHours = $entries->sum('hours_worked');
                $billableHours = $entries->where('billable', true)->sum('hours_to_bill');
                
                // Count tickets assigned to this resource
                $ticketsResolved = $tickets->where('assigned_resource', $resourceId)
                    ->where('completed_date', '!=', null)
                    ->count();
                
                return [
                    'resource_id' => $resourceId,
                    'name' => $name,
                    'title' => $title,
                    'total_hours' => round($totalHours, 1),
                    'billable_hours' => round($billableHours, 1),
                    'tickets_resolved' => $ticketsResolved,
                    'billing_efficiency' => $totalHours > 0 ? round(($billableHours / $totalHours) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('total_hours')
            ->values()
            ->toArray();

        return $technicianData;
    }

    private function getContractSummary(Collection $contracts): array
    {
        return [
            'total_contracts' => $contracts->count(),
            'active_contracts' => $contracts->where('status', 'active')->count(),
            'expiring_soon' => $contracts->where('status', 'expiring_soon')->count(),
            'expired_contracts' => $contracts->where('status', 'expired')->count(),
            'total_value' => $contracts->sum('contract_value'),
        ];
    }

    // ==================== Alert Generation ====================

    private function generateAlerts(Collection $tickets, Collection $contracts, array $timeSummary): array
    {
        $alerts = [];
        
        // Alert: High number of open tickets
        $openTickets = $tickets->where('completed_date', null)->count();
        if ($openTickets > 10) {
            $alerts[] = [
                'type' => 'warning',
                'severity' => 'warning',
                'title' => 'High Number of Open Tickets',
                'description' => "{$openTickets} tickets are currently open and require attention.",
            ];
        }
        
        // Alert: Low billing efficiency
        if ($timeSummary['billing_efficiency'] < 70) {
            $alerts[] = [
                'type' => 'warning',
                'severity' => 'warning',
                'title' => 'Low Billing Efficiency',
                'description' => "Billing efficiency is {$timeSummary['billing_efficiency']}%. Consider reviewing non-billable time allocations.",
            ];
        }
        
        // Alert: Contracts expiring soon
        $expiringContracts = $contracts->where('status', 'expiring_soon');
        if ($expiringContracts->count() > 0) {
            $alerts[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'title' => 'Contracts Expiring Soon',
                'description' => "{$expiringContracts->count()} contract(s) will expire within 30 days. Renewal action required.",
            ];
        }
        
        // Alert: Expired contracts
        $expiredContracts = $contracts->where('status', 'expired');
        if ($expiredContracts->count() > 0) {
            $alerts[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'title' => 'Expired Contracts',
                'description' => "{$expiredContracts->count()} contract(s) have expired and require immediate attention.",
            ];
        }
        
        return $alerts;
    }
}
