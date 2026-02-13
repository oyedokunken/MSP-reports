<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\SyncEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TestConcurrency extends Command
{
    protected $signature = 'integrations:test-concurrency';
    protected $description = 'Trigger 5 integrations at once to test concurrency and parallelism';

    /** @var array<int, string> */
    protected array $targetIntegrations = [
        2  => 'Datto',
        21 => 'Cipp',
    ];

    public function handle(SyncEngine $syncEngine): int
    {
        $this->info("Starting Concurrency Test...");
        $this->info("Targeting: " . implode(', ', $this->targetIntegrations));
        $this->newLine();

        $integrations = Integration::whereIn('integration_id', array_keys($this->targetIntegrations))->get();

        if ($integrations->count() < count($this->targetIntegrations)) {
            $this->error("Some target integrations were not found in the database.");
            return 1;
        }

        $this->info("Triggering all 5 integrations simultaneously...");
        
        $syncLogs = [];
        foreach ($integrations as $integration) {
            $this->line("Triggering {$integration->display_name}...");
            $syncLogs[$integration->integration_id] = $syncEngine->triggerSync($integration);
        }

        $this->newLine();
        $this->info("Monitoring Status (Ctrl+C to stop)...");

        while (true) {
            $rows = [];
            $allFinished = true;

            foreach ($integrations as $integration) {
                $latestLog = SyncLog::where('integration_id', $integration->integration_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $status = $latestLog ? $latestLog->status : 'unknown';
                $records = $latestLog ? $latestLog->records_synced : 0;
                $started = $latestLog ? $latestLog->started_at : '--';
                
                $rows[] = [
                    $integration->display_name,
                    $integration->integration_id,
                    $status,
                    $records,
                    $started,
                ];

                if (!in_array($status, ['success', 'failed', 'partial'])) {
                    $allFinished = false;
                }
            }

            $this->output->write("\e[H\e[J"); // Clear screen
            $this->info("Concurrency Test Monitor");
            
            // Show Queue Status
            $syncQueueSize = Redis::connection()->llen('queues:sync');
            $defaultQueueSize = Redis::connection()->llen('queues:default');
            
            $this->warn("Queue Status: [sync: {$syncQueueSize}] [default: {$defaultQueueSize}]");
            $this->line("Workers available: [sync: 2] [default: 12]"); // for now 2 numprocs using for parallel
            $this->newLine();

            $this->table(
                ['Integration', 'ID', 'Status', 'Records', 'Started At'],
                $rows
            );

            if ($allFinished) {
                $this->info("All syncs have reached a final state.");
                break;
            }

            sleep(2);
        }

        return 0;
    }
}
