<?php

namespace App\Integrations\AdminDroid;

class AdminDroidMapper
{
    /**
     * Map AdminDroid M365 report into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapM365Report(array $report): array
    {
        return [
            // No real schema yet; filled in once AdminDroid API is defined
            'integration_customer_id' => null,
            'data_type' => 'admindroid_m365_report',
            'timestamp' => now(),
            'payload' => $report,
        ];
    }

    /**
     * Map AdminDroid user activity into a standardized IntegrationData payload.
     * Currently placeholder only.
     */
    public static function mapUserActivity(array $activity): array
    {
        return [
            // No real schema yet; filled in once AdminDroid API is defined
            'integration_customer_id' => null,
            'data_type' => 'admindroid_user_activity',
            'timestamp' => now(),
            'payload' => $activity,
        ];
    }
}
