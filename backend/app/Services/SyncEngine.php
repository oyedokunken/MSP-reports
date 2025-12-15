<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\SyncLog;
use App\Jobs\SyncIntegrationJob;
use Illuminate\Support\Facades\Log;

class SyncEngine
{
    public function triggerSync(Integration $integration, string $syncType = 'current_state', string $priority = 'default'): SyncLog
    {
        $syncLog = SyncLog::create([
            'integration_id' => $integration->integration_id,
            'sync_type' => $syncType,
            'status' => 'queued',
            'started_at' => now(),
        ]);

        SyncIntegrationJob::dispatch($integration, $syncLog)->onQueue($priority);

        return $syncLog;
    }

    public function evaluateScheduledSyncs(): array
    {
        $results = [
            'evaluated' => 0,
            'triggered' => 0,
            'skipped' => 0,
        ];

        $integrations = Integration::where('status', 'active')->get();

        foreach ($integrations as $integration) {
            $results['evaluated']++;

            
            if ($this->isSyncDue($integration)) {
                $this->triggerSync($integration);
                $results['triggered']++;
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    public function isSyncDue(Integration $integration): bool
    {
        $company = $integration->company;
        $autoSyncEnabled = $company->settings['integrations']['auto_sync_enabled'] ?? true;

        if (!$autoSyncEnabled) {
            return false;
        }
        
        $syncSchedule = $company->settings['integrations']['sync_schedule'] ?? '0 */4 * * *';

        if (!$integration->last_sync_at) {
            return true;
        }

        $hoursSinceLastSync = now()->diffInHours($integration->last_sync_at);
        $syncIntervalHours = $this->parseSyncInterval($syncSchedule);

        return $hoursSinceLastSync >= $syncIntervalHours;
    }

    private function parseSyncInterval(string $cronSchedule): int
    {
        if (preg_match('/\*\/(\d+)\s+\*/', $cronSchedule, $matches)) {
            
            return (int) $matches[1];
        }

        if (preg_match('/\*\/(\d+)/', $cronSchedule, $matches)) {
            
            return max(1, (int) ($matches[1] / 60));
        }
   
        return 4;
    }

    public function completeSyncLog(
        SyncLog $syncLog,
        string $status,
        int $recordsSynced = 0,
        int $recordsFailed = 0,
        array $errors = [],
        array $customersMapped = []
    ): void {
        $syncLog->update([
            'status' => $status,
            'completed_at' => now(),
            'records_synced' => $recordsSynced,
            'records_failed' => $recordsFailed,
            'error_messages' => $errors,
            'customers_mapped' => $customersMapped,
        ]);

        
        $integration = $syncLog->integration;
        $integration->last_sync_at = now();
        $integration->last_sync_status = $status;

        if ($status === 'failed' && !empty($errors)) {
            $integration->error_message = json_encode($errors);
        } else {
            $integration->error_message = null;
            // Reset status to active when sync succeeds
            if ($status === 'success' && $integration->status === 'error') {
                $integration->status = 'active';
            }
        }

        $integration->save();

        if ($status === 'failed') {
            $this->notifyFailure($integration, $syncLog);
        }
    }

    private function notifyFailure(Integration $integration, SyncLog $syncLog): void
    {
        $company = $integration->company;
        $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];

        if (empty($notificationEmails)) {
            return;
        }
        
        Log::warning('Integration sync failed', [
            'integration_id' => $integration->integration_id,
            'integration_type' => $integration->integration_type,
            'company_id' => $company->company_id,
            'sync_log_id' => $syncLog->sync_log_id,
            'errors' => $syncLog->error_messages,
        ]); 
    }
}
