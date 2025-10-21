<?php

namespace App\Integrations\Datto;

class DattoMapper
{
    /**
     * Map Datto customer to standardized format.
     */
    public static function mapCustomer(array $dattoCustomer): array
    {
        return [
            'external_id' => $dattoCustomer['id'] ?? null,
            'name' => $dattoCustomer['name'] ?? 'Unknown',
            'active' => $dattoCustomer['status'] === 'active',
            'metadata' => [
                'datto_id' => $dattoCustomer['id'] ?? null,
                'account_type' => $dattoCustomer['account_type'] ?? null,
                'created_at' => $dattoCustomer['created_at'] ?? null,
                'contact_email' => $dattoCustomer['contact_email'] ?? null,
                'contact_phone' => $dattoCustomer['contact_phone'] ?? null,
            ],
        ];
    }

    /**
     * Map Datto backup data to standardized format.
     */
    public static function mapBackupData(array $dattoBackup): array
    {
        return [
            'integration_customer_id' => $dattoBackup['customer_id'] ?? null,
            'data_type' => 'backup_status',
            'timestamp' => $dattoBackup['timestamp'] ?? now(),
            'payload' => [
                'backup_id' => $dattoBackup['id'] ?? null,
                'device_name' => $dattoBackup['device_name'] ?? null,
                'status' => $dattoBackup['status'] ?? 'unknown',
                'success' => $dattoBackup['success'] ?? false,
                'size_bytes' => $dattoBackup['size_bytes'] ?? 0,
                'duration_seconds' => $dattoBackup['duration_seconds'] ?? 0,
                'error_message' => $dattoBackup['error_message'] ?? null,
            ],
        ];
    }

    /**
     * Map Datto device uptime data to standardized format.
     */
    public static function mapUptimeData(array $dattoUptime): array
    {
        return [
            'integration_customer_id' => $dattoUptime['customer_id'] ?? null,
            'data_type' => 'uptime_metric',
            'timestamp' => $dattoUptime['timestamp'] ?? now(),
            'payload' => [
                'device_id' => $dattoUptime['device_id'] ?? null,
                'device_name' => $dattoUptime['device_name'] ?? null,
                'uptime_percentage' => $dattoUptime['uptime_percentage'] ?? 0,
                'downtime_minutes' => $dattoUptime['downtime_minutes'] ?? 0,
                'incidents' => $dattoUptime['incidents'] ?? [],
            ],
        ];
    }
}
