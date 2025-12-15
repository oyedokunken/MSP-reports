<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncLog;
use Carbon\Carbon;

class CleanupStaleSyncs extends Command
{
    protected $signature = 'syncs:cleanup-stale';
    protected $description = 'Mark stale syncs (running > 2 hours) as failed';

    public function handle()
    {
        $threshold = now()->subHours(2);
        
        $staleSyncs = SyncLog::where('status', 'running')
            ->where('started_at', '<', $threshold)
            ->get();
        
        if ($staleSyncs->isEmpty()) {
            $this->info('No stale syncs found.');
            return 0;
        }
        
        foreach ($staleSyncs as $sync) {
            $sync->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_messages' => ['Automatically marked as failed due to timeout (exceeded 2 hours)']
            ]);
            
            $this->warn("Marked sync {$sync->sync_id} (integration {$sync->integration_id}) as failed");
        }
        
        $this->info("Cleaned up {$staleSyncs->count()} stale syncs");
        return 0;
    }
}
