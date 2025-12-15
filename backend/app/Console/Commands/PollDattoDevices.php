<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Jobs\SyncDattoDevicesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollDattoDevices extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'datto:poll-devices {--integration=* : Specific integration IDs to poll}';

    /**
     * The console command description.
     */
    protected $description = 'Poll devices from Datto RMM integrations (separate from main sync to avoid rate limits)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $integrationIds = $this->option('integration');
        
        // Query for Datto integrations
        $query = Integration::where('integration_type', 'Datto')
            ->where('status', 'active');

        // If specific integrations were requested, filter to only those
        if (!empty($integrationIds)) {
            $query->whereIn('integration_id', $integrationIds);
        }

        $integrations = $query->get();
        $this->info("Found " . $integrations->count() . " Datto integrations to poll");

        if ($integrations->count() === 0) {
            $this->warn("No active Datto integrations found");
            return 0;
        }

        foreach ($integrations as $integration) {
            $this->info("\nPolling devices for integration: {$integration->integration_name} (ID: {$integration->integration_id})");
            $startTime = now();
            
            try {
                // Dispatch the device sync job
                SyncDattoDevicesJob::dispatch($integration);
                
                $this->info("✓ Successfully queued device sync job for: {$integration->integration_name}");
                $this->info("  - Job dispatched at: " . now()->format('Y-m-d H:i:s'));
                $this->info("  - Check logs for completion status");
                
                Log::info('Datto: Manual device poll dispatched', [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->integration_name,
                    'dispatched_at' => now(),
                    'via' => 'artisan command'
                ]);
                
            } catch (\Exception $e) {
                $this->error("Failed to queue device sync for integration: {$integration->integration_name}");
                $this->error("Error: " . $e->getMessage());
                
                Log::error('Datto: Failed to dispatch manual device poll', [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->integration_name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->newLine();
        $this->info("Device polling jobs queued for " . $integrations->count() . " integration(s)");
        $this->info("Jobs will be processed by the queue worker");

        return 0;
    }
}
