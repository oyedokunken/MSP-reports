<?php

declare(strict_types=1);

namespace App\Integrations\RocketCyber;

use Carbon\Carbon;

final class RocketCyberMapper
{
    /**
     * Map RocketCyber account to standardized customer format
     * 
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    public static function mapAccount(array $account): array
    {
        $id = $account['id'] ?? $account['accountId'] ?? $account['Id'] ?? null;
        $name = $account['name'] ?? $account['accountName'] ?? $account['Name'] ?? 'Unknown Account';
        
        return [
            'external_id' => (string) $id,
            'name' => $name,
            'active' => ($account['status'] ?? $account['isActive'] ?? true) === true || 
                        ($account['status'] ?? '') === 'Active',
            'metadata' => [
                'account_id' => $id,
                'name' => $name,
                'status' => $account['status'] ?? 'Active',
                'agent_count' => $account['agentCount'] ?? $account['totalAgents'] ?? 0,
                'created_at' => $account['createdAt'] ?? $account['dateCreated'] ?? null,
                'type' => $account['type'] ?? $account['accountType'] ?? null,
            ],
        ];
    }

    /**
     * Map RocketCyber security incident into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $incident
     * @return array<string, mixed>
     */
    public static function mapSecurityIncident(array $incident, ?int $integrationCustomerId = null): array
    {
        $id = $incident['id'] ?? $incident['incidentId'] ?? $incident['Id'] ?? null;
        $timestamp = $incident['timestamp'] ?? $incident['createdAt'] ?? $incident['dateTime'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'rocketcyber_security_incident',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'title' => $incident['title'] ?? $incident['name'] ?? 'Unknown Incident',
                'description' => $incident['description'] ?? $incident['details'] ?? null,
                'severity' => $incident['severity'] ?? $incident['priority'] ?? 'Medium',
                'status' => $incident['status'] ?? 'Open',
                'account_id' => $incident['accountId'] ?? null,
                'agent_id' => $incident['agentId'] ?? null,
                'hostname' => $incident['hostname'] ?? $incident['machineName'] ?? null,
                'category' => $incident['category'] ?? $incident['type'] ?? null,
                'resolved_at' => $incident['resolvedAt'] ?? $incident['closedAt'] ?? null,
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Map RocketCyber threat detection into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $threat
     * @return array<string, mixed>
     */
    public static function mapThreatDetection(array $threat, ?int $integrationCustomerId = null): array
    {
        $id = $threat['id'] ?? $threat['eventId'] ?? $threat['Id'] ?? null;
        $timestamp = $threat['timestamp'] ?? $threat['detectedAt'] ?? $threat['dateTime'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'rocketcyber_threat_detection',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'threat_type' => $threat['threatType'] ?? $threat['type'] ?? 'Unknown',
                'severity' => $threat['severity'] ?? $threat['level'] ?? 'Medium',
                'account_id' => $threat['accountId'] ?? null,
                'agent_id' => $threat['agentId'] ?? null,
                'hostname' => $threat['hostname'] ?? $threat['machineName'] ?? null,
                'user' => $threat['user'] ?? $threat['userName'] ?? null,
                'description' => $threat['description'] ?? $threat['message'] ?? null,
                'source' => $threat['source'] ?? $threat['detectionSource'] ?? null,
                'file_path' => $threat['filePath'] ?? $threat['path'] ?? null,
                'file_hash' => $threat['fileHash'] ?? $threat['hash'] ?? null,
                'action_taken' => $threat['actionTaken'] ?? $threat['action'] ?? null,
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Map RocketCyber agent to standardized format
     * 
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    public static function mapAgent(array $agent, ?int $integrationCustomerId = null): array
    {
        $id = $agent['id'] ?? $agent['agentId'] ?? $agent['Id'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'rocketcyber_agent',
            'timestamp' => Carbon::parse($agent['lastSeen'] ?? $agent['lastCheckIn'] ?? now()),
            'payload' => [
                'id' => $id,
                'hostname' => $agent['hostname'] ?? $agent['name'] ?? 'Unknown',
                'account_id' => $agent['accountId'] ?? null,
                'os' => $agent['operatingSystem'] ?? $agent['os'] ?? null,
                'os_version' => $agent['osVersion'] ?? null,
                'agent_version' => $agent['agentVersion'] ?? $agent['version'] ?? null,
                'last_seen' => $agent['lastSeen'] ?? $agent['lastCheckIn'] ?? null,
                'status' => $agent['status'] ?? 'Unknown',
                'ip_address' => $agent['ipAddress'] ?? $agent['localIp'] ?? null,
                'is_online' => $agent['isOnline'] ?? ($agent['status'] === 'Online'),
            ],
        ];
    }
}
