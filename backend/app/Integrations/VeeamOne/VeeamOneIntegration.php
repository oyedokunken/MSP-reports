<?php

namespace App\Integrations\VeeamOne;

use App\Integrations\Contracts\IntegrationInterface;
use App\Integrations\Contracts\IntegrationDataType;
use App\Integrations\Traits\HasDefaultPreview;
use App\Models\Integration;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Veeam ONE Reporting Server Integration
 *
 * Syncs backup job status, alarms, protected workloads, and repository info
 * from the Veeam ONE REST API.
 */
class VeeamOneIntegration implements IntegrationInterface
{
    use HasDefaultPreview;

    protected Integration $integration;
    protected VeeamOneClient $client;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $rateLimiter = app(RateLimiter::class);
        $this->client = new VeeamOneClient(
            $integration->credentials ?? [],
            $rateLimiter,
            $integration
        );
    }

    public function authenticate(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(): array
    {
        $stats = [
            'records_synced' => 0,
            'records_new' => 0,
            'customers_mapped' => [],
            'records_failed' => 0,
            'errors' => [],
        ];

        try {
            Log::info('VeeamOne: Starting sync', [
                'integration_id' => $this->integration->integration_id,
            ]);

            // 1. Sync backup jobs
            $jobsResult = $this->syncBackupJobs();
            $stats['records_synced'] += $jobsResult['synced'];
            $stats['records_new'] += $jobsResult['new'];
            if (!empty($jobsResult['error'])) {
                $stats['errors'][] = $jobsResult['error'];
            }

            // 2. Sync backup sessions (recent)
            $sessionsResult = $this->syncBackupSessions();
            $stats['records_synced'] += $sessionsResult['synced'];
            $stats['records_new'] += $sessionsResult['new'];
            if (!empty($sessionsResult['error'])) {
                $stats['errors'][] = $sessionsResult['error'];
            }

            // 3. Sync alarms
            $alarmsResult = $this->syncAlarms();
            $stats['records_synced'] += $alarmsResult['synced'];
            $stats['records_new'] += $alarmsResult['new'];
            if (!empty($alarmsResult['error'])) {
                $stats['errors'][] = $alarmsResult['error'];
            }

            // 4. Sync repositories
            $reposResult = $this->syncRepositories();
            $stats['records_synced'] += $reposResult['synced'];
            $stats['records_new'] += $reposResult['new'];
            if (!empty($reposResult['error'])) {
                $stats['errors'][] = $reposResult['error'];
            }

            // 5. Sync protected workloads
            $workloadsResult = $this->syncProtectedWorkloads();
            $stats['records_synced'] += $workloadsResult['synced'];
            $stats['records_new'] += $workloadsResult['new'];
            if (!empty($workloadsResult['error'])) {
                $stats['errors'][] = $workloadsResult['error'];
            }

            Log::info('VeeamOne: Sync completed', [
                'integration_id' => $this->integration->integration_id,
                'stats' => $stats,
            ]);

        } catch (\Throwable $e) {
            Log::error('VeeamOne: Sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $stats['errors'][] = 'Sync failed: ' . $e->getMessage();
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncBackupJobs(): array
    {
        $result = ['synced' => 0, 'new' => 0, 'error' => null];

        try {
            $jobs = $this->client->getBackupJobs();

            foreach ($jobs as $job) {
                $mapped = VeeamOneMapper::mapBackupJob($job);
                $mapped['integration_id'] = $this->integration->integration_id;

                $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                    ->where('data_type', IntegrationDataType::VEEAM_BACKUP_JOB)
                    ->where('external_id', $mapped['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'payload' => $mapped['payload'],
                        'timestamp' => $mapped['timestamp'],
                    ]);
                    $result['synced']++;
                } else {
                    IntegrationData::create($mapped);
                    $result['new']++;
                    $result['synced']++;
                }
            }

            Log::info('VeeamOne: Backup jobs synced', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($jobs),
            ]);

        } catch (\Throwable $e) {
            $result['error'] = 'Backup jobs sync failed: ' . $e->getMessage();
            Log::error('VeeamOne: Backup jobs sync error', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncBackupSessions(): array
    {
        $result = ['synced' => 0, 'new' => 0, 'error' => null];

        try {
            // Get sessions from last 24 hours
            $sessions = $this->client->getBackupSessions([
                'startTime' => Carbon::now()->subDay()->toIso8601String(),
            ]);

            foreach ($sessions as $session) {
                $mapped = VeeamOneMapper::mapBackupSession($session);
                $mapped['integration_id'] = $this->integration->integration_id;

                $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                    ->where('data_type', IntegrationDataType::VEEAM_BACKUP_SESSION)
                    ->where('external_id', $mapped['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'payload' => $mapped['payload'],
                        'timestamp' => $mapped['timestamp'],
                    ]);
                    $result['synced']++;
                } else {
                    IntegrationData::create($mapped);
                    $result['new']++;
                    $result['synced']++;
                }
            }

            Log::info('VeeamOne: Backup sessions synced', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($sessions),
            ]);

        } catch (\Throwable $e) {
            $result['error'] = 'Backup sessions sync failed: ' . $e->getMessage();
            Log::error('VeeamOne: Backup sessions sync error', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncAlarms(): array
    {
        $result = ['synced' => 0, 'new' => 0, 'error' => null];

        try {
            $alarms = $this->client->getAlarms();

            foreach ($alarms as $alarm) {
                $mapped = VeeamOneMapper::mapAlarm($alarm);
                $mapped['integration_id'] = $this->integration->integration_id;

                $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                    ->where('data_type', IntegrationDataType::VEEAM_ALARM)
                    ->where('external_id', $mapped['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'payload' => $mapped['payload'],
                        'timestamp' => $mapped['timestamp'],
                    ]);
                    $result['synced']++;
                } else {
                    IntegrationData::create($mapped);
                    $result['new']++;
                    $result['synced']++;
                }
            }

            Log::info('VeeamOne: Alarms synced', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($alarms),
            ]);

        } catch (\Throwable $e) {
            $result['error'] = 'Alarms sync failed: ' . $e->getMessage();
            Log::error('VeeamOne: Alarms sync error', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncRepositories(): array
    {
        $result = ['synced' => 0, 'new' => 0, 'error' => null];

        try {
            $repos = $this->client->getRepositories();

            foreach ($repos as $repo) {
                $mapped = VeeamOneMapper::mapRepository($repo);
                $mapped['integration_id'] = $this->integration->integration_id;

                $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                    ->where('data_type', IntegrationDataType::VEEAM_REPOSITORY)
                    ->where('external_id', $mapped['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'payload' => $mapped['payload'],
                        'timestamp' => $mapped['timestamp'],
                    ]);
                    $result['synced']++;
                } else {
                    IntegrationData::create($mapped);
                    $result['new']++;
                    $result['synced']++;
                }
            }

            Log::info('VeeamOne: Repositories synced', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($repos),
            ]);

        } catch (\Throwable $e) {
            $result['error'] = 'Repositories sync failed: ' . $e->getMessage();
            Log::error('VeeamOne: Repositories sync error', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncProtectedWorkloads(): array
    {
        $result = ['synced' => 0, 'new' => 0, 'error' => null];

        try {
            $workloads = $this->client->getProtectedWorkloads();

            foreach ($workloads as $workload) {
                $mapped = VeeamOneMapper::mapProtectedWorkload($workload);
                $mapped['integration_id'] = $this->integration->integration_id;

                $existing = IntegrationData::where('integration_id', $this->integration->integration_id)
                    ->where('data_type', IntegrationDataType::VEEAM_PROTECTED_WORKLOAD)
                    ->where('external_id', $mapped['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'payload' => $mapped['payload'],
                        'timestamp' => $mapped['timestamp'],
                    ]);
                    $result['synced']++;
                } else {
                    IntegrationData::create($mapped);
                    $result['new']++;
                    $result['synced']++;
                }
            }

            Log::info('VeeamOne: Protected workloads synced', [
                'integration_id' => $this->integration->integration_id,
                'count' => count($workloads),
            ]);

        } catch (\Throwable $e) {
            $result['error'] = 'Protected workloads sync failed: ' . $e->getMessage();
            Log::error('VeeamOne: Protected workloads sync error', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array
    {
        // Veeam ONE doesn't have a traditional customer concept
        // Could return backup servers or companies from jobs in the future
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $sessions = $this->client->getBackupSessions([
                'startTime' => $startDate->toIso8601String(),
                'endTime' => $endDate->toIso8601String(),
            ]);

            return [
                'data' => $sessions,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            return [
                'data' => [],
                'errors' => ['Historical data fetch failed: ' . $e->getMessage()],
            ];
        }
    }

    public function getAvailableHistoryRange(): ?Carbon
    {
        // Veeam ONE typically retains data for 1 year
        return Carbon::now()->subYear();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Veeam ONE',
            'description' => 'Veeam ONE Reporting Server integration for backup monitoring and reporting',
            'data_types' => [
                IntegrationDataType::VEEAM_BACKUP_JOB,
                IntegrationDataType::VEEAM_BACKUP_SESSION,
                IntegrationDataType::VEEAM_ALARM,
                IntegrationDataType::VEEAM_REPOSITORY,
                IntegrationDataType::VEEAM_PROTECTED_WORKLOAD,
            ],
            'supports_historical' => true,
            'historical_range_days' => 365,
        ];
    }

    public function disconnect(): void
    {
        $this->client->clearTokenCache();

        Log::info('VeeamOne: Disconnected', [
            'integration_id' => $this->integration->integration_id,
        ]);
    }

    public function healthCheck(): bool
    {
        return $this->authenticate();
    }

    /**
     * @return array<string, int>
     */
    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ];
    }

    /**
     * Get dashboard widget data for reporting
     * 
     * @return array<string, mixed>
     */
    public function getDashboardData(int $dashboardId, int $widgetId, int $datasourceId): array
    {
        return $this->client->getWidgetData($dashboardId, $widgetId, $datasourceId);
    }

    /**
     * Get server information
     * 
     * @return array<string, mixed>
     */
    public function getServerInfo(): array
    {
        return $this->client->getAbout();
    }

    /**
     * Get license information
     * 
     * @return array<string, mixed>
     */
    public function getLicenseInfo(): array
    {
        return $this->client->getLicense();
    }
}
