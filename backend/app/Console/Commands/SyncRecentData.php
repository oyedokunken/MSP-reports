<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Jobs\SyncRecentDataJob;
use Illuminate\Support\Facades\Log;

class SyncRecentData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:sync-recent-data 
                            {--days=7 : Number of days back to sync}
                            {--integration= : Specific integration ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync recent data (last N days) for all active integrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysBack = (int) $this->option('days');
        $integrationId = $this->option('integration');

        $this->info("Syncing recent data (last {$daysBack} days)...");
        $this->newLine();

        // Get integrations to sync
        $query = Integration::where('status', 'active');
        
        if ($integrationId) {
            $query->where('integration_id', $integrationId);
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->warn('No active integrations found.');
            return Command::FAILURE;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($integrations as $integration) {
            // Check if integration supports historical data
            $className = "App\\Integrations\\{$integration->integration_type}\\{$integration->integration_type}Integration";
            
            if (!class_exists($className)) {
                $this->warn("  ⊘ {$integration->display_name} - Integration class not found");
                $skipped++;
                continue;
            }

            $tempIntegration = new \App\Models\Integration([
                'integration_type' => $integration->integration_type,
                'credentials' => [],
            ]);
            $instance = new $className($tempIntegration);
            $metadata = $instance->getMetadata();

            if (!($metadata['supports_historical'] ?? false)) {
                $this->line("  ⊘ {$integration->display_name} - Does not support historical data");
                $skipped++;
                continue;
            }

            // Queue the job
            SyncRecentDataJob::dispatch($integration, $daysBack);
            $this->info("  ✓ {$integration->display_name} - Queued");
            $queued++;

            Log::info('SyncRecentData command: Job queued', [
                'integration_id' => $integration->integration_id,
                'integration_type' => $integration->integration_type,
                'days_back' => $daysBack,
            ]);
        }

        $this->newLine();
        $this->info("Summary:");
        $this->line("  Queued: {$queued}");
        $this->line("  Skipped: {$skipped}");
        $this->newLine();
        $this->comment("Jobs are processing in the background. Check logs for progress.");

        return Command::SUCCESS;
    }
}
