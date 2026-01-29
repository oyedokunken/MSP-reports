<?php

namespace App\Integrations\Kemp;

use Carbon\Carbon;

class KempMapper
{
    /**
     * Map Kemp Virtual Service to standardized format
     * 
     * @param array<string, mixed> $vs
     * @return array<string, mixed>
     */
    public static function mapVirtualService(array $vs, ?int $integrationCustomerId = null): array
    {
        $index = $vs['Index'] ?? $vs['index'] ?? null;
        $name = $vs['NickName'] ?? $vs['VSAddress'] ?? 'Unknown VS';
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'kemp_virtual_service',
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $index,
                'name' => $name,
                'address' => $vs['VSAddress'] ?? null,
                'port' => $vs['VSPort'] ?? null,
                'protocol' => $vs['Protocol'] ?? null,
                'status' => $vs['Status'] ?? 'Unknown',
                'enabled' => ($vs['Enable'] ?? 'Y') === 'Y',
                'total_connections' => $vs['TotalConns'] ?? 0,
                'active_connections' => $vs['ActiveConns'] ?? 0,
                'bytes_in' => $vs['BytesIn'] ?? 0,
                'bytes_out' => $vs['BytesOut'] ?? 0,
                'persist_type' => $vs['Persist'] ?? null,
                'schedule_method' => $vs['Schedule'] ?? null,
            ],
        ];
    }

    /**
     * Map Kemp Real Server to standardized format
     * 
     * @param array<string, mixed> $rs
     * @return array<string, mixed>
     */
    public static function mapRealServer(array $rs, ?int $integrationCustomerId = null): array
    {
        $index = $rs['RSIndex'] ?? $rs['rsindex'] ?? null;
        $address = $rs['Addr'] ?? $rs['Address'] ?? 'Unknown';
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'kemp_real_server',
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $index,
                'address' => $address,
                'port' => $rs['Port'] ?? null,
                'status' => $rs['Status'] ?? 'Unknown',
                'enabled' => ($rs['Enable'] ?? 'Y') === 'Y',
                'weight' => $rs['Weight'] ?? 1000,
                'active_connections' => $rs['ActiveConns'] ?? 0,
                'total_connections' => $rs['TotalConns'] ?? 0,
                'vs_index' => $rs['VSIndex'] ?? null,
            ],
        ];
    }

    /**
     * Map Kemp system stats to standardized format
     * 
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    public static function mapStats(array $stats, ?int $integrationCustomerId = null): array
    {
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'kemp_stats',
            'timestamp' => Carbon::now(),
            'payload' => [
                'cpu_user' => $stats['CPU']['User'] ?? $stats['cpuuser'] ?? null,
                'cpu_system' => $stats['CPU']['System'] ?? $stats['cpusystem'] ?? null,
                'cpu_idle' => $stats['CPU']['Idle'] ?? $stats['cpuidle'] ?? null,
                'memory_used' => $stats['Memory']['memused'] ?? $stats['memused'] ?? null,
                'memory_free' => $stats['Memory']['memfree'] ?? $stats['memfree'] ?? null,
                'total_connections' => $stats['Totals']['TotalConns'] ?? $stats['totalconns'] ?? 0,
                'connections_per_sec' => $stats['Totals']['ConnsPerSec'] ?? $stats['connspersec'] ?? 0,
                'bytes_in' => $stats['Totals']['BytesIn'] ?? $stats['bytesin'] ?? 0,
                'bytes_out' => $stats['Totals']['BytesOut'] ?? $stats['bytesout'] ?? 0,
                'packets_in' => $stats['Totals']['PktsIn'] ?? $stats['pktsin'] ?? 0,
                'packets_out' => $stats['Totals']['PktsOut'] ?? $stats['pktsout'] ?? 0,
            ],
        ];
    }

    /**
     * Map Kemp LoadMaster as a "customer" (single device integration)
     * 
     * @param array<string, mixed> $systemInfo
     * @return array<string, mixed>
     */
    public static function mapLoadMaster(array $systemInfo, string $baseUrl): array
    {
        $hostname = $systemInfo['hostname'] ?? $systemInfo['Hostname'] ?? parse_url($baseUrl, PHP_URL_HOST) ?? 'LoadMaster';
        $version = $systemInfo['version'] ?? $systemInfo['Version'] ?? 'Unknown';
        
        return [
            'external_id' => md5($baseUrl),
            'name' => $hostname,
            'active' => true,
            'metadata' => [
                'hostname' => $hostname,
                'version' => $version,
                'model' => $systemInfo['model'] ?? $systemInfo['Model'] ?? null,
                'serial' => $systemInfo['serialnumber'] ?? $systemInfo['SerialNumber'] ?? null,
                'ha_mode' => $systemInfo['hamode'] ?? $systemInfo['HAMode'] ?? null,
                'base_url' => $baseUrl,
            ],
        ];
    }
}
