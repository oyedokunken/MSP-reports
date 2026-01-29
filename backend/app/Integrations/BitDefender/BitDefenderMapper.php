<?php

declare(strict_types=1);

namespace App\Integrations\BitDefender;

final class BitDefenderMapper
{
    /**
     * Map BitDefender endpoint (device) data into a standardized IntegrationData payload.
     * Endpoints are actual computers/devices managed by BitDefender.
     * 
     * @param array<string, mixed> $endpoint
     * @return array<string, mixed>
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
     * Map BitDefender threat detection (quarantine item) into a standardized IntegrationData payload.
     * 
     * @param array<string, mixed> $threat
     * @return array<string, mixed>
     */
    public static function mapThreatDetection(array $threat, ?int $integrationCustomerId = null): array
    {
        // Parse detection time
        $detectionTime = now();
        if (isset($threat['detectionTime'])) {
            try {
                $detectionTime = \Carbon\Carbon::parse($threat['detectionTime']);
            } catch (\Exception $e) {
                // Use current time if parsing fails
            }
        }

        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'bitdefender_threat_detection',
            'timestamp' => $detectionTime,
            'payload' => [
                'threat_id' => $threat['id'] ?? null,
                'endpoint_id' => $threat['endpointId'] ?? null,
                'endpoint_name' => $threat['endpointName'] ?? null,
                'company_id' => $threat['companyId'] ?? null,
                'threat_name' => $threat['threatName'] ?? null,
                'threat_type' => $threat['threatType'] ?? null,
                'file_path' => $threat['filePath'] ?? null,
                'detection_time' => isset($threat['detectionTime']) ? $threat['detectionTime'] : null,
                'action_taken' => $threat['actionTaken'] ?? null,
                'status' => $threat['status'] ?? null,
                'raw_data' => $threat,
            ],
        ];
    }
}
