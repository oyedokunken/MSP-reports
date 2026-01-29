<?php

declare(strict_types=1);

namespace App\Integrations\Autotask;

final class AutotaskMapper
{
    /**
     * @param array<string, mixed> $autotaskCompany
     * @return array<string, mixed>
     */
    public static function mapCompany(array $autotaskCompany): array
    {
        return [
            'external_id' => (string) $autotaskCompany['id'],
            'name' => $autotaskCompany['companyName'] ?? 'Unknown',
            'active' => ($autotaskCompany['isActive'] ?? true) === true,
            'metadata' => [
                // Basic Information
                'company_type' => $autotaskCompany['companyType'] ?? null,
                'company_number' => $autotaskCompany['companyNumber'] ?? null,
                'company_category_id' => $autotaskCompany['companyCategoryID'] ?? null,
                'classification' => $autotaskCompany['classification'] ?? null,
                
                // Contact Information
                'phone' => $autotaskCompany['phone'] ?? null,
                'fax' => $autotaskCompany['fax'] ?? null,
                'alternate_phone1' => $autotaskCompany['alternatePhone1'] ?? null,
                'alternate_phone2' => $autotaskCompany['alternatePhone2'] ?? null,
                'website' => $autotaskCompany['webAddress'] ?? null,
                
                // Primary Address
                'address1' => $autotaskCompany['address1'] ?? null,
                'address2' => $autotaskCompany['address2'] ?? null,
                'additional_address_info' => $autotaskCompany['additionalAddressInformation'] ?? null,
                'city' => $autotaskCompany['city'] ?? null,
                'state' => $autotaskCompany['state'] ?? null,
                'postal_code' => $autotaskCompany['postalCode'] ?? null,
                'country_id' => $autotaskCompany['countryID'] ?? null,
                
                // Billing Address
                'billing_address1' => $autotaskCompany['billingAddress1'] ?? null,
                'billing_address2' => $autotaskCompany['billingAddress2'] ?? null,
                'bill_to_additional_address_info' => $autotaskCompany['billToAdditionalAddressInformation'] ?? null,
                'bill_to_city' => $autotaskCompany['billToCity'] ?? null,
                'bill_to_state' => $autotaskCompany['billToState'] ?? null,
                'bill_to_zip_code' => $autotaskCompany['billToZipCode'] ?? null,
                'bill_to_country_id' => $autotaskCompany['billToCountryID'] ?? null,
                'bill_to_attention' => $autotaskCompany['billToAttention'] ?? null,
                'bill_to_address_to_use' => $autotaskCompany['billToAddressToUse'] ?? null,
                'bill_to_company_location_id' => $autotaskCompany['billToCompanyLocationID'] ?? null,
                
                // Financial Information
                'currency_id' => $autotaskCompany['currencyID'] ?? null,
                'is_tax_exempt' => $autotaskCompany['isTaxExempt'] ?? false,
                'tax_id' => $autotaskCompany['taxID'] ?? null,
                'tax_region_id' => $autotaskCompany['taxRegionID'] ?? null,
                'asset_value' => $autotaskCompany['assetValue'] ?? null,
                
                // Business Details
                'sic_code' => $autotaskCompany['sicCode'] ?? null,
                'stock_symbol' => $autotaskCompany['stockSymbol'] ?? null,
                'stock_market' => $autotaskCompany['stockMarket'] ?? null,
                'market_segment_id' => $autotaskCompany['marketSegmentID'] ?? null,
                'territory_id' => $autotaskCompany['territoryID'] ?? null,
                'competitor_id' => $autotaskCompany['competitorID'] ?? null,
                
                // Account Management
                'owner_resource_id' => $autotaskCompany['ownerResourceID'] ?? null,
                'parent_company_id' => $autotaskCompany['parentCompanyID'] ?? null,
                
                // Portal & Integration
                'is_client_portal_active' => $autotaskCompany['isClientPortalActive'] ?? false,
                'is_enabled_for_comanaged' => $autotaskCompany['isEnabledForComanaged'] ?? false,
                'is_task_fire_active' => $autotaskCompany['isTaskFireActive'] ?? false,
                'api_vendor_id' => $autotaskCompany['apiVendorID'] ?? null,
                
                // Invoice & Quote Settings
                'invoice_method' => $autotaskCompany['invoiceMethod'] ?? null,
                'invoice_template_id' => $autotaskCompany['invoiceTemplateID'] ?? null,
                'invoice_email_message_id' => $autotaskCompany['invoiceEmailMessageID'] ?? null,
                'invoice_non_contract_items_to_parent' => $autotaskCompany['invoiceNonContractItemsToParentCompany'] ?? null,
                'quote_template_id' => $autotaskCompany['quoteTemplateID'] ?? null,
                'quote_email_message_id' => $autotaskCompany['quoteEmailMessageID'] ?? null,
                'purchase_order_template_id' => $autotaskCompany['purchaseOrderTemplateID'] ?? null,
                
                // Ratings & Feedback
                'survey_company_rating' => $autotaskCompany['surveyCompanyRating'] ?? null,
                
                // Timestamps
                'create_date' => $autotaskCompany['createDate'] ?? null,
                'created_by_resource_id' => $autotaskCompany['createdByResourceID'] ?? null,
                'last_activity_date' => $autotaskCompany['lastActivityDate'] ?? null,
                'last_tracked_modified_date_time' => $autotaskCompany['lastTrackedModifiedDateTime'] ?? null,
                'impersonator_creator_resource_id' => $autotaskCompany['impersonatorCreatorResourceID'] ?? null,
                
                // Custom Fields
                'user_defined_fields' => $autotaskCompany['userDefinedFields'] ?? [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $autotaskTicket
     * @return array<string, mixed>
     */
    public static function mapTicket(array $autotaskTicket): array
    {
        return [
            'integration_customer_id' => null, 
            'data_type' => 'ticket',
            'timestamp' => $autotaskTicket['lastActivityDate'] ?? $autotaskTicket['createDate'] ?? now(),
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

    /**
     * @param array<string, mixed> $autotaskTimeEntry
     * @return array<string, mixed>
     */
    public static function mapTimeEntry(array $autotaskTimeEntry): array
    {
        return [
            'integration_customer_id' => null,
            'data_type' => 'time_entry',
            'timestamp' => $autotaskTimeEntry['dateWorked'] ?? now(),
            'payload' => [
                'time_entry_id' => $autotaskTimeEntry['id'],
                'ticket_id' => $autotaskTimeEntry['ticketID'] ?? null,
                'resource_id' => $autotaskTimeEntry['resourceID'] ?? null,
                'date_worked' => $autotaskTimeEntry['dateWorked'] ?? null,
                'hours_worked' => $autotaskTimeEntry['hoursWorked'] ?? 0,
                'hours_to_bill' => $autotaskTimeEntry['hoursToBill'] ?? 0,
                'billable' => !($autotaskTimeEntry['isNonBillable'] ?? false),
                'internal_notes' => $autotaskTimeEntry['internalNotes'] ?? null,
                'summary_notes' => $autotaskTimeEntry['summaryNotes'] ?? null,
                'role_id' => $autotaskTimeEntry['roleID'] ?? null,
                'task_id' => $autotaskTimeEntry['taskID'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $autotaskResource
     * @return array<string, mixed>
     */
    public static function mapResource(array $autotaskResource): array
    {
        return [
            'integration_customer_id' => null, // Resources are not customer-specific
            'data_type' => 'resource',
            'timestamp' => now(),
            'payload' => [
                'resource_id' => $autotaskResource['id'],
                'first_name' => $autotaskResource['firstName'] ?? '',
                'last_name' => $autotaskResource['lastName'] ?? '',
                'email' => $autotaskResource['email'] ?? null,
                'title' => $autotaskResource['title'] ?? '',
                'is_active' => $autotaskResource['isActive'] ?? false,
                'user_name' => $autotaskResource['userName'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $autotaskContract
     * @return array<string, mixed>
     */
    public static function mapContract(array $autotaskContract): array
    {
        return [
            'integration_customer_id' => null,
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

    /**
     * @param array<string, mixed> $autotaskContact
     * @return array<string, mixed>
     */
    public static function mapContact(array $autotaskContact): array
    {
        return [
            'integration_customer_id' => null, // Will be mapped to company if possible, but primarily a company sub-resource
            'data_type' => 'contact',
            'timestamp' => $autotaskContact['lastActivityDate'] ?? $autotaskContact['createDate'] ?? now(),
            'payload' => [
                'contact_id' => $autotaskContact['id'],
                'company_id' => $autotaskContact['companyID'] ?? null,
                'first_name' => $autotaskContact['firstName'] ?? null,
                'last_name' => $autotaskContact['lastName'] ?? null,
                'email' => $autotaskContact['emailAddress'] ?? null,
                'phone' => $autotaskContact['phone'] ?? null,
                'mobile_phone' => $autotaskContact['mobilePhone'] ?? null,
                'title' => $autotaskContact['title'] ?? null,
                'is_active' => $autotaskContact['isActive'] ?? true,
                'is_primary_contact' => $autotaskContact['isPrimaryContact'] ?? false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $autotaskNote
     * @return array<string, mixed>
     */
    public static function mapTicketNote(array $autotaskNote): array
    {
        return [
            'integration_customer_id' => null,
            'data_type' => 'ticket_note',
            'timestamp' => $autotaskNote['createDateTime'] ?? now(),
            'payload' => [
                'note_id' => $autotaskNote['id'],
                'ticket_id' => $autotaskNote['ticketID'] ?? null,
                'title' => $autotaskNote['title'] ?? null,
                'description' => $autotaskNote['description'] ?? null,
                'note_type' => $autotaskNote['noteType'] ?? null,
                'publish' => $autotaskNote['publish'] ?? null,
                'created_by_contact_id' => $autotaskNote['createdByContactID'] ?? null,
                'created_by_resource_id' => $autotaskNote['creatorResourceID'] ?? null,
                'created_at' => $autotaskNote['createDateTime'] ?? null,
            ],
        ];
    }
}
