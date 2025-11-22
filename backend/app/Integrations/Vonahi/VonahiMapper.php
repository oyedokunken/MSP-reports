<?php

namespace App\Integrations\Vonahi;

class VonahiMapper
{
    /**
     * Map Vonahi security findings into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapSecurityFinding(array $finding): array
    {
        return [
            // No real schema yet; filled in once Vonahi API is defined
            'integration_customer_id' => null,
            'data_type' => 'vonahi_security_finding',
            'timestamp' => now(),
            'payload' => $finding,
        ];
    }
}
