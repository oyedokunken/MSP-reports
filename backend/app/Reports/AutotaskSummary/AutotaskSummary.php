<?php

namespace App\Reports\AutotaskSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;

class AutotaskSummary extends BaseReport
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

    /**
     * Override data check to include open tickets outside the date range
     */
    protected static function hasDataInRange(int $customerId, Carbon $startDate, Carbon $endDate): bool
    {
        // Standard check for data in range (tickets, time entries, etc)
        $hasDataInPeriod = parent::hasDataInRange($customerId, $startDate, $endDate);
        
        if ($hasDataInPeriod) {
            return true;
        }

        // Additional check for OPEN tickets that were created BEFORE the end of the period
        // This matches the logic in validate() and gatherTickets()
        $customer = Customer::find($customerId);
        if (!$customer) {
            return false;
        }

        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Autotask')
                      ->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        $endUtc = $endDate->copy()->setTimezone('UTC');

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'ticket')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereRaw("data_payload->>'status' != '5'")
            ->whereRaw("(data_payload->>'created_date')::timestamp <= ?", [$endUtc->toDateTimeString()])
            ->exists();
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has any Autotask integration
        $hasAutotask = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Autotask')
                      ->whereIn('status', ['active', 'importing_historical', 'syncing']);
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
                      ->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        $startUtc = $startDate->copy()->setTimezone('UTC');
        $endUtc = $endDate->copy()->setTimezone('UTC');

        $hasDataInRange = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', ['ticket', 'time_entry'])
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startUtc, $endUtc])
            ->exists();

        if ($hasDataInRange) {
            \Log::info("Autotask Summary validation - has data in range", [
                'customer_id' => $customer->customer_id,
            ]);
            return true;
        }

        $hasOpenTickets = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'ticket')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereRaw("data_payload->>'status' != '5'")
            ->whereRaw("(data_payload->>'created_date')::timestamp <= ?", [$endUtc->toDateTimeString()])
            ->exists();

        \Log::info("Autotask Summary validation", [
            'customer_id' => $customer->customer_id,
            'company_id' => $customer->company_id,
            'integration_customer_ids' => $integrationCustomerIds,
            'start_date_local' => $startDate->toDateTimeString(),
            'end_date_local' => $endDate->toDateTimeString(),
            'start_date_utc' => $startUtc->toDateTimeString(),
            'end_date_utc' => $endUtc->toDateTimeString(),
            'has_data_in_range' => $hasDataInRange,
            'has_open_tickets' => $hasOpenTickets,
        ]);

        return $hasOpenTickets;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get integration customer IDs
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'Autotask')->whereIn('status', ['active', 'importing_historical', 'syncing']);
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

        // Calculate daily trends for line graph
        $dailyTrends = $this->calculateDailyTrends($tickets, $startDate, $endDate);

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
            'daily_trends' => $dailyTrends,
            'total_alerts' => count($alerts),
        ];

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
            'options' => $options, // Pass options to template for dynamic title
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

        // Apply section filtering based on template configuration
        \Log::info('AutotaskSummary: Filtering sections', [
            'included_sections' => $options['included_sections'] ?? 'not set',
            'options_keys' => array_keys($options),
        ]);
        
        $filtered = $this->filterSections($reportData, $options);
        
        \Log::info('AutotaskSummary: After filtering', [
            'sections_to_render' => $filtered['sections_to_render'] ?? 'not set',
        ]);
        
        return $filtered;
    }

    public function getTemplate(): string
    {
        return 'Reports.AutotaskSummary.template';
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'autotask_summary',
            'name' => 'Autotask Summary',
            'description' => 'Comprehensive service report combining tickets, time entries, technician performance, and contracts from Autotask PSA',
            'category' => 'service_desk',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 90,
        ];
    }

    // Data gathering is now handled by AutotaskDataGatherer

    // ==================== Summary Calculation Methods ====================

    /**
     * @param Collection<int, mixed> $tickets
     * @return array<string, mixed>
     */
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
        $priorityBreakdown = $tickets->groupBy(function($item): string {
            return (string)($item['priority'] ?? 'unknown');
        })->map->count()->toArray();
        
        // Top issues
        $topIssues = $tickets->groupBy(function($item): string {
            return (string)($item['title'] ?? 'unknown');
        })
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

    /**
     * @param Collection<int, mixed> $timeEntries
     * @return array<string, mixed>
     */
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

    /**
     * @param Collection<int, mixed> $timeEntries
     * @param Collection<int, mixed> $tickets
     * @param Collection<int, mixed> $resources
     * @return array<int, mixed>
     */
    private function getTechnicianSummary(Collection $timeEntries, Collection $tickets, Collection $resources): array
    {
        $resourceMap = $resources->keyBy('resource_id')->toArray();
        
        // Group time entries by resource
        $technicianData = $timeEntries->groupBy(function($item): string {
            return (string)($item['resource_id'] ?? 'unknown');
        })
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

    /**
     * @param Collection<int, mixed> $contracts
     * @return array<string, mixed>
     */
    private function getContractSummary(Collection $contracts): array
    {
        return [
            'total_contracts' => $contracts->count(),
            'active_contracts' => $contracts->whereIn('status', ['active', 'importing_historical', 'syncing'])->count(),
            'expiring_soon' => $contracts->where('status', 'expiring_soon')->count(),
            'expired_contracts' => $contracts->where('status', 'expired')->count(),
            'total_value' => $contracts->sum('contract_value'),
        ];
    }

    // ==================== Alert Generation ====================

    /**
     * @param Collection<int, mixed> $tickets
     * @param Collection<int, mixed> $contracts
     * @param array<string, mixed> $timeSummary
     * @return array<int, array{type: string, severity: string, title: string, description: string}>
     */
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

    /**
     * @param Collection<int, mixed> $tickets
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array{labels: array<int, string>, created: array<int, int>, completed: array<int, int>}
     */
    private function calculateDailyTrends(Collection $tickets, Carbon $startDate, Carbon $endDate): array
    {
        $days = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('M d');
            $startOfDay = $currentDate->copy()->startOfDay();
            $endOfDay = $currentDate->copy()->endOfDay();
            
            $created = $tickets->filter(function($t) use ($startOfDay, $endOfDay) {
                if ($t['created_date'] === 'Unknown') return false;
                $date = Carbon::parse($t['created_date']);
                return $date->between($startOfDay, $endOfDay);
            })->count();
            
            $completed = $tickets->filter(function($t) use ($startOfDay, $endOfDay) {
                if (!$t['completed_date']) return false;
                $date = Carbon::parse($t['completed_date']);
                return $date->between($startOfDay, $endOfDay);
            })->count();
            
            $days[] = [
                'date' => $dateStr,
                'created' => $created,
                'completed' => $completed,
            ];
            
            $currentDate->addDay();
        }
        
        return [
            'labels' => array_column($days, 'date'),
            'created' => array_column($days, 'created'),
            'completed' => array_column($days, 'completed'),
        ];
    }
}
