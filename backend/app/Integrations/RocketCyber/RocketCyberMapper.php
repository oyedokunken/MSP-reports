<?php

namespace App\Integrations\RocketCyber;

class RocketCyberMapper
{
    /**
     * Map RocketCyber security incident into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapSecurityIncident(array $incident): array
    {
        return [
            // No real schema yet; filled in once RocketCyber API is defined
            'integration_customer_id' => null,
            'data_type' => 'rocketcyber_security_incident',
            'timestamp' => now(),
            'payload' => $incident,
        ];
    }

    /**
     * Map RocketCyber threat detection into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapThreatDetection(array $threat): array
    {
        return [
            // No real schema yet; filled in once RocketCyber API is defined
            'integration_customer_id' => null,
            'data_type' => 'rocketcyber_threat_detection',
            'timestamp' => now(),
            'payload' => $threat,
        ];
    }
}
