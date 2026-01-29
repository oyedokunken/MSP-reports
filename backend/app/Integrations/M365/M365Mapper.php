<?php

declare(strict_types=1);

namespace App\Integrations\M365;

final class M365Mapper
{
    /**
     * Map M365 audit log into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    public static function mapAuditLog(array $log): array
    {
        return [
            // No real schema yet; filled in once M365 API is defined
            'integration_customer_id' => null,
            'data_type' => 'm365_audit_log',
            'timestamp' => now(),
            'payload' => $log,
        ];
    }

    /**
     * Map M365 security alert into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $alert
     * @return array<string, mixed>
     */
    public static function mapSecurityAlert(array $alert): array
    {
        return [
            // No real schema yet; filled in once M365 API is defined
            'integration_customer_id' => null,
            'data_type' => 'm365_security_alert',
            'timestamp' => now(),
            'payload' => $alert,
        ];
    }
}
