<?php

namespace App\Integrations\Autotask;

class AutotaskMapper
{
    public static function mapCompany(array $autotaskCompany): array
    {
        return [
            'external_id' => (string) $autotaskCompany['id'],
            'name' => $autotaskCompany['companyName'] ?? 'Unknown',
            'active' => ($autotaskCompany['isActive'] ?? true) === true,
            'metadata' => [
                'company_type' => $autotaskCompany['companyType'] ?? null,
                'phone' => $autotaskCompany['phone'] ?? null,
                'address' => $autotaskCompany['address1'] ?? null,
                'city' => $autotaskCompany['city'] ?? null,
                'state' => $autotaskCompany['state'] ?? null,
                'postal_code' => $autotaskCompany['postalCode'] ?? null,
                'country' => $autotaskCompany['countryID'] ?? null,
                'website' => $autotaskCompany['webAddress'] ?? null,
                'account_number' => $autotaskCompany['companyNumber'] ?? null,
            ],
        ];
    }

    public static function mapTicket(array $autotaskTicket): array
    {
        return [
            'integration_customer_id' => null, 
            'data_type' => 'ticket',
            'timestamp' => $autotaskTicket['createDate'] ?? now(),
            'payload' => [
                'ticket_id' => $autotaskTicket['id'],
                'ticket_number' => $autotaskTicket['ticketNumber'] ?? null,
                'title' => $autotaskTicket['title'] ?? 'Untitled',
                'description' => $autotaskTicket['description'] ?? null,
                'status' => $autotaskTicket['status'] ?? null,
                'priority' => $autotaskTicket['priority'] ?? null,
                'queue' => $autotaskTicket['queueID'] ?? null,
                'assigned_resource' => $autotaskTicket['assignedResourceID'] ?? null,
                'company_id' => $autotaskTicket['companyID'] ?? null,
                'created_date' => $autotaskTicket['createDate'] ?? null,
                'completed_date' => $autotaskTicket['completedDate'] ?? null,
                'estimated_hours' => $autotaskTicket['estimatedHours'] ?? 0,
                'hours_worked' => $autotaskTicket['hoursToBeScheduled'] ?? 0,
            ],
        ];
    }

    public static function mapTimeEntry(array $autotaskTimeEntry): array
    {
        return [
            'integration_customer_id' => null, // Will be set by caller
            'data_type' => 'time_entry',
            'timestamp' => $autotaskTimeEntry['dateWorked'] ?? now(),
            'payload' => [
                'time_entry_id' => $autotaskTimeEntry['id'],
                'ticket_id' => $autotaskTimeEntry['ticketID'] ?? null,
                'resource_id' => $autotaskTimeEntry['resourceID'] ?? null,
                'date_worked' => $autotaskTimeEntry['dateWorked'] ?? null,
                'hours_worked' => $autotaskTimeEntry['hoursWorked'] ?? 0,
                'hours_to_bill' => $autotaskTimeEntry['hoursToBill'] ?? 0,
                'billable' => $autotaskTimeEntry['isBillable'] ?? false,
                'internal_notes' => $autotaskTimeEntry['internalNotes'] ?? null,
                'summary_notes' => $autotaskTimeEntry['summaryNotes'] ?? null,
                'role_id' => $autotaskTimeEntry['roleID'] ?? null,
                'task_id' => $autotaskTimeEntry['taskID'] ?? null,
            ],
        ];
    }

    public static function mapContract(array $autotaskContract): array
    {
        return [
            'integration_customer_id' => null, // Will be set by caller
            'data_type' => 'contract',
            'timestamp' => $autotaskContract['startDate'] ?? now(),
            'payload' => [
                'contract_id' => $autotaskContract['id'],
                'contract_name' => $autotaskContract['contractName'] ?? 'Unnamed Contract',
                'contract_number' => $autotaskContract['contractNumber'] ?? null,
                'contract_type' => $autotaskContract['contractType'] ?? null,
                'status' => $autotaskContract['status'] ?? null,
                'start_date' => $autotaskContract['startDate'] ?? null,
                'end_date' => $autotaskContract['endDate'] ?? null,
                'contract_value' => $autotaskContract['contractValue'] ?? 0,
                'billing_preference' => $autotaskContract['billingPreference'] ?? null,
                'company_id' => $autotaskContract['companyID'] ?? null,
            ],
        ];
    }
}
