<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\SyncLog;
use Carbon\Carbon;

class CleanupStaleSyncs extends Command
{
    protected $signature = 'syncs:cleanup-stale';
    protected $description = 'Mark stale syncs (running/queued too long) as failed and fix stuck integrations';

    public function handle(): int
    {
        $cleaned = 0;

        // 1. Running syncs older than 2 hours
        $staleRunning = SyncLog::where('status', 'running')
            ->where('started_at', '<', now()->subHours(2))
            ->get();

        foreach ($staleRunning as $sync) {
            $sync->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_messages' => ['Automatically marked as failed due to timeout (exceeded 2 hours)'],
            ]);
            $this->warn("Marked running sync {$sync->sync_id} (integration {$sync->integration_id}) as failed");
            $cleaned++;
        }

        // 2. Queued syncs older than 1 hour (job never picked up)
        $staleQueued = SyncLog::where('status', 'queued')
            ->where('started_at', '<', now()->subHour())
            ->get();

        foreach ($staleQueued as $sync) {
            $sync->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_messages' => ['Automatically marked as failed - job was never picked up from queue'],
            ]);
            $this->warn("Marked queued sync {$sync->sync_id} (integration {$sync->integration_id}) as failed");
            $cleaned++;
        }

        // 3. Fix integrations stuck in importing_historical for > 4 hours
        $stuckImporting = Integration::where('status', 'importing_historical')
            ->where('updated_at', '<', now()->subHours(4))
            ->get();

        foreach ($stuckImporting as $integration) {
            $integration->historicalImportJobs()
                ->whereIn('status', ['queued', 'running'])
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);

            $integration->update([
                'status' => 'active',
                'historical_import_status' => 'failed',
            ]);

            $this->warn("Reset stuck integration {$integration->integration_id} ({$integration->display_name}) from importing_historical to active");
            $cleaned++;
        }

        // 4. Fix integrations with last_sync_status stuck on 'syncing' for > 1 hour
        $stuckSyncing = Integration::where('last_sync_status', 'syncing')
            ->where('updated_at', '<', now()->subHour())
            ->get();

        foreach ($stuckSyncing as $integration) {
            $integration->update(['last_sync_status' => 'failed']);
            $this->warn("Reset stuck syncing status for integration {$integration->integration_id} ({$integration->display_name})");
            $cleaned++;
        }

        $this->info("Cleanup complete. {$cleaned} items fixed.");
        return 0;
    }
}
