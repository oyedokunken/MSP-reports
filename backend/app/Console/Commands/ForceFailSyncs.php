<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Models\SyncLog;
use App\Models\Integration;

class ForceFailSyncs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:force-fail {--force : Force operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Forcefully fail all running/queued syncs, clear queues, and reset integration statuses.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will wipe all pending jobs and mark active syncs as failed. Are you sure?')) {
            return Command::SUCCESS;
        }

        $this->info('Starting force cleanup...');

        // 1. Clear Queues
        $queues = ['sync', 'critical', 'high', 'default', 'historical'];
        foreach ($queues as $queue) {
            $this->info("Clearing queue: {$queue}...");
            Artisan::call('queue:clear', ['--queue' => $queue, '--force' => true]);
        }
        $this->info('All queues cleared.');

        // 2. Clear Redis Locks & State
        $this->info('Clearing Redis locks and state...');
        try {
            $redis = Redis::connection();
            
            // Delete specific sync keys
            $keys = Helper::scanKeys('sync_log:*');
            if (!empty($keys)) {
                foreach (array_chunk($keys, 100) as $chunk) {
                    $redis->del(...$chunk);
                }
            }
            
            // Delete Laravel locks
            $lockKeys = Helper::scanKeys('laravel_database_laravel_cache:job:lock*'); 
             if (!empty($keys)) {
                foreach (array_chunk($keys, 100) as $chunk) {
                     // CAUTION: These might be prefixed, better to use Cache::forget if possible, 
                     // but pattern matching needs Redis.
                     // Rely on cache:clear for general locks
                }
            }

        } catch (\Exception $e) {
            $this->warn('Redis cleanup warning: ' . $e->getMessage());
        }

        // 3. Update Database - SyncLogs
        $this->info('Marking running/queued SyncLogs as failed...');
        $affectedLogs = SyncLog::whereIn('status', ['queued', 'working', 'running', 'pending'])
            ->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_messages' => json_encode(['Manually forced failure by sync:force-fail command'])
            ]);
        $this->info("Updated {$affectedLogs} sync logs.");

        // 4. Update Database - Integrations
        $this->info('Resetting Integration statuses...');
        $affectedIntegrations = Integration::whereIn('last_sync_status', ['syncing', 'running', 'queued', 'working'])
            ->update([
                'last_sync_status' => 'failed',
                'error_message' => 'Manually terminated by user via sync:force-fail',
                'last_sync_at' => now()
            ]);
        $this->info("Updated {$affectedIntegrations} integrations.");

        // 5. Clear Cache
        $this->info('Clearing application cache...');
        Artisan::call('cache:clear');

        // 6. Restart Queues
        $this->info('Restarting queue workers...');
        Artisan::call('queue:restart');

        $this->info('Force cleanup complete! You can now start fresh.');

        return Command::SUCCESS;
    }
}

class Helper {
     public static function scanKeys($pattern) {
        $redis = Redis::connection();
        $cursor = '0';
        $keys = [];
        
        do {
            // scan returns [cursor, [keys]]
            $result = $redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 1000]);
            $cursor = $result[0];
            $chunk = $result[1];
            
            if (!empty($chunk)) {
                $keys = array_merge($keys, $chunk);
            }
        } while ($cursor !== '0');
        
        return array_unique($keys);
     }
}
