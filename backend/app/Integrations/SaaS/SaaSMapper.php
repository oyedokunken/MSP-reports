<?php

namespace App\Integrations\SaaS;

class SaaSMapper
{
    /**
     * Map SaaS Alerts customer to standardized IntegrationCustomer format.
     * 
     * @param array $saasCustomer Raw customer data from SaaS Alerts API
     * @return array Standardized customer data
     */
    public static function mapCustomer(array $saasCustomer): array
    {
        return [
            'external_id' => $saasCustomer['id'],
            'name' => $saasCustomer['name'],
            'active' => ($saasCustomer['status'] ?? 'active') === 'active',
            'metadata' => [
                'email' => $saasCustomer['email'] ?? null,
                'partner_id' => $saasCustomer['partnerId'] ?? null,
                'create_time' => $saasCustomer['createTime'] ?? null,
                'countries' => $saasCustomer['countries'] ?? [],
                'products' => $saasCustomer['products'] ?? [],
                'ip_ranges' => $saasCustomer['ipRanges'] ?? [],
                'status' => $saasCustomer['status'] ?? 'active',
            ],
        ];
    }

    /**
     * Map SaaS Alerts user to standardized IntegrationData format.
     * 
     * @param array $saasUser Raw user data from SaaS Alerts API
     * @param string $integrationCustomerId The IntegrationCustomer ID this user belongs to
     * @return array Standardized user data for IntegrationData storage
     */
    public static function mapUser(array $saasUser, ?string $integrationCustomerId = null): array
    {
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'saas_user',
            'timestamp' => isset($saasUser['time']) ? new \DateTime($saasUser['time']) : now(),
            'payload' => [
                'user_id' => $saasUser['id'] ?? null,
                'name' => $saasUser['name'] ?? null,
                'email' => $saasUser['email'] ?? null,
                'username' => $saasUser['username'] ?? null,
                'account_enabled' => $saasUser['accountEnabled'] ?? true,
                'is_licensed' => $saasUser['isLicensed'] ?? false,
                'is_billable' => $saasUser['isBillable'] ?? false,
                'is_service_account' => $saasUser['isServiceAccount'] ?? false,
                'product' => $saasUser['product'] ?? null,
                'customer_connection_id' => $saasUser['customerConnectionId'] ?? null,
                'role' => $saasUser['role'] ?? null,
                'usage_location' => $saasUser['usageLocation'] ?? null,
                'assigned_licenses' => $saasUser['assignedLicenses'] ?? [],
                'last_event' => $saasUser['lastEvent'] ?? null,
                'deleted_date_time' => $saasUser['deletedDateTime'] ?? null,
            ],
        ];
    }

    /**
     * Map SaaS Alerts security event to standardized IntegrationData format.
     * 
     * @param array $saasEvent Raw event data from SaaS Alerts API
     * @param int|string|null $integrationCustomerId The IntegrationCustomer ID this event belongs to
     * @return array Standardized event data for IntegrationData storage
     */
    public static function mapEvent(array $saasEvent, $integrationCustomerId = null): array
    {
        $eventId = $saasEvent['eventId'] ?? $saasEvent['_id'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'external_id' => $eventId,
            'data_type' => 'saas_security_event',
            'timestamp' => isset($saasEvent['time']) ? new \DateTime($saasEvent['time']) : now(),
            'payload' => [
                'event_id' => $eventId,
                'correlation_id' => $saasEvent['correlationId'] ?? null,
                'type' => $saasEvent['type'] ?? null,
                'joint_type' => $saasEvent['jointType'] ?? null,
                'joint_desc' => $saasEvent['jointDesc'] ?? null,
                'joint_desc_additional' => $saasEvent['jointDescAdditional'] ?? null,
                'alert_status' => $saasEvent['alertStatus'] ?? null,
                'status' => $saasEvent['status'] ?? null,
                
                // User information
                'user' => $saasEvent['user'] ?? null,
                'user_email' => $saasEvent['userEmail'] ?? null,
                
                // Location information
                'ip' => $saasEvent['ip'] ?? null,
                'location' => $saasEvent['location'] ?? null,
                
                // Product/Application information
                'product' => $saasEvent['product'] ?? null,
                'app_name' => $saasEvent['appName'] ?? null,
                'application_id' => $saasEvent['applicationId'] ?? null,
                'workload' => $saasEvent['workload'] ?? null,
                
                // Customer/Partner information
                'customer' => $saasEvent['customer'] ?? null,
                'partner' => $saasEvent['partner'] ?? null,
                
                // Device information
                'device' => $saasEvent['device'] ?? null,
                'devices' => $saasEvent['devices'] ?? [],
                
                // File/Item information
                'file_name' => $saasEvent['fileName'] ?? null,
                'file_id' => $saasEvent['fileId'] ?? null,
                'item_type' => $saasEvent['itemType'] ?? null,
                
                // PSA/Ticket information
                'psa_info' => $saasEvent['psaInfo'] ?? [],
                'psa_ticket' => $saasEvent['psaTicket'] ?? [],
                
                // Rule/Respond information
                'rule' => $saasEvent['rule'] ?? null,
                'rule_trigger' => $saasEvent['ruleTrigger'] ?? null,
                
                // Timestamps
                'pull_time' => $saasEvent['pullTime'] ?? null,
                
                // Additional metadata
                'operation' => $saasEvent['operation'] ?? null,
                'section' => $saasEvent['section'] ?? null,
                'do_not_show' => $saasEvent['doNotShow'] ?? false,
                'misc' => $saasEvent['misc'] ?? null,
            ],
        ];
    }
}
