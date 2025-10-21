<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\SyncLog;
use App\Jobs\SyncIntegrationJob;
use Illuminate\Support\Facades\Log;

class SyncEngine
{
    /**
     * Trigger a sync for a specific integration.
     * 
     * @param Integration $integration
     * @param string $syncType
     * @return SyncLog
     */
    public function triggerSync(Integration $integration, string $syncType = 'current_state'): SyncLog
    {
        // Create sync log
        $syncLog = SyncLog::create([
            'integration_id' => $integration->integration_id,
            'sync_type' => $syncType,
            'status' => 'queued',
            'started_at' => now(),
        ]);

        // Dispatch sync job
        SyncIntegrationJob::dispatch($integration, $syncLog);

        return $syncLog;
    }

    /**
     * Evaluate all integrations and trigger syncs for those that are due.
     * This is called by a scheduled command.
     * 
     * @return array
     */
    public function evaluateScheduledSyncs(): array
    {
        $results = [
            'evaluated' => 0,
            'triggered' => 0,
            'skipped' => 0,
        ];

        // Get all active integrations
        $integrations = Integration::where('status', 'active')->get();

        foreach ($integrations as $integration) {
            $results['evaluated']++;

            // Check if sync is due
            if ($this->isSyncDue($integration)) {
                $this->triggerSync($integration);
                $results['triggered']++;
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    /**
     * Check if a sync is due for an integration.
     * 
     * @param Integration $integration
     * @return bool
     */
    public function isSyncDue(Integration $integration): bool
    {
        $company = $integration->company;
        $autoSyncEnabled = $company->settings['integrations']['auto_sync_enabled'] ?? true;

        if (!$autoSyncEnabled) {
            return false;
        }

        // Get sync schedule from company settings (cron format)
        $syncSchedule = $company->settings['integrations']['sync_schedule'] ?? '0 */4 * * *';

        // Parse cron and check if current time matches
        // For simplicity, we'll check if last sync was more than 4 hours ago
        if (!$integration->last_sync_at) {
            return true;
        }

        $hoursSinceLastSync = now()->diffInHours($integration->last_sync_at);
        $syncIntervalHours = $this->parseSyncInterval($syncSchedule);

        return $hoursSinceLastSync >= $syncIntervalHours;
    }

    /**
     * Parse sync interval from cron schedule.
     * 
     * @param string $cronSchedule
     * @return int Hours between syncs
     */
    private function parseSyncInterval(string $cronSchedule): int
    {
        // Simple parser for common patterns
        // "0 */4 * * *" = every 4 hours
        // "0 * * * *" = every hour
        // "*/30 * * * *" = every 30 minutes (0.5 hours)

        if (preg_match('/\*\/(\d+)\s+\*/', $cronSchedule, $matches)) {
            // Every X hours pattern
            return (int) $matches[1];
        }

        if (preg_match('/\*\/(\d+)/', $cronSchedule, $matches)) {
            // Every X minutes pattern
            return max(1, (int) ($matches[1] / 60));
        }

        // Default to 4 hours
        return 4;
    }

    /**
     * Handle sync completion.
     * 
     * @param SyncLog $syncLog
     * @param string $status
     * @param int $recordsSynced
     * @param int $recordsFailed
     * @param array $errors
     * @return void
     */
    public function completeSyncLog(
        SyncLog $syncLog,
        string $status,
        int $recordsSynced = 0,
        int $recordsFailed = 0,
        array $errors = []
    ): void {
        $syncLog->update([
            'status' => $status,
            'completed_at' => now(),
            'records_synced' => $recordsSynced,
            'records_failed' => $recordsFailed,
            'error_messages' => $errors,
        ]);

        // Update integration status
        $integration = $syncLog->integration;
        $integration->last_sync_at = now();
        $integration->last_sync_status = $status;

        if ($status === 'failed' && !empty($errors)) {
            $integration->error_message = json_encode($errors);
        } else {
            $integration->error_message = null;
        }

        $integration->save();

        // Send notifications if sync failed
        if ($status === 'failed') {
            $this->notifyFailure($integration, $syncLog);
        }
    }

    /**
     * Notify company admins of sync failure.
     * 
     * @param Integration $integration
     * @param SyncLog $syncLog
     * @return void
     */
    private function notifyFailure(Integration $integration, SyncLog $syncLog): void
    {
        $company = $integration->company;
        $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];

        if (empty($notificationEmails)) {
            return;
        }

        // Log for now - actual email sending would be implemented here
        Log::warning('Integration sync failed', [
            'integration_id' => $integration->integration_id,
            'integration_type' => $integration->integration_type,
            'company_id' => $company->company_id,
            'sync_log_id' => $syncLog->sync_log_id,
            'errors' => $syncLog->error_messages,
        ]);

        // TODO: Send actual email notifications
        // Mail::to($notificationEmails)->send(new SyncFailureNotification($integration, $syncLog));
    }
}
