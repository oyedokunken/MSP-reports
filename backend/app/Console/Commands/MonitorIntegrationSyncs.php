<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Services\SyncEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        $this->info(json_encode($counters));
        return 0;
    }

    protected function performHealthChecks(Integration $integration, array &$counters): void
    {
        $now = now();

        // Check for "Zombie" Running Syncs (Stuck > 2 hours)
        // These lock the integration and prevent new syncs.
        $staleSyncs = $integration->syncLogs()
            ->where('status', 'running')
            ->where('started_at', '<', $now->copy()->subHours(2))
            ->get();

        foreach ($staleSyncs as $sync) {
            $sync->update([
                'status' => 'failed',
                'completed_at' => $now,
                'error_messages' => ['Terminated by Monitor: Timed out (Running > 2hrs)']
            ]);
            $counters['crashed']++;
            Log::warning("Terminated stale sync", ['sync_id' => $sync->sync_id]);
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

    protected function triggerSync(Integration $integration, array &$counters): void
    {
        try {
            $this->syncEngine->triggerSync($integration, 'current_state', 'default');
            $counters['triggered']++;
            $this->info("Triggered sync for Integration #{$integration->integration_id}");
        } catch (\Exception $e) {
            Log::error("Monitor failed to trigger sync", [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function calculateNextSync(Carbon $lastSync, string $cronExpression): Carbon
    {
        // Simplified Cron Parser (Minutes/Hours) for robustness
        // Handles "*/5 * * * *" or "0 */4 * * *"
        $minutes = 240; // Default 4 hours

        if (preg_match('/\*\/(\d+)\s+\*/', $cronExpression, $m)) {
            $minutes = (int)$m[1]; // Minute based
        } elseif (preg_match('/\*\/(\d+)/', $cronExpression, $m)) {
            $minutes = (int)$m[1] * 60; // Hour based
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
