<?php

namespace App\Integrations\SaaS;

class SaaSMapper
{
    /**
     * Map SaaS application usage into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapApplicationUsage(array $usage): array
    {
        return [
            // No real schema yet; filled in once SaaS API is defined
            'integration_customer_id' => null,
            'data_type' => 'saas_application_usage',
            'timestamp' => now(),
            'payload' => $usage,
        ];
    }

    /**
     * Map SaaS license management into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapLicenseManagement(array $license): array
    {
        return [
            // No real schema yet; filled in once SaaS API is defined
            'integration_customer_id' => null,
            'data_type' => 'saas_license_management',
            'timestamp' => now(),
            'payload' => $license,
        ];
    }
}
