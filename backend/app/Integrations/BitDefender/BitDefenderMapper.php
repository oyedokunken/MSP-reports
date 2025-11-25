<?php

namespace App\Integrations\BitDefender;

class BitDefenderMapper
{
    /**
     * Map BitDefender endpoint (device) data into a standardized IntegrationData payload.
     * Endpoints are actual computers/devices managed by BitDefender.
     */
    public static function mapEndpoint(array $endpoint): array
    {
        return [
            'data_type' => 'bitdefender_endpoint',
            'timestamp' => now(),
            'payload' => [
                'endpoint_id' => $endpoint['id'] ?? null,
                'name' => $endpoint['name'] ?? null,
                'label' => $endpoint['label'] ?? null,
                'fqdn' => $endpoint['fqdn'] ?? null,
                'group_id' => $endpoint['groupId'] ?? null,
                'is_managed' => $endpoint['isManaged'] ?? false,
                'machine_type' => $endpoint['machineType'] ?? null,
                'operating_system' => $endpoint['operatingSystemVersion'] ?? null,
                'ip' => $endpoint['ip'] ?? null,
                'mac_addresses' => $endpoint['macs'] ?? [],
                'ssid' => $endpoint['ssid'] ?? null,
                'managed_with_best' => $endpoint['managedWithBest'] ?? false,
                'policy' => $endpoint['policy'] ?? null,
                'moving_info' => $endpoint['movingInfo'] ?? null,
                'raw_data' => $endpoint,
            ],
        ];
    }

    /**
     * Map BitDefender threat detection into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapThreatDetection(array $threat): array
    {
        return [
            // No real schema yet; filled in once BitDefender API is defined
            'integration_customer_id' => null,
            'data_type' => 'bitdefender_threat_detection',
            'timestamp' => now(),
            'payload' => $threat,
        ];
    }
}
