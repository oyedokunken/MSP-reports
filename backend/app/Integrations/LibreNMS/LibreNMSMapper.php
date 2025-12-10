<?php

namespace App\Integrations\LibreNMS;

use Carbon\Carbon;

class LibreNMSMapper
{
    public static function mapDeviceGroup(array $group): array
    {
        return [
            'external_id' => 'group_' . $group['id'],
            'name' => $group['name'] ?? 'Unknown Group',
            'active' => true,
            'metadata' => [
                'source' => 'librenms',
                'group_id' => $group['id'],
                'description' => $group['desc'] ?? null,
                'type' => $group['type'] ?? null, // dynamic or static
                'rules' => $group['rules'] ?? null,
                'pattern' => $group['pattern'] ?? null,
            ],
        ];
    }

    public static function mapDevice(array $device, ?array $groups = null): array
    {
        $deviceId = $device['device_id'];
        $hostname = $device['hostname'] ?? $device['sysName'] ?? 'Unknown';
        
        return [
            'external_id' => 'device_' . $deviceId,
            'name' => $hostname,
            'active' => ($device['status'] ?? 0) == 1 && ($device['disabled'] ?? 0) == 0,
            'metadata' => [
                // Source Information
                'source' => 'librenms',
                'device_id' => $deviceId,
                
                // Basic Information
                'hostname' => $device['hostname'] ?? null,
                'sysName' => $device['sysName'] ?? null,
                'display' => $device['display'] ?? null,
                'ip' => $device['ip'] ?? null,
                'type' => $device['type'] ?? null, // network, server, workstation, etc.
                'os' => $device['os'] ?? null,
                'version' => $device['version'] ?? null,
                'hardware' => $device['hardware'] ?? null,
                'serial' => $device['serial'] ?? null,
                'features' => $device['features'] ?? null,
                
                // Status
                'status' => $device['status'] ?? 0,
                'status_reason' => $device['status_reason'] ?? '',
                'disabled' => $device['disabled'] ?? 0,
                'ignore' => $device['ignore'] ?? 0,
                
                // System Information
                'sysDescr' => $device['sysDescr'] ?? null,
                'sysObjectID' => $device['sysObjectID'] ?? null,
                'sysContact' => $device['sysContact'] ?? null,
                
                // Location
                'location' => $device['location'] ?? null,
                'location_id' => $device['location_id'] ?? null,
                'lat' => $device['lat'] ?? null,
                'lng' => $device['lng'] ?? null,
                
                // Uptime & Polling
                'uptime' => $device['uptime'] ?? null,
                'last_polled' => $device['last_polled'] ?? null,
                'last_discovered' => $device['last_discovered'] ?? null,
                'last_ping' => $device['last_ping'] ?? null,
                'last_ping_timetaken' => $device['last_ping_timetaken'] ?? null,
                
                // SNMP
                'snmpver' => $device['snmpver'] ?? null,
                'port' => $device['port'] ?? null,
                'transport' => $device['transport'] ?? null,
                
                // Groups
                'groups' => $groups ?? [],
                
                // Metadata
                'purpose' => $device['purpose'] ?? null,
                'notes' => $device['notes'] ?? null,
                'icon' => $device['icon'] ?? null,
                
                // Timestamps
                'inserted' => $device['inserted'] ?? null,
            ],
        ];
    }

    public static function mapDeviceAvailability(int $deviceId, array $availability): array
    {
        $availabilityData = [];
        
        foreach ($availability as $period) {
            $duration = $period['duration'] ?? 0;
            $percentage = floatval($period['availability_perc'] ?? 0);
            
            // Map duration to period name
            $periodName = match($duration) {
                86400 => '24h',
                604800 => '7d',
                2592000 => '30d',
                31536000 => '365d',
                default => $duration . 's',
            };
            
            $availabilityData[$periodName] = [
                'duration_seconds' => $duration,
                'availability_percent' => $percentage,
                'uptime_percent' => $percentage,
                'downtime_percent' => 100 - $percentage,
            ];
        }
        
        return [
            'integration_customer_id' => null, // Will be set by integration
            'data_type' => 'device_availability',
            'timestamp' => now(),
            'payload' => [
                'device_id' => $deviceId,
                'availability' => $availabilityData,
                'measured_at' => now()->toDateTimeString(),
            ],
        ];
    }

    public static function mapDeviceUptime(array $device): array
    {
        return [
            'integration_customer_id' => null, // Will be set by integration
            'data_type' => 'device_uptime',
            'timestamp' => now(),
            'payload' => [
                'device_id' => $device['device_id'],
                'hostname' => $device['hostname'] ?? $device['sysName'] ?? 'Unknown',
                'status' => $device['status'] ?? 0,
                'uptime_seconds' => $device['uptime'] ?? 0,
                'uptime_days' => isset($device['uptime']) ? round($device['uptime'] / 86400, 2) : 0,
                'last_polled' => $device['last_polled'] ?? null,
                'last_ping' => $device['last_ping'] ?? null,
                'last_ping_timetaken' => $device['last_ping_timetaken'] ?? null,
            ],
        ];
    }

    public static function mapDeviceDetails(array $device, array $groups = []): array
    {
        return [
            'integration_customer_id' => null, // Will be set by integration
            'data_type' => 'device_details',
            'timestamp' => now(),
            'payload' => [
                'device_id' => $device['device_id'],
                'hostname' => $device['hostname'] ?? $device['sysName'] ?? 'Unknown',
                'sysName' => $device['sysName'] ?? null,
                'ip' => $device['ip'] ?? null,
                'type' => $device['type'] ?? 'network',
                'hardware' => $device['hardware'] ?? null,
                'os' => $device['os'] ?? null,
                'version' => $device['version'] ?? null,
                'features' => $device['features'] ?? null,
                'location' => $device['location'] ?? null,
                'groups' => $groups,
                'status' => $device['status'] ?? 0,
                'serial' => $device['serial'] ?? null,
                'last_polled' => $device['last_polled'] ?? null,
            ],
        ];
    }
}
