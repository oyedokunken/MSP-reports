<?php

namespace App\Integrations\VeeamOne;

use App\Integrations\Contracts\IntegrationDataType;
use Carbon\Carbon;

/**
 * Maps Veeam ONE API responses to standardized IntegrationData payloads
 */
class VeeamOneMapper
{
    /**
     * Map backup job to IntegrationData format
     */
    public static function mapBackupJob(array $job): array
    {
        $id = $job['id'] ?? $job['uid'] ?? $job['jobId'] ?? null;
        $name = $job['name'] ?? $job['jobName'] ?? 'Unknown Job';

        return [
            'external_id' => (string) $id,
            'data_type' => IntegrationDataType::VEEAM_BACKUP_JOB,
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'name' => $name,
                'description' => $job['description'] ?? null,
                'type' => $job['type'] ?? $job['jobType'] ?? null,
                'status' => $job['status'] ?? $job['lastResult'] ?? null,
                'last_run' => $job['lastRun'] ?? $job['lastRunTime'] ?? null,
                'next_run' => $job['nextRun'] ?? $job['nextRunTime'] ?? null,
                'target' => $job['target'] ?? null,
                'repository' => $job['repository'] ?? $job['repositoryName'] ?? null,
                'schedule' => $job['schedule'] ?? null,
                'is_enabled' => $job['isEnabled'] ?? $job['enabled'] ?? true,
                'vm_count' => $job['vmCount'] ?? $job['objectsCount'] ?? null,
                '_raw' => $job,
            ],
        ];
    }

    /**
     * Map backup session to IntegrationData format
     */
    public static function mapBackupSession(array $session): array
    {
        $id = $session['id'] ?? $session['sessionId'] ?? $session['uid'] ?? null;
        $jobName = $session['jobName'] ?? $session['name'] ?? 'Unknown Session';

        $startTime = $session['startTime'] ?? $session['creationTime'] ?? null;
        $endTime = $session['endTime'] ?? $session['finishTime'] ?? null;

        return [
            'external_id' => (string) $id,
            'data_type' => IntegrationDataType::VEEAM_BACKUP_SESSION,
            'timestamp' => $startTime ? Carbon::parse($startTime) : Carbon::now(),
            'payload' => [
                'id' => $id,
                'job_id' => $session['jobId'] ?? $session['jobUid'] ?? null,
                'job_name' => $jobName,
                'status' => $session['status'] ?? $session['result'] ?? null,
                'result' => $session['result'] ?? $session['status'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_seconds' => self::calculateDuration($startTime, $endTime),
                'progress' => $session['progress'] ?? 100,
                'processed_size' => $session['processedSize'] ?? $session['totalSize'] ?? null,
                'transferred_size' => $session['transferredSize'] ?? $session['dataSize'] ?? null,
                'bottleneck' => $session['bottleneck'] ?? null,
                'message' => $session['message'] ?? $session['description'] ?? null,
                'warnings_count' => $session['warningsCount'] ?? 0,
                'errors_count' => $session['errorsCount'] ?? 0,
                '_raw' => $session,
            ],
        ];
    }

    /**
     * Map alarm to IntegrationData format
     */
    public static function mapAlarm(array $alarm): array
    {
        $id = $alarm['id'] ?? $alarm['alarmId'] ?? $alarm['uid'] ?? null;

        $triggeredAt = $alarm['triggeredTime'] ?? $alarm['time'] ?? $alarm['created'] ?? null;

        return [
            'external_id' => (string) $id,
            'data_type' => IntegrationDataType::VEEAM_ALARM,
            'timestamp' => $triggeredAt ? Carbon::parse($triggeredAt) : Carbon::now(),
            'payload' => [
                'id' => $id,
                'name' => $alarm['name'] ?? $alarm['alarmName'] ?? 'Unknown Alarm',
                'description' => $alarm['description'] ?? $alarm['message'] ?? null,
                'status' => $alarm['status'] ?? $alarm['state'] ?? 'triggered',
                'severity' => $alarm['severity'] ?? $alarm['level'] ?? 'warning',
                'triggered_time' => $triggeredAt,
                'resolved_time' => $alarm['resolvedTime'] ?? null,
                'object_type' => $alarm['objectType'] ?? $alarm['entityType'] ?? null,
                'object_name' => $alarm['objectName'] ?? $alarm['entityName'] ?? null,
                'object_id' => $alarm['objectId'] ?? $alarm['entityId'] ?? null,
                'acknowledged' => $alarm['acknowledged'] ?? false,
                'acknowledged_by' => $alarm['acknowledgedBy'] ?? null,
                '_raw' => $alarm,
            ],
        ];
    }

    /**
     * Map repository to IntegrationData format
     */
    public static function mapRepository(array $repo): array
    {
        $id = $repo['id'] ?? $repo['repositoryId'] ?? $repo['uid'] ?? null;
        $name = $repo['name'] ?? 'Unknown Repository';

        return [
            'external_id' => (string) $id,
            'data_type' => IntegrationDataType::VEEAM_REPOSITORY,
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'name' => $name,
                'description' => $repo['description'] ?? null,
                'type' => $repo['type'] ?? $repo['repositoryType'] ?? null,
                'path' => $repo['path'] ?? $repo['location'] ?? null,
                'host' => $repo['hostName'] ?? $repo['host'] ?? null,
                'capacity_bytes' => $repo['capacity'] ?? $repo['totalSpace'] ?? null,
                'free_space_bytes' => $repo['freeSpace'] ?? $repo['availableSpace'] ?? null,
                'used_space_bytes' => $repo['usedSpace'] ?? null,
                'usage_percentage' => self::calculateUsagePercentage($repo),
                'status' => $repo['status'] ?? 'available',
                'backups_count' => $repo['backupsCount'] ?? null,
                '_raw' => $repo,
            ],
        ];
    }

    /**
     * Map protected workload (VM) to IntegrationData format
     */
    public static function mapProtectedWorkload(array $workload): array
    {
        $id = $workload['id'] ?? $workload['vmId'] ?? $workload['uid'] ?? null;
        $name = $workload['name'] ?? $workload['vmName'] ?? 'Unknown VM';

        return [
            'external_id' => (string) $id,
            'data_type' => IntegrationDataType::VEEAM_PROTECTED_WORKLOAD,
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'name' => $name,
                'type' => $workload['type'] ?? $workload['vmType'] ?? 'vm',
                'platform' => $workload['platform'] ?? $workload['hypervisor'] ?? null,
                'status' => $workload['status'] ?? $workload['protectionStatus'] ?? null,
                'protection_status' => $workload['protectionStatus'] ?? $workload['status'] ?? null,
                'last_backup' => $workload['lastBackup'] ?? $workload['lastBackupTime'] ?? null,
                'last_restore_point' => $workload['lastRestorePoint'] ?? null,
                'restore_points_count' => $workload['restorePointsCount'] ?? $workload['rpCount'] ?? null,
                'total_size' => $workload['totalSize'] ?? $workload['provisionedSize'] ?? null,
                'used_size' => $workload['usedSize'] ?? null,
                'host' => $workload['hostName'] ?? $workload['host'] ?? null,
                'cluster' => $workload['clusterName'] ?? $workload['cluster'] ?? null,
                'job_name' => $workload['jobName'] ?? null,
                '_raw' => $workload,
            ],
        ];
    }

    /**
     * Calculate duration in seconds from start and end times
     */
    protected static function calculateDuration(?string $startTime, ?string $endTime): ?int
    {
        if (empty($startTime) || empty($endTime)) {
            return null;
        }

        try {
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);
            return $end->diffInSeconds($start);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Calculate storage usage percentage
     */
    protected static function calculateUsagePercentage(array $repo): ?float
    {
        $total = $repo['capacity'] ?? $repo['totalSpace'] ?? null;
        $used = $repo['usedSpace'] ?? null;

        if (empty($total) || $total <= 0) {
            return null;
        }

        if ($used === null) {
            $free = $repo['freeSpace'] ?? $repo['availableSpace'] ?? null;
            if ($free !== null) {
                $used = $total - $free;
            }
        }

        if ($used === null) {
            return null;
        }

        return round(($used / $total) * 100, 2);
    }
}
