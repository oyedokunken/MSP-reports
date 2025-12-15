<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class PollDattoDevicesCommand extends Command
{
    protected $signature = 'datto:poll-devices 
        {--integration= : Specific integration ID to poll}';

    protected $description = 'Poll Datto RMM integrations for device data and alerts';

    public function handle()
    {
        $integrations = $this->getIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('No active Datto integrations found');
            return 0;
        }

        $this->info("Polling Datto integrations for devices");

        foreach ($integrations as $integration) {
            $this->info("Processing integration: {$integration->name} (ID: {$integration->integration_id})");
            
            try {
                $dattoIntegration = app(\App\Integrations\Datto\DattoIntegration::class, ['integration' => $integration]);
                
                // Poll devices and alerts
                $this->pollDevices($dattoIntegration, $integration);
                
                $this->info("✓ Successfully polled integration: {$integration->name}");
            } catch (\Exception $e) {
                $this->error("Failed to poll integration {$integration->name}: {$e->getMessage()}");
                Log::error('Datto poll failed', [
                    'integration_id' => $integration->integration_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return 0;
    }

    protected function getIntegrations()
    {
        $query = Integration::where('integration_type', 'Datto')
            ->where('status', 'active');

        if ($integrationId = $this->option('integration')) {
            $query->where('integration_id', $integrationId);
        }

        return $query->get();
    }

    protected function pollDevices($dattoIntegration, Integration $integration)
    {
        // Use getHistoricalData to fetch current snapshot of devices and recent alerts
        // This method in DattoIntegration is designed to pull current state
        $this->info("  Fetching device and alert data...");
        
        $startDate = now()->subHours(4); // Look back 4 hours for alerts/changes
        $endDate = now();
        
        $result = $dattoIntegration->getHistoricalData($startDate, $endDate);
        
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->error("  Error: $error");
            }
        }
        
        $count = $result['total_processed'] ?? 0;
        $this->info("  Processed $count records");
    }
}
