<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Services\SyncEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MonitorIntegrationSyncs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:monitor
                            {--force : Force sync regardless of schedule}
                            {--id= : Monitor specific integration ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Smart monitoring of integration sync state. Handles cold-starts, stale checks, and routine schedules.';

    protected SyncEngine $syncEngine;

    public function __construct(SyncEngine $syncEngine)
    {
        parent::__construct();
        $this->syncEngine = $syncEngine;
    }

    public function handle(): int
    {
        $this->info('Starting Integration Monitor...');
        
        $query = Integration::query()
            ->whereIn('status', ['active', 'importing_historical', 'created']); // Include 'created' for immediate pickup

        if ($id = $this->option('id')) {
            $query->where('integration_id', $id);
        }

        $integrations = $query->get();
        $counters = ['checked' => 0, 'triggered' => 0, 'skipped' => 0, 'crashed' => 0];

        foreach ($integrations as $integration) {
            $counters['checked']++;
            
            // 1. Health Safety Checks (The "Doctor" Phase)
            $this->performHealthChecks($integration, $counters);

            // 2. Schedule Evaluation (The "Scheduler" Phase)
            if ($this->shouldTriggerSync($integration)) {
                $this->triggerSync($integration, $counters);
            } else {
                $counters['skipped']++;
            }
        }

        $output = json_encode($counters);
        $this->info($output !== false ? $output : 'Failed to encode counters');
        return 0;
    }

    /**
     * @param array<string, int> $counters
     */
    protected function performHealthChecks(Integration $integration, array &$counters): void
    {
        $now = now();

        // Check for "Zombie" Running Syncs (Stuck > 2 hours)
        // These lock the integration and prevent new syncs.
        $staleSyncs = $integration->syncLogs()
            ->where('status', 'running')
            ->where('started_at', '<', $now->copy()->subHours(4))
            ->get();

        foreach ($staleSyncs as $sync) {
            /** @var \App\Models\SyncLog $sync */
            $sync->update([
                'status' => 'failed',
                'completed_at' => $now,
                'error_messages' => ['Terminated by Monitor: Timed out (Running > 4hrs)']
            ]);
            $counters['crashed']++;
            Log::warning("Terminated stale sync", ['sync_id' => $sync->sync_id]);
        }

        // Check for "Orphaned" Syncs - heartbeat stale but batches likely complete
        // These happen when batch jobs finish but final completion callback fails
        $runningSyncs = $integration->syncLogs()
            ->where('status', 'running')
            ->where('started_at', '<', $now->copy()->subMinutes(5)) // Running at least 5 min
            ->get();

        foreach ($runningSyncs as $sync) {
            /** @var \App\Models\SyncLog $sync */
            // Check heartbeat or update age in PHP (timezone sensitive)
            $heartbeatAt = $sync->last_heartbeat_at ?? $sync->updated_at ?? $sync->started_at;
            $ageInMinutes = abs($now->diffInMinutes($heartbeatAt, false));

            if ($ageInMinutes < 20) {
                continue; // Still active
            }

            // STAGNANT PROGRESS CHECK
            // If the heartbeat is old, check if records are still being synced.
            // We use a small cache key to track the last record count seen by the monitor.
            $progressKey = "monitor:sync_progress:{$sync->sync_id}";
            $lastCount = \Illuminate\Support\Facades\Cache::get($progressKey);
            
            if ($lastCount !== null && $lastCount == $sync->records_synced) {
                // Progress has stalled for at least one monitor cycle (usually 5-10 min)
                $isStalled = true;
            } else {
                \Illuminate\Support\Facades\Cache::put($progressKey, $sync->records_synced, now()->addMinutes(30));
                $isStalled = false;
            }

            // Check Redis for pending batches
            $pendingKey = "sync_log:{$sync->sync_id}:pending";
            $pending = \Illuminate\Support\Facades\Redis::get($pendingKey);
            $pendingValue = ($pending === null) ? 0 : (int)$pending;
            
            // If key doesn't exist, is <= 0, OR we are stalled and at least 10 min old
            if ($pendingValue <= 0 || ($isStalled && $ageInMinutes >= 20)) {
                // No pending batches OR stalled - mark as complete
                $failedKey = "sync_log:{$sync->sync_id}:failed";
                $failedCount = (int) \Illuminate\Support\Facades\Redis::get($failedKey);
                
                $status = $failedCount > 0 ? 'partial' : 'success';
                $errors = $failedCount > 0 ? ["{$failedCount} batch(es) failed"] : [];
                
                if ($isStalled && $pendingValue > 0) {
                    $errors[] = "Sync stalled with {$pendingValue} batches remaining. Auto-completed by Monitor.";
                    $status = 'partial';
                }
                
                $sync->update([
                    'status' => $status,
                    'completed_at' => $now,
                    'error_messages' => $errors ?: null,
                ]);
                
                // Update integration status
                $integration->update([
                    'last_sync_at' => $now,
                    'last_sync_status' => $status,
                ]);
                
                // Cleanup Redis & Cache
                \Illuminate\Support\Facades\Redis::del($pendingKey, $failedKey);
                \Illuminate\Support\Facades\Cache::forget($progressKey);
                
                Log::info("Auto-completed orphaned/stalled sync", [
                    'sync_id' => $sync->sync_id,
                    'records_synced' => $sync->records_synced,
                    'status' => $status,
                    'was_stalled' => $isStalled
                ]);
                
                $this->line("Auto-completed " . ($isStalled ? "stalled" : "orphaned") . " sync #{$sync->sync_id} with {$sync->records_synced} records");
            }
        }
    }

    protected function shouldTriggerSync(Integration $integration): bool
    {
        // 0. Auto-Sync Disabled globally?
        $settings = $integration->company->settings ?? [];
        if (($settings['integrations']['auto_sync_enabled'] ?? true) === false) {
            return false;
        }

        // 1. Overlapping Check
        // If it's already running validly, don't double tap.
        $isRunning = $integration->syncLogs()
            ->whereIn('status', ['queued', 'running'])
            ->exists();
        
        if ($isRunning) {
            return false;
        }

        // 2. Force Flag
        if ($this->option('force')) {
            return true;
        }

        // 3. The "Cold Start" Check (CRITICAL FIX)
        // If last_sync_at is NULL, it has NEVER synced. 
        // We treat this as "Due Immediately".
        if (!$integration->last_sync_at) {
            $this->line("Integration {$integration->integration_id} is cold (never synced). Triggering immediately.");
            return true;
        }

        // 4. Standard Schedule Check
        $schedule = $integration->sync_schedule ?? $this->getDefaultSchedule($integration->integration_type);
        $nextDue = $this->calculateNextSync($integration->last_sync_at, $schedule);
        
        return now()->gte($nextDue);
    }

    /**
     * @param array<string, int> $counters
     */
    protected function triggerSync(Integration $integration, array &$counters): void
    {
        $lockKey = "sync_trigger_lock:{$integration->integration_id}";
        
        // Use atomic lock to prevent race conditions between monitor instances
        $lock = Cache::lock($lockKey, 60); // 60 second lock timeout
        
        if (!$lock->get()) {
            $this->line("Integration #{$integration->integration_id} is locked by another process, skipping");
            $counters['skipped']++;
            return;
        }
        
        try {
            // Double-check no sync is running (now protected by lock)
            $isRunning = $integration->syncLogs()
                ->whereIn('status', ['queued', 'running'])
                ->exists();
            
            if ($isRunning) {
                $this->line("Integration #{$integration->integration_id} already syncing, skipping");
                $counters['skipped']++;
                return;
            }
            
            $this->syncEngine->triggerSync($integration, 'current_state', 'default');
            $counters['triggered']++;
            $this->info("Triggered sync for Integration #{$integration->integration_id}");
        } catch (\Exception $e) {
            Log::error("Monitor failed to trigger sync", [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage()
            ]);
        } finally {
            $lock->release();
        }
    }

    protected function calculateNextSync(Carbon $lastSync, string $cronExpression): Carbon
    {
        // Parse cron expression: "minute hour dom month dow"
        // Examples: "*/5 * * * *" (every 5 min), "0 */6 * * *" (every 6 hrs)
        $minutes = 240; // Default 4 hours
        
        $parts = preg_split('/\s+/', trim($cronExpression));
        if (is_array($parts) && count($parts) >= 2) {
            $minuteField = $parts[0];
            $hourField = $parts[1];
            
            // Check hour field first (e.g., "0 */6 * * *" = every 6 hours)
            if (preg_match('/\*\/(\d+)/', $hourField, $m)) {
                $minutes = (int)$m[1] * 60; // Hour-based interval
            }
            // Check minute field (e.g., "*/5 * * * *" = every 5 minutes)
            elseif (preg_match('/\*\/(\d+)/', $minuteField, $m)) {
                $minutes = (int)$m[1]; // Minute-based interval
            }
        }

        return $lastSync->copy()->addMinutes($minutes);
    }

    protected function getDefaultSchedule(string $type): string
    {
        // Higher frequency for RMM/Monitoring tools
        return match($type) {
            'Datto', 'LibreNMS' => '*/5 * * * *',
            'Autotask' => '*/10 * * * *',
            default => '0 */4 * * *',
        };
    }
}
