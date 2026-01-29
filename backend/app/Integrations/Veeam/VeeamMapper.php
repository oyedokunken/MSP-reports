<?php

declare(strict_types=1);

namespace App\Integrations\Veeam;

final class VeeamMapper
{
    /**
     * Map Veeam backup job into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    public static function mapBackupJob(array $job): array
    {
        return [
            // No real schema yet; filled in once Veeam API is defined
            'integration_customer_id' => null,
            'data_type' => 'veeam_backup_job',
            'timestamp' => now(),
            'payload' => $job,
        ];
    }

    /**
     * Map Veeam backup session into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public static function mapBackupSession(array $session): array
    {
        return [
            // No real schema yet; filled in once Veeam API is defined
            'integration_customer_id' => null,
            'data_type' => 'veeam_backup_session',
            'timestamp' => now(),
            'payload' => $session,
        ];
    }

    /**
     * Map Veeam protected VM into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $vm
     * @return array<string, mixed>
     */
    public static function mapProtectedVM(array $vm): array
    {
        return [
            // No real schema yet; filled in once Veeam API is defined
            'integration_customer_id' => null,
            'data_type' => 'veeam_protected_vm',
            'timestamp' => now(),
            'payload' => $vm,
        ];
    }
}
