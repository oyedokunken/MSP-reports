<?php

namespace App\Integrations\ThreatLocker;

use Carbon\Carbon;

class ThreatLockerMapper
{
    /**
     * Map ThreatLocker organization to standardized customer format
     * 
     * @param array<string, mixed> $org
     * @return array<string, mixed>
     */
    public static function mapOrganization(array $org): array
    {
        $id = $org['id'] ?? $org['organizationId'] ?? $org['Id'] ?? null;
        $name = $org['name'] ?? $org['organizationName'] ?? $org['Name'] ?? 'Unknown Organization';
        
        return [
            'external_id' => (string) $id,
            'name' => $name,
            'active' => ($org['status'] ?? $org['isActive'] ?? true) === true || 
                        ($org['status'] ?? '') === 'Active',
            'metadata' => [
                'organization_id' => $id,
                'name' => $name,
                'status' => $org['status'] ?? 'Active',
                'computer_count' => $org['computerCount'] ?? $org['totalComputers'] ?? 0,
                'created_at' => $org['createdAt'] ?? $org['dateCreated'] ?? null,
                'parent_id' => $org['parentId'] ?? $org['parentOrganizationId'] ?? null,
            ],
        ];
    }

    /**
     * Map ThreatLocker computer/endpoint to standardized format
     * 
     * @param array<string, mixed> $computer
     * @return array<string, mixed>
     */
    public static function mapComputer(array $computer, ?int $integrationCustomerId = null): array
    {
        $id = $computer['id'] ?? $computer['computerId'] ?? $computer['Id'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'threatlocker_computer',
            'timestamp' => Carbon::parse($computer['lastSeen'] ?? $computer['lastCheckIn'] ?? now()),
            'payload' => [
                'id' => $id,
                'computer_name' => $computer['computerName'] ?? $computer['name'] ?? 'Unknown',
                'organization_id' => $computer['organizationId'] ?? null,
                'os' => $computer['operatingSystem'] ?? $computer['os'] ?? null,
                'os_version' => $computer['osVersion'] ?? null,
                'agent_version' => $computer['agentVersion'] ?? $computer['version'] ?? null,
                'last_seen' => $computer['lastSeen'] ?? $computer['lastCheckIn'] ?? null,
                'status' => $computer['status'] ?? 'Unknown',
                'ip_address' => $computer['ipAddress'] ?? $computer['localIp'] ?? null,
                'is_online' => $computer['isOnline'] ?? ($computer['status'] === 'Online'),
            ],
        ];
    }

    /**
     * Map ThreatLocker policy data into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public static function mapPolicy(array $policy, ?int $integrationCustomerId = null): array
    {
        $id = $policy['id'] ?? $policy['policyId'] ?? $policy['Id'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'threatlocker_policy',
            'timestamp' => Carbon::parse($policy['createdAt'] ?? $policy['dateCreated'] ?? now()),
            'payload' => [
                'id' => $id,
                'name' => $policy['name'] ?? $policy['policyName'] ?? 'Unknown Policy',
                'type' => $policy['type'] ?? $policy['policyType'] ?? null,
                'organization_id' => $policy['organizationId'] ?? null,
                'status' => $policy['status'] ?? 'Active',
                'description' => $policy['description'] ?? null,
                'created_at' => $policy['createdAt'] ?? $policy['dateCreated'] ?? null,
                'updated_at' => $policy['updatedAt'] ?? $policy['dateModified'] ?? null,
            ],
        ];
    }

    /**
     * Map ThreatLocker security event into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public static function mapSecurityEvent(array $event, ?int $integrationCustomerId = null): array
    {
        $id = $event['id'] ?? $event['eventId'] ?? $event['Id'] ?? null;
        $timestamp = $event['timestamp'] ?? $event['eventTime'] ?? $event['dateTime'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'threatlocker_security_event',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'event_type' => $event['eventType'] ?? $event['type'] ?? 'Unknown',
                'severity' => $event['severity'] ?? $event['level'] ?? 'Info',
                'computer_name' => $event['computerName'] ?? $event['machineName'] ?? null,
                'computer_id' => $event['computerId'] ?? null,
                'organization_id' => $event['organizationId'] ?? null,
                'user' => $event['user'] ?? $event['userName'] ?? null,
                'description' => $event['description'] ?? $event['message'] ?? null,
                'application' => $event['application'] ?? $event['processName'] ?? null,
                'path' => $event['path'] ?? $event['filePath'] ?? null,
                'hash' => $event['hash'] ?? $event['fileHash'] ?? null,
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Map ThreatLocker application block into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    public static function mapApplicationBlock(array $block, ?int $integrationCustomerId = null): array
    {
        $id = $block['id'] ?? $block['blockId'] ?? $block['Id'] ?? null;
        $timestamp = $block['timestamp'] ?? $block['blockTime'] ?? $block['dateTime'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'threatlocker_application_block',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'application_name' => $block['applicationName'] ?? $block['processName'] ?? 'Unknown',
                'computer_name' => $block['computerName'] ?? $block['machineName'] ?? null,
                'computer_id' => $block['computerId'] ?? null,
                'organization_id' => $block['organizationId'] ?? null,
                'user' => $block['user'] ?? $block['userName'] ?? null,
                'path' => $block['path'] ?? $block['filePath'] ?? null,
                'hash' => $block['hash'] ?? $block['fileHash'] ?? null,
                'reason' => $block['reason'] ?? $block['blockReason'] ?? null,
                'policy_name' => $block['policyName'] ?? null,
                'timestamp' => $timestamp,
            ],
        ];
    }
}
