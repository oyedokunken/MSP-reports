<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditLogCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup {days=90 : The number of days of logs to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove audit logs older than a specified number of days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->argument('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up audit logs older than {$cutoffDate->toDateString()} ({$days} days)...");

        $count = \App\Models\AuditLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$count} audit log entries.");

        return 0;
    }
}
