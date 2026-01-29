<?php

declare(strict_types=1);

namespace App\Integrations\Datto;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class DattoMapper
{
    /**
     * @param array<string, mixed> $dattoCustomer
     * @return array<string, mixed>
     */
    public static function mapCustomer(array $dattoCustomer): array
    {
        if (isset($dattoCustomer['uid']) && isset($dattoCustomer['devicesStatus'])) {
            return self::mapRmmSite($dattoCustomer);
        }
        
        if (isset($dattoCustomer['organizationId']) && isset($dattoCustomer['backupStats'])) {
            return self::mapSaasBackupData($dattoCustomer);
        }

        return [
            'external_id' => $dattoCustomer['id'] ?? null,
            'name' => $dattoCustomer['name'] ?? 'Unknown',
            'active' => ($dattoCustomer['status'] ?? 'active') === 'active',
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
     * @param array<string, mixed> $site
     * @return array<string, mixed>
     */
    public static function mapRmmSite(array $site): array
    {
        $devicesStatus = $site['devicesStatus'] ?? [];
        
        return [
            'external_id' => $site['uid'],
            'name' => $site['name'],
            'active' => !($site['deleted'] ?? false),
            'metadata' => [
                'source' => 'datto_rmm',
                'site_id' => $site['id'] ?? null,
                'account_uid' => $site['accountUid'] ?? null,
                'description' => $site['description'] ?? null,
                'notes' => $site['notes'] ?? null,
                'on_demand' => $site['onDemand'] ?? false,
                'devices_total' => $devicesStatus['numberOfDevices'] ?? 0,
                'devices_online' => $devicesStatus['numberOfOnlineDevices'] ?? 0,
                'devices_offline' => $devicesStatus['numberOfOfflineDevices'] ?? 0,
                'autotask_company_name' => $site['autotaskCompanyName'] ?? null,
                'autotask_company_id' => $site['autotaskCompanyId'] ?? null,
                'portal_url' => $site['portalUrl'] ?? null,
            ],
        ];
    }
    
    /**
     * @param array<string, mixed> $domain
     * @return array<string, mixed>
     */
    public static function mapSaasBackupData(array $domain): array
    {
        $stats = $domain['backupStats'] ?? [];
        
        return [
            'external_id' => (string)$domain['organizationId'],
            'name' => $domain['saasCustomerName'] ?? $domain['organizationName'] ?? 'Unknown',
            'active' => true,
            'metadata' => [
                'source' => 'datto_saas',
                'organization_id' => $domain['organizationId'] ?? null,
                'organization_name' => $domain['organizationName'] ?? null,
                'domain' => $domain['domain'] ?? null,
                'product_type' => $domain['productType'] ?? null,
                'backup_percentage' => $stats['backupPercentage'] ?? 0,
                'active_services' => $stats['activeServicesCount'] ?? 0,
                'backed_up_services' => $stats['activeServicesWithRecentBackupCount'] ?? 0,
                'missing_backups' => ($stats['activeServicesCount'] ?? 0) - ($stats['activeServicesWithRecentBackupCount'] ?? 0),
                'seats_used' => $domain['seatsUsed'] ?? null,
                'retention_type' => $domain['retentionType'] ?? null,
                'external_subscription_id' => $domain['externalSubscriptionId'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $dattoBackup
     * @return array<string, mixed>
     */
    public static function mapBackupData(array $dattoBackup): array
    {
        if (isset($dattoBackup['backupStats'])) {
            $stats = $dattoBackup['backupStats'];
            return [
                'integration_customer_id' => $dattoBackup['organizationId'] ?? null,
                'data_type' => 'backup_status',
                'timestamp' => now(),
                'payload' => [
                    'organization_name' => $dattoBackup['organizationName'] ?? null,
                    'domain' => $dattoBackup['domain'] ?? null,
                    'product_type' => $dattoBackup['productType'] ?? null,
                    'backup_percentage' => $stats['backupPercentage'] ?? 0,
                    'active_services' => $stats['activeServicesCount'] ?? 0,
                    'backed_up_services' => $stats['activeServicesWithRecentBackupCount'] ?? 0,
                    'missing_backups' => ($stats['activeServicesCount'] ?? 0) - ($stats['activeServicesWithRecentBackupCount'] ?? 0),
                    'seats_used' => $dattoBackup['seatsUsed'] ?? null,
                ],
            ];
        }
        
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
     * @param array<string, mixed> $device
     * @return array<string, mixed>
     */
    public static function mapDeviceData(array $device): array
    {
        return [
            'integration_customer_id' => $device['siteUid'] ?? null,
            'data_type' => 'device_status',
            'timestamp' => isset($device['lastSeen']) 
                ? Carbon::createFromTimestampMs($device['lastSeen']) 
                : now(),
            'payload' => [
                'device_uid' => $device['uid'] ?? null,
                'hostname' => $device['hostname'] ?? null,
                'site_name' => $device['siteName'] ?? null,
                'online' => $device['online'] ?? false,
                'operating_system' => $device['operatingSystem'] ?? null,
                'ip_address' => $device['intIpAddress'] ?? null,
                'external_ip' => $device['extIpAddress'] ?? null,
                'domain' => $device['domain'] ?? null,
                'device_type' => $device['deviceType']['type'] ?? null,
                'last_seen' => isset($device['lastSeen']) 
                    ? Carbon::createFromTimestampMs($device['lastSeen'])->toIso8601String() 
                    : null,
                'last_reboot' => isset($device['lastReboot']) 
                    ? Carbon::createFromTimestampMs($device['lastReboot'])->toIso8601String() 
                    : null,
                'antivirus_status' => $device['antivirus'] ?? null,
                'patch_status' => $device['patchManagement'] ?? null,
                'reboot_required' => $device['rebootRequired'] ?? false,
                'suspended' => $device['suspended'] ?? false,
            ],
        ];
    }
    
    /**
     * @param array<string, mixed> $alert
     * @return array<string, mixed>
     */
    public static function mapAlertData(array $alert): array
    {
        return [
            'integration_customer_id' => $alert['alertSourceInfo']['siteUid'] ?? null,
            'data_type' => 'alert',
            'timestamp' => isset($alert['timestamp']) 
                ? Carbon::createFromTimestampMs($alert['timestamp']) 
                : now(),
            'payload' => [
                'alert_uid' => $alert['alertUid'] ?? null,
                'device_uid' => $alert['alertSourceInfo']['deviceUid'] ?? null,
                'device_name' => $alert['alertSourceInfo']['deviceName'] ?? null,
                'priority' => $alert['priority'] ?? null,
                'description' => $alert['alertContext']['description'] ?? null,
                'source' => $alert['alertContext']['source'] ?? null,
                'event_type' => $alert['alertContext']['type'] ?? null,
                'resolved' => $alert['resolved'] ?? false,
                'resolved_at' => isset($alert['resolvedOn']) 
                    ? Carbon::createFromTimestampMs($alert['resolvedOn'])->toIso8601String() 
                    : null,
                'muted' => $alert['muted'] ?? false,
                'ticket_number' => $alert['ticketNumber'] ?? null,
            ],
        ];
    }
    
    /**
     * @param array<string, mixed> $dattoUptime
     * @return array<string, mixed>
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
