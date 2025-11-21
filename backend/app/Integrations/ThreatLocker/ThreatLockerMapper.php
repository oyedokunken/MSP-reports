<?php

namespace App\Integrations\ThreatLocker;

class ThreatLockerMapper
{
    /**
     * Map ThreatLocker policy data into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapPolicy(array $policy): array
    {
        return [
            // No real schema yet; filled in once ThreatLocker API is defined
            'integration_customer_id' => null,
            'data_type' => 'threatlocker_policy',
            'timestamp' => now(),
            'payload' => $policy,
        ];
    }

    /**
     * Map ThreatLocker security event into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapSecurityEvent(array $event): array
    {
        return [
            // No real schema yet; filled in once ThreatLocker API is defined
            'integration_customer_id' => null,
            'data_type' => 'threatlocker_security_event',
            'timestamp' => now(),
            'payload' => $event,
        ];
    }
}
