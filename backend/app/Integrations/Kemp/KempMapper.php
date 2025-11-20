<?php

namespace App\Integrations\Kemp;

class KempMapper
{
    /**
     * Map Kemp data into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapStatus(array $status): array
    {
        return [
            // No real schema yet; filled in once Kemp API is defined
            'integration_customer_id' => null,
            'data_type' => 'kemp_status',
            'timestamp' => now(),
            'payload' => $status,
        ];
    }
}
