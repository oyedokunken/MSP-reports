<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Integrations\Autotask\AutotaskIntegration;
use Carbon\Carbon;

class TestOptimizedSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:optimized-sync {integration_id} {--days=90}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the optimized Autotask historical sync';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $integrationId = $this->argument('integration_id');
        $daysBack = (int) $this->option('days');
        
        $integration = Integration::find($integrationId);
        
        if (!$integration) {
            $this->error("Integration not found!");
            return 1;
        }

        if (!in_array($integration->integration_type, ['Autotask', 'Datto'])) {
            $this->error("Integration is not Autotask or Datto!");
            return 1;
        }

        $endDate = now();
        $startDate = now()->subDays($daysBack)->startOfDay();
        
        $this->info("=== Testing Optimized Sync ===");
        $this->info("Integration: {$integration->display_name} (ID: {$integration->integration_id})");
        $this->info("Type: {$integration->integration_type}");
        $this->info("Days back: {$daysBack}");
        $this->line('');
        
        $startMemory = memory_get_peak_usage(true);
        $this->info("Initial memory: " . round($startMemory / 1024 / 1024, 2) . " MB");
        $this->info("Date range: {$startDate->toDateString()} to {$endDate->toDateString()}");
        
        // Count records before
        $beforeCount = \DB::table('integration_data')
            ->where('integration_id', $integrationId)
            ->count();
            
        $this->info("Records before: {$beforeCount}");
        
        // Time the sync
        
        try {
            if ($integration->integration_type === 'Autotask') {
                $integrationClass = new AutotaskIntegration($integration);
            } elseif ($integration->integration_type === 'Datto') {
                $integrationClass = new \App\Integrations\Datto\DattoIntegration($integration);
            }
            
            $this->info("Starting optimized sync...");
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage(true);

            // Call the optimized method (now the standard method)
            $result = $integrationClass->getHistoricalData($startDate, $endDate);

            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->line('');
            $this->info("=== Sync Results ===");
            $this->info("Duration: {$duration}s");
            $this->info("Records processed: " . ($result['total_processed'] ?? 0));
            $this->info("Errors: " . count($result['errors'] ?? []));
            $this->info("Peak memory: " . round($endMemory / 1024 / 1024, 2) . " MB");
            $this->info("Current memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB");
            
            // Count records after
            $afterCount = \DB::table('integration_data')
                ->where('integration_id', $integrationId)
                ->count();
                
            $this->info("Records after: {$afterCount}");
            $this->info("New records: " . ($afterCount - $beforeCount));
            
            if (!empty($result['errors'])) {
                $this->error("Errors encountered:");
                foreach ($result['errors'] as $error) {
                    $this->error(" - $error");
                }
            }
            
            $this->line('');
            $this->info("✅ Test completed successfully");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Sync failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
