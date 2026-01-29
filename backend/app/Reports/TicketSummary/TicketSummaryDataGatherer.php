<?php

namespace App\Reports\TicketSummary;

use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TicketSummaryDataGatherer
{
    protected Customer $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * @param array{start: Carbon, end: Carbon}|Carbon $period
     * @return array<string, mixed>
     */
    public function getReportData($period): array
    {
        $startDate = is_array($period) ? $period['start'] : now()->subMonth()->startOfMonth();
        $endDate = is_array($period) ? $period['end'] : now()->subMonth()->endOfMonth();

        // Get all integration customer IDs for this customer
        $integrationCustomerIds = IntegrationCustomer::query()
            ->whereIn('integration_customer_id', function ($query) {
                $query->select('integration_customer_id')
                    ->from('customer_mappings')
                    ->where('customer_id', $this->customer->customer_id);
            })
            ->pluck('integration_customer_id');

        if ($integrationCustomerIds->isEmpty()) {
            Log::warning('No integration customers found for customer', [
                'customer_id' => $this->customer->customer_id,
            ]);
            
            return $this->getEmptyData();
        }

        try {
            // Query tickets from IntegrationData
            $ticketRecords = IntegrationData::query()
                ->whereIn('integration_customer_id', $integrationCustomerIds)
                ->where('data_type', 'ticket')
                ->whereBetween('data_timestamp', [$startDate, $endDate])
                ->get();

            // Query time entries from IntegrationData
            $timeEntryRecords = IntegrationData::query()
                ->whereIn('integration_customer_id', $integrationCustomerIds)
                ->where('data_type', 'time_entry')
                ->whereBetween('data_timestamp', [$startDate, $endDate])
                ->get();

            if ($ticketRecords->isEmpty() && $timeEntryRecords->isEmpty()) {
                Log::info('No ticket or time entry data found for customer', [
                    'customer_id' => $this->customer->customer_id,
                    'period' => [$startDate->toDateString(), $endDate->toDateString()],
                ]);
                
                return $this->getEmptyData();
            }

            // Fetch resources (technicians) from IntegrationData
            $resourceRecords = IntegrationData::query()
                ->where('company_id', $this->customer->company_id)
                ->where('data_type', 'resource')
                ->get();

            $resources = $this->extractResources($resourceRecords);

            // Process the data
            $tickets = $this->extractTickets($ticketRecords);
            $timeEntries = $this->extractTimeEntries($timeEntryRecords);

            // Calculate metrics
            return [
                'tickets' => $this->processTickets($tickets),
                'time_entries' => $this->processTimeEntries($timeEntries),
                'summary' => $this->calculateSummary($tickets, $timeEntries),
                'top_issues' => $this->getTopIssues($tickets),
                'technicians' => $this->getTechnicianActivity($timeEntries, $resources),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to gather ticket data for report', [
                'customer_id' => $this->customer->customer_id,
                'error' => $e->getMessage(),
            ]);
            
            return $this->getEmptyData();
        }
    }

    /**
     * @param \Illuminate\Support\Collection<int, IntegrationData> $records
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function extractTickets($records)
    {
        return $records->map(function ($record) {
            $payload = $record->data_payload;
            return [
                'id' => $payload['ticket_id'] ?? null,
                'number' => $payload['ticket_number'] ?? 'N/A',
                'title' => $payload['title'] ?? 'Untitled',
                'status' => $payload['status'] ?? null,
                'priority' => $payload['priority'] ?? null,
                'createDate' => $payload['created_date'] ?? $record->data_timestamp,
                'completedDate' => $payload['completed_date'] ?? null,
                'assignedResourceID' => $payload['assigned_resource'] ?? null,
            ];
        });
    }

    /**
     * @param \Illuminate\Support\Collection<int, IntegrationData> $records
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function extractTimeEntries($records)
    {
        return $records->map(function ($record) {
            $payload = $record->data_payload;
            return [
                'id' => $payload['time_entry_id'] ?? null,
                'ticketID' => $payload['ticket_id'] ?? null,
                'resourceID' => $payload['resource_id'] ?? null,
                'hoursWorked' => $payload['hours_worked'] ?? 0,
                'hoursToBill' => $payload['hours_to_bill'] ?? 0,
                'isNonBillable' => !($payload['billable'] ?? true),
                'dateWorked' => $payload['date_worked'] ?? $record->data_timestamp,
                'summaryNotes' => $payload['summary_notes'] ?? '',
            ];
        });
    }

    /**
     * @param \Illuminate\Support\Collection<int, IntegrationData> $records
     * @return array<int|string, array{id: int|string, first_name: string, last_name: string, title: string, email: string|null}>
     */
    protected function extractResources($records): array
    {
        $resources = [];
        foreach ($records as $record) {
            $payload = $record->data_payload;
            $resourceId = $payload['resource_id'] ?? null;
            if ($resourceId) {
                $resources[$resourceId] = [
                    'id' => $resourceId,
                    'first_name' => $payload['first_name'] ?? '',
                    'last_name' => $payload['last_name'] ?? '',
                    'title' => $payload['title'] ?? 'Technician',
                    'email' => $payload['email'] ?? null,
                ];
            }
        }
        return $resources;
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $tickets
     * @return array<int, array<string, mixed>>
     */
    protected function processTickets($tickets): array
    {
        return $tickets->map(function ($ticket) {
            return [
                'id' => $ticket['id'],
                'number' => $ticket['number'] ?? 'N/A',
                'title' => $ticket['title'] ?? 'Untitled',
                'status' => $ticket['status'],
                'priority' => $ticket['priority'],
                'created_at' => $ticket['createDate'] ?? null,
                'completed_at' => $ticket['completedDate'] ?? null,
                'assigned_resource_id' => $ticket['assignedResourceID'] ?? null,
            ];
        })->toArray();
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $timeEntries
     * @return array<int, array<string, mixed>>
     */
    protected function processTimeEntries($timeEntries): array
    {
        return $timeEntries->map(function ($entry) {
            return [
                'id' => $entry['id'],
                'ticket_id' => $entry['ticketID'] ?? null,
                'hours_worked' => $entry['hoursWorked'] ?? 0,
                'hours_to_bill' => $entry['hoursToBill'] ?? 0,
                'is_billable' => !($entry['isNonBillable'] ?? false),
                'date_worked' => $entry['dateWorked'] ?? null,
                'resource_id' => $entry['resourceID'] ?? null,
                'resource_name' => 'Resource ' . ($entry['resourceID'] ?? 'Unknown'),
                'summary' => $entry['summaryNotes'] ?? '',
            ];
        })->toArray();
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $tickets
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $timeEntries
     * @return array<string, mixed>
     */
    protected function calculateSummary($tickets, $timeEntries): array
    {
        $totalTickets = $tickets->count();
        $completedTickets = $tickets->where('status', 5)->count(); // Status 5 = Complete
        $openTickets = $totalTickets - $completedTickets;

        $totalHours = $timeEntries->sum('hoursWorked');
        $billableHours = $timeEntries->where('isNonBillable', false)->sum('hoursToBill');
        $nonBillableHours = $timeEntries->where('isNonBillable', true)->sum('hoursWorked');

        // Calculate average resolution time
        $resolvedTickets = $tickets->filter(function ($ticket) {
            return !empty($ticket['completedDate']) && !empty($ticket['createDate']);
        });

        $avgResolutionHours = 0;
        if ($resolvedTickets->count() > 0) {
            $totalResolutionTime = $resolvedTickets->sum(function ($ticket) {
                $created = Carbon::parse($ticket['createDate']);
                $completed = Carbon::parse($ticket['completedDate']);
                return $created->diffInHours($completed);
            });
            $avgResolutionHours = round($totalResolutionTime / $resolvedTickets->count(), 1);
        }

        return [
            'total_tickets' => $totalTickets,
            'completed_tickets' => $completedTickets,
            'open_tickets' => $openTickets,
            'total_hours' => round($totalHours, 1),
            'billable_hours' => round($billableHours, 1),
            'non_billable_hours' => round($nonBillableHours, 1),
            'billing_efficiency' => $totalHours > 0 ? round(($billableHours / $totalHours) * 100, 1) : 0,
            'avg_resolution_hours' => $avgResolutionHours,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $tickets
     * @return array<int, array{title: string, count: int}>
     */
    protected function getTopIssues($tickets): array
    {
        return $tickets
            ->groupBy(function($item): string {
                return (string)($item['title'] ?? 'unknown');
            })
            ->map(function ($group) {
                return [
                    'title' => (string)($group->first()['title'] ?? 'Unknown'),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $timeEntries
     * @param array<int|string, array<string, mixed>> $resources
     * @return array<int, array{resource_id: mixed, name: string, title: string, total_hours: float, billable_hours: float}>
     */
    protected function getTechnicianActivity($timeEntries, $resources): array
    {
        return $timeEntries
            ->groupBy(function($item): string {
                return (string)($item['resourceID'] ?? 'unknown');
            })
            ->map(function ($group) use ($resources) {
                $resourceId = $group->first()['resourceID'] ?? null;
                $resource = $resources[$resourceId] ?? null;
                
                $name = 'Resource ' . ($resourceId ?? 'Unknown');
                $title = 'Technician';
                
                if ($resource) {
                    $name = trim(((string)($resource['first_name'] ?? '')) . ' ' . ((string)($resource['last_name'] ?? '')));
                    if (empty($name)) {
                        $name = 'Resource ' . $resourceId;
                    }
                    $title = (string)($resource['title'] ?? 'Technician');
                }
                
                return [
                    'resource_id' => $resourceId,
                    'name' => $name,
                    'title' => $title,
                    'total_hours' => (float)round($group->sum('hoursWorked'), 1),
                    'billable_hours' => (float)round($group->where('isNonBillable', false)->sum('hoursToBill'), 1),
                ];
            })
            ->sortByDesc('total_hours')
            ->values()
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyData(): array
    {
        return [
            'tickets' => [],
            'time_entries' => [],
            'summary' => [
                'total_tickets' => 0,
                'completed_tickets' => 0,
                'open_tickets' => 0,
                'total_hours' => 0,
                'billable_hours' => 0,
                'non_billable_hours' => 0,
                'billing_efficiency' => 0,
                'avg_resolution_hours' => 0,
            ],
            'top_issues' => [],
            'technicians' => [],
        ];
    }
}
