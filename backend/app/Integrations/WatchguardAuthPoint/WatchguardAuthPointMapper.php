<?php

namespace App\Integrations\WatchguardAuthPoint;

class WatchguardAuthPointMapper
{
    /**
     * Map WatchGuard AuthPoint authentication event into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapAuthenticationEvent(array $event): array
    {
        return [
            // No real schema yet; filled in once WatchGuard AuthPoint API is defined
            'integration_customer_id' => null,
            'data_type' => 'watchguardauthpoint_authentication_event',
            'timestamp' => now(),
            'payload' => $event,
        ];
    }

    /**
     * Map WatchGuard AuthPoint user enrollment into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapUserEnrollment(array $enrollment): array
    {
        return [
            // No real schema yet; filled in once WatchGuard AuthPoint API is defined
            'integration_customer_id' => null,
            'data_type' => 'watchguardauthpoint_user_enrollment',
            'timestamp' => now(),
            'payload' => $enrollment,
        ];
    }
}
