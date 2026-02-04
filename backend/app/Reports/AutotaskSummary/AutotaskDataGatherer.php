<?php

declare(strict_types=1);

namespace App\Reports\AutotaskSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AutotaskDataGatherer
{
    /**
     * Gather ticket data for a customer within a date range
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherTickets(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $startUtc = $startDate->copy()->setTimezone('UTC');
        $endUtc = $endDate->copy()->setTimezone('UTC');

        $ticketsInRange = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'ticket')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startUtc, $endUtc])
            ->orderBy('data_timestamp', 'desc')
            ->get();

        $openTickets = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'ticket')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->where('data_payload->status', '!=', '5')
            ->where('data_payload->created_date', '<=', $endUtc->toDateTimeString())
            ->orderBy('data_timestamp', 'desc')
            ->get();

        $rawData = $ticketsInRange->merge($openTickets);

        return $rawData->unique(function($item) {
            return $item->data_payload['ticket_id'] ?? null;
        })->map(function($item) use ($startDate, $endDate) {
            $payload = $item->data_payload;
            $createdDate = isset($payload['created_date']) ? Carbon::parse($payload['created_date']) : null;
            $completedDate = isset($payload['completed_date']) ? Carbon::parse($payload['completed_date']) : null;
            $dueDate = isset($payload['due_date_time']) ? Carbon::parse($payload['due_date_time']) : null;
            $resolvedDate = isset($payload['resolved_date_time']) ? Carbon::parse($payload['resolved_date_time']) : null;
            $lastActivityDate = isset($payload['last_activity_date']) ? Carbon::parse($payload['last_activity_date']) : null;
            
            $status = $payload['status'] ?? null;
            $isOpen = $status != 5;
            
            $isCreated = $createdDate && $createdDate->between($startDate, $endDate);
            $isCompleted = $completedDate && $completedDate->between($startDate, $endDate);
            
            if (!$isCreated && !$isCompleted && !$isOpen) {
                return null;
            }
            
            $resolutionHours = null;
            if ($createdDate && $completedDate) {
                $resolutionHours = $createdDate->diffInHours($completedDate);
            }
            
            return [
                'ticket_id' => $payload['ticket_id'] ?? null,
                'ticket_number' => $payload['ticket_number'] ?? 'N/A',
                'title' => $payload['title'] ?? 'Untitled',
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? null,
                'status_label' => $this->getStatusLabel($payload['status'] ?? null),
                'priority' => $payload['priority'] ?? null,
                'priority_label' => $this->getPriorityLabel($payload['priority'] ?? null),
                'ticket_type' => $payload['ticket_type'] ?? null,
                'ticket_category' => $payload['ticket_category'] ?? null,
                'issue_type' => $payload['issue_type'] ?? null,
                'sub_issue_type' => $payload['sub_issue_type'] ?? null,
                'source' => $payload['source'] ?? null,
                'queue_id' => $payload['queue_id'] ?? null,
                'contract_id' => $payload['contract_id'] ?? null,
                'billing_code_id' => $payload['billing_code_id'] ?? null,
                'created_date' => $createdDate ? $createdDate->format('M d, Y g:i A') : 'Unknown',
                'completed_date' => $completedDate ? $completedDate->format('M d, Y g:i A') : null,
                'due_date' => $dueDate ? $dueDate->format('M d, Y g:i A') : null,
                'resolved_date' => $resolvedDate ? $resolvedDate->format('M d, Y g:i A') : null,
                'last_activity_date' => $lastActivityDate ? $lastActivityDate->format('M d, Y g:i A') : null,
                'resolution_hours' => $resolutionHours,
                'assigned_resource' => $payload['assigned_resource'] ?? null,
                'assigned_resource_role_id' => $payload['assigned_resource_role_id'] ?? null,
                'creator_resource_id' => $payload['creator_resource_id'] ?? null,
                'completed_by_resource_id' => $payload['completed_by_resource_id'] ?? null,
                'estimated_hours' => $payload['estimated_hours'] ?? 0,
                'hours_to_be_scheduled' => $payload['hours_to_be_scheduled'] ?? 0,
                'purchase_order_number' => $payload['purchase_order_number'] ?? null,
                'resolution' => $payload['resolution'] ?? null,
            ];
        })->filter()->values();
    }

    /**
     * Gather time entry data for a customer within a date range
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherTimeEntries(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $startUtc = $startDate->copy()->setTimezone('UTC');
        $endUtc = $endDate->copy()->setTimezone('UTC');

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'time_entry')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startUtc, $endUtc])
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['time_entry_id'] ?? null;
        })->map(function($item) {
            $payload = $item->data_payload;
            $dateWorked = isset($payload['date_worked']) ? Carbon::parse($payload['date_worked']) : null;
            $startDateTime = isset($payload['start_date_time']) ? Carbon::parse($payload['start_date_time']) : null;
            $endDateTime = isset($payload['end_date_time']) ? Carbon::parse($payload['end_date_time']) : null;
            $createDateTime = isset($payload['create_date_time']) ? Carbon::parse($payload['create_date_time']) : null;
            
            return [
                'time_entry_id' => $payload['time_entry_id'] ?? null,
                'ticket_id' => $payload['ticket_id'] ?? null,
                'resource_id' => $payload['resource_id'] ?? null,
                'role_id' => $payload['role_id'] ?? null,
                'date_worked' => $dateWorked ? $dateWorked->format('M d, Y') : 'Unknown',
                'start_date_time' => $startDateTime ? $startDateTime->format('M d, Y H:i') : null,
                'end_date_time' => $endDateTime ? $endDateTime->format('M d, Y H:i') : null,
                'hours_worked' => $payload['hours_worked'] ?? 0,
                'hours_to_bill' => $payload['hours_to_bill'] ?? 0,
                'offset_hours' => $payload['offset_hours'] ?? 0,
                'billable' => !($payload['is_non_billable'] ?? false),
                'is_non_billable' => $payload['is_non_billable'] ?? false,
                'billing_code_id' => $payload['billing_code_id'] ?? null,
                'internal_billing_code_id' => $payload['internal_billing_code_id'] ?? null,
                'summary_notes' => $payload['summary_notes'] ?? '',
                'internal_notes' => $payload['internal_notes'] ?? null,
                'show_on_invoice' => $payload['show_on_invoice'] ?? true,
                'time_entry_type' => $payload['time_entry_type'] ?? null,
                'contract_id' => $payload['contract_id'] ?? null,
                'contract_service_id' => $payload['contract_service_id'] ?? null,
                'contract_service_bundle_id' => $payload['contract_service_bundle_id'] ?? null,
                'create_date_time' => $createDateTime ? $createDateTime->format('M d, Y H:i') : null,
                'creator_user_id' => $payload['creator_user_id'] ?? null,
                'last_modified_user_id' => $payload['last_modified_user_id'] ?? null,
            ];
        })->filter(function($item) use ($startDate, $endDate) {
            $dateWorked = $item['date_worked'] !== 'Unknown' 
                ? Carbon::parse($item['date_worked']) 
                : null;
            
            return $dateWorked && $dateWorked->between($startDate, $endDate);
        })->values();
    }

    /**
     * Gather resource (technician) data for a company
     * 
     * @param Customer $customer
     * @return Collection<int, mixed>
     */
    public function gatherResources(Customer $customer): Collection
    {
        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'resource')
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['resource_id'] ?? null;
        })->map(function($item) {
            $payload = $item->data_payload;
            $hireDate = isset($payload['hire_date']) ? Carbon::parse($payload['hire_date']) : null;
            
            return [
                'resource_id' => $payload['resource_id'] ?? null,
                'first_name' => $payload['first_name'] ?? '',
                'last_name' => $payload['last_name'] ?? '',
                'middle_name' => $payload['middle_name'] ?? '',
                'name' => trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? '')),
                'user_name' => $payload['user_name'] ?? null,
                'title' => $payload['title'] ?? 'Technician',
                'email' => $payload['email'] ?? null,
                'email2' => $payload['email2'] ?? null,
                'email3' => $payload['email3'] ?? null,
                'email_type_code' => $payload['email_type_code'] ?? null,
                'office_phone' => $payload['office_phone'] ?? null,
                'mobile_phone' => $payload['mobile_phone'] ?? null,
                'home_phone' => $payload['home_phone'] ?? null,
                'office_extension' => $payload['office_extension'] ?? null,
                'is_active' => $payload['is_active'] ?? false,
                'resource_type' => $payload['resource_type'] ?? 'Employee',
                'user_type' => $payload['user_type'] ?? null,
                'license_type' => $payload['license_type'] ?? null,
                'location_id' => $payload['location_id'] ?? null,
                'hire_date' => $hireDate ? $hireDate->format('M d, Y') : null,
                'internal_cost' => $payload['internal_cost'] ?? 0,
                'payroll_type' => $payload['payroll_type'] ?? null,
                'payroll_identifier' => $payload['payroll_identifier'] ?? null,
                'default_service_desk_role_id' => $payload['default_service_desk_role_id'] ?? null,
                'gender' => $payload['gender'] ?? null,
                'initials' => $payload['initials'] ?? null,
                'accounting_reference_id' => $payload['accounting_reference_id'] ?? null,
            ];
        })->values();
    }

    /**
     * Gather contract data for a customer
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherContracts(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect([]);
        }

        $rawData = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'contract')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get();

        return $rawData->unique(function($item) {
            return $item->data_payload['contract_id'] ?? null;
        })->map(function($item) {
            $payload = $item->data_payload;
            $startDate = isset($payload['start_date']) ? Carbon::parse($payload['start_date']) : null;
            $endDate = isset($payload['end_date']) ? Carbon::parse($payload['end_date']) : null;
            $lastModifiedDate = isset($payload['last_modified_date_time']) ? Carbon::parse($payload['last_modified_date_time']) : null;
            $daysUntilExpiry = $endDate ? now()->diffInDays($endDate, false) : null;
            
            $status = 'active';
            if ($daysUntilExpiry !== null) {
                if ($daysUntilExpiry <= 0) $status = 'expired';
                elseif ($daysUntilExpiry <= 30) $status = 'expiring_soon';
            }
            
            return [
                'contract_id' => $payload['contract_id'] ?? null,
                'contract_name' => $payload['contract_name'] ?? 'Unknown Contract',
                'contract_number' => $payload['contract_number'] ?? null,
                'contract_type' => $payload['contract_type'] ?? null,
                'contract_category' => $payload['contract_category'] ?? null,
                'contract_period_type' => $payload['contract_period_type'] ?? null,
                'description' => $payload['description'] ?? null,
                'start_date' => $startDate ? $startDate->format('M d, Y') : 'Unknown',
                'end_date' => $endDate ? $endDate->format('M d, Y') : 'Unknown',
                'days_until_expiry' => $daysUntilExpiry,
                'contract_value' => $payload['contract_value'] ?? 0,
                'estimated_cost' => $payload['estimated_cost'] ?? 0,
                'estimated_hours' => $payload['estimated_hours'] ?? 0,
                'estimated_revenue' => $payload['estimated_revenue'] ?? 0,
                'setup_fee' => $payload['setup_fee'] ?? 0,
                'overage_billing_rate' => $payload['overage_billing_rate'] ?? null,
                'billing_preference' => $payload['billing_preference'] ?? null,
                'is_default_contract' => $payload['is_default_contract'] ?? false,
                'is_compliant' => $payload['is_compliant'] ?? true,
                'service_level_agreement_id' => $payload['service_level_agreement_id'] ?? null,
                'purchase_order_number' => $payload['purchase_order_number'] ?? null,
                'opportunity_id' => $payload['opportunity_id'] ?? null,
                'renewed_contract_id' => $payload['renewed_contract_id'] ?? null,
                'last_modified_date_time' => $lastModifiedDate ? $lastModifiedDate->format('M d, Y H:i') : null,
                'status' => $status,
                'contract_status' => $payload['status'] ?? null,
            ];
        })->sortBy('days_until_expiry')->values();
    }

    /**
     * Get human-readable status label from status code
     */
    private function getStatusLabel(mixed $status): string
    {
        $statusInt = $status !== null ? (int)$status : null;
        $statusLabels = [
            1 => 'New',
            2 => 'In Progress',
            5 => 'Complete',
            8 => 'Waiting Customer',
            9 => 'Waiting Vendor',
        ];
        
        return $statusLabels[$statusInt] ?? 'Unknown';
    }

    /**
     * Get human-readable priority label from priority code
     */
    private function getPriorityLabel(mixed $priority): string
    {
        $priorityInt = $priority !== null ? (int)$priority : null;
        $priorityLabels = [
            1 => 'Critical',
            2 => 'High',
            3 => 'Medium',
            4 => 'Low',
        ];
        
        return $priorityLabels[$priorityInt] ?? 'Unknown';
    }
}
