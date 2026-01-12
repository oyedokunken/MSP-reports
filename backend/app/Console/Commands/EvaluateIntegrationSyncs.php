<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Services\SyncEngine;
use Carbon\Carbon;

class EvaluateIntegrationSyncs extends Command
{
    protected $signature = 'integrations:evaluate-syncs';
    protected $description = 'Evaluate integration sync schedules and queue syncs that are due';

    public function handle(SyncEngine $syncEngine)
    {
        $this->info('Evaluating integration sync schedules...');

        $now = Carbon::now();
        $integrationsProcessed = 0;
        $syncsQueued = 0;

        $integrations = Integration::where('status', 'active')
            ->orWhere('status', 'importing_historical')
            ->get();

        foreach ($integrations as $integration) {
            $company = $integration->company;
            
            $autoSyncEnabled = $company->settings['integrations']['auto_sync_enabled'] ?? true;
            
            if (!$autoSyncEnabled) {
                $this->info("Auto-sync disabled for company {$company->company_id}");
                continue;
            }

            // Use integration-specific schedule, or type-based default
            $syncSchedule = $integration->sync_schedule ?? $this->getDefaultSchedule($integration->integration_type);
            
            // Mark syncs stuck in 'running' for > 2 hours as failed (hard timeout)
            $staleRunningThreshold = $now->copy()->subHours(2);
            $staleRunningCount = \App\Models\SyncLog::where('integration_id', $integration->integration_id)
                ->where('status', 'running')
                ->where('started_at', '<', $staleRunningThreshold)
                ->update([
                    'status' => 'failed',
                    'completed_at' => $now,
                    'error_messages' => ['Sync marked as failed due to timeout (exceeded 2 hours)']
                ]);

            // Heartbeat Check: Mark syncs as failed if they haven't pulsed for 10 minutes (crashed worker)
            $heartbeatThreshold = $now->copy()->subMinutes(10);
            $crashedCount = \App\Models\SyncLog::where('integration_id', $integration->integration_id)
                ->where('status', 'running')
                ->whereNotNull('last_heartbeat_at')
                ->where('last_heartbeat_at', '<', $heartbeatThreshold)
                ->update([
                    'status' => 'failed',
                    'completed_at' => $now,
                    'error_messages' => ['Sync marked as failed due to inactivity (worker potentially crashed)']
                ]);

            if ($crashedCount > 0) {
                 $this->warn("Marked {$crashedCount} crashed sync(s) as failed for integration {$integration->integration_id}");
            }
            
            
            if ($staleRunningCount > 0) {
                $this->warn("Marked {$staleRunningCount} stale running sync(s) as failed for integration {$integration->integration_id}");
            }
            
            // Mark syncs stuck in 'queued' for > 10 minutes as failed (workers should pick up jobs within seconds)
            // This handles cases where job dispatch fails or worker crashes before processing
            $staleQueuedThreshold = $now->copy()->subMinutes(10);
            $staleQueuedCount = \App\Models\SyncLog::where('integration_id', $integration->integration_id)
                ->where('status', 'queued')
                ->where('started_at', '<', $staleQueuedThreshold)
                ->update([
                    'status' => 'failed',
                    'completed_at' => $now,
                    'error_messages' => ['Sync marked as failed - stuck in queued state (orphaned job)']
                ]);
            
            if ($staleQueuedCount > 0) {
                $this->warn("Marked {$staleQueuedCount} stale queued sync(s) as failed for integration {$integration->integration_id}");
            }
            
            // Check if there's already a sync queued or running for this integration
            $existingSync = \App\Models\SyncLog::where('integration_id', $integration->integration_id)
                ->whereIn('status', ['queued', 'running'])
                ->exists();
            
            if ($existingSync) {
                // If it's been running for > 15 minutes, log a warning but don't force fail it here 
                // (cleanup-stale handles widely stuck ones, but we simply want to avoid overlap)
                $this->info("Skipping integration {$integration->integration_id} ({$integration->integration_type}) - sync already queued/running");
                continue;
            }
            
            if ($this->isSyncDue($integration, $syncSchedule, $now)) {
                try {
                    // $syncLog = $syncEngine->queueSync($integration);
                    $syncLog = $syncEngine->triggerSync($integration, 'current_state', 'default');
                    $syncsQueued++;
                    
                    $this->info("Queued sync for integration {$integration->integration_id} ({$integration->integration_type})");
                } catch (\Exception $e) {
                    $this->error("Failed to queue sync for integration {$integration->integration_id}: {$e->getMessage()}");
                }
            }

            $integrationsProcessed++;
        }

        $this->info("Processed {$integrationsProcessed} integrations, queued {$syncsQueued} syncs");

        return 0;
    }

    private function isSyncDue(Integration $integration, string $cronSchedule, Carbon $now): bool
    {
        if (!$integration->last_sync_at) {
            return true;
        }

        $intervalMinutes = $this->parseCronInterval($cronSchedule);
        
        if ($intervalMinutes === null) {
            $intervalMinutes = 240;
        }

        $lastSync = Carbon::parse($integration->last_sync_at);
        $nextSyncDue = $lastSync->copy()->addMinutes($intervalMinutes);

        return $now->gte($nextSyncDue);
    }

    private function parseCronInterval(string $cronSchedule): ?int
    {
        // Parse minute-based intervals (e.g., */5 * * * * = every 5 minutes)
        if (preg_match('/^\*\/(\d+)\s+\*/', $cronSchedule, $matches)) {
            return (int)$matches[1];
        }

        // Parse hour-based intervals and convert to minutes
        if (preg_match('/\*\/(\d+)/', $cronSchedule, $matches)) {
            return (int)$matches[1] * 60;
        }

        $patterns = [
            '0 */1 * * *' => 60,    // Hourly
            '0 */2 * * *' => 120,   // Every 2 hours
            '0 */4 * * *' => 240,   // Every 4 hours
            '0 */6 * * *' => 360,   // Every 6 hours
            '0 */12 * * *' => 720,  // Every 12 hours
            '0 0 * * *' => 1440,    // Daily (24 hours)
        ];

        return $patterns[$cronSchedule] ?? null;
    }

    /**
     * Get default sync schedule based on integration type
     */
    protected function getDefaultSchedule(string $integrationType): string
    {
        return match($integrationType) {
            'Datto' => '*/5 * * * *',      // Every 5 minutes (monitoring)
            'Autotask' => '*/10 * * * *',   // Every 10 minutes (companies + tickets + time)
            'ITGlue' => '0 */6 * * *',     // Every 6 hours (documentation)
            'LibreNMS' => '*/10 * * * *',  // Every 10 minutes (network monitoring)
            'SaaS' => '0 */6 * * *',       // Every 6 hours (security monitoring)
            default => '0 */4 * * *',      // Every 4 hours (fallback)
        };
    }
}
