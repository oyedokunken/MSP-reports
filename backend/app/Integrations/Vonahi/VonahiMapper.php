<?php

namespace App\Integrations\Vonahi;

use Carbon\Carbon;

class VonahiMapper
{
    /**
     * Map Vonahi organization to standardized customer format
     */
    public static function mapOrganization(array $org): array
    {
        $id = $org['id'] ?? $org['organizationId'] ?? null;
        $name = $org['name'] ?? $org['organizationName'] ?? 'Unknown Organization';
        
        return [
            'external_id' => (string) $id,
            'name' => $name,
            'active' => ($org['status'] ?? 'active') === 'active',
            'metadata' => [
                'organization_id' => $id,
                'name' => $name,
                'status' => $org['status'] ?? 'active',
                'pentest_count' => $org['pentestCount'] ?? 0,
                'created_at' => $org['createdAt'] ?? null,
            ],
        ];
    }

    /**
     * Map Vonahi penetration test to standardized format
     */
    public static function mapPenTest(array $pentest, ?int $integrationCustomerId = null): array
    {
        $id = $pentest['id'] ?? $pentest['pentestId'] ?? null;
        $timestamp = $pentest['completedAt'] ?? $pentest['startedAt'] ?? $pentest['createdAt'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_pentest',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'name' => $pentest['name'] ?? $pentest['title'] ?? 'Penetration Test',
                'type' => $pentest['type'] ?? $pentest['testType'] ?? 'network',
                'status' => $pentest['status'] ?? 'completed',
                'organization_id' => $pentest['organizationId'] ?? null,
                'started_at' => $pentest['startedAt'] ?? null,
                'completed_at' => $pentest['completedAt'] ?? null,
                'findings_count' => $pentest['findingsCount'] ?? 0,
                'critical_count' => $pentest['criticalCount'] ?? 0,
                'high_count' => $pentest['highCount'] ?? 0,
                'medium_count' => $pentest['mediumCount'] ?? 0,
                'low_count' => $pentest['lowCount'] ?? 0,
                'scope' => $pentest['scope'] ?? null,
            ],
        ];
    }

    /**
     * Map Vonahi security finding into a standardized IntegrationData payload
     */
    public static function mapSecurityFinding(array $finding, ?int $integrationCustomerId = null): array
    {
        $id = $finding['id'] ?? $finding['findingId'] ?? null;
        $timestamp = $finding['discoveredAt'] ?? $finding['createdAt'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_security_finding',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'title' => $finding['title'] ?? $finding['name'] ?? 'Unknown Finding',
                'description' => $finding['description'] ?? null,
                'severity' => $finding['severity'] ?? $finding['risk'] ?? 'Medium',
                'cvss_score' => $finding['cvssScore'] ?? $finding['cvss'] ?? null,
                'status' => $finding['status'] ?? 'open',
                'pentest_id' => $finding['pentestId'] ?? null,
                'asset' => $finding['asset'] ?? $finding['target'] ?? null,
                'ip_address' => $finding['ipAddress'] ?? $finding['ip'] ?? null,
                'port' => $finding['port'] ?? null,
                'protocol' => $finding['protocol'] ?? null,
                'remediation' => $finding['remediation'] ?? $finding['recommendation'] ?? null,
                'cve' => $finding['cve'] ?? $finding['cveId'] ?? null,
                'discovered_at' => $timestamp,
            ],
        ];
    }

    /**
     * Map Vonahi scan to standardized format
     */
    public static function mapScan(array $scan, ?int $integrationCustomerId = null): array
    {
        $id = $scan['id'] ?? $scan['scanId'] ?? null;
        $timestamp = $scan['completedAt'] ?? $scan['startedAt'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_scan',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'name' => $scan['name'] ?? 'Vulnerability Scan',
                'type' => $scan['type'] ?? $scan['scanType'] ?? 'vulnerability',
                'status' => $scan['status'] ?? 'completed',
                'organization_id' => $scan['organizationId'] ?? null,
                'started_at' => $scan['startedAt'] ?? null,
                'completed_at' => $scan['completedAt'] ?? null,
                'hosts_scanned' => $scan['hostsScanned'] ?? 0,
                'vulnerabilities_found' => $scan['vulnerabilitiesFound'] ?? 0,
            ],
        ];
    }

    /**
     * Map Vonahi asset to standardized format
     */
    public static function mapAsset(array $asset, ?int $integrationCustomerId = null): array
    {
        $id = $asset['id'] ?? $asset['assetId'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_asset',
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'name' => $asset['name'] ?? $asset['hostname'] ?? 'Unknown Asset',
                'ip_address' => $asset['ipAddress'] ?? $asset['ip'] ?? null,
                'hostname' => $asset['hostname'] ?? null,
                'type' => $asset['type'] ?? $asset['assetType'] ?? null,
                'os' => $asset['operatingSystem'] ?? $asset['os'] ?? null,
                'organization_id' => $asset['organizationId'] ?? null,
                'status' => $asset['status'] ?? 'active',
                'last_scanned' => $asset['lastScanned'] ?? null,
            ],
        ];
    }
}
