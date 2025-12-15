<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class PollLibreNMSDevicesCommand extends Command
{
    protected $signature = 'librenms:poll-devices 
        {--integration= : Specific integration ID to poll}';

    protected $description = 'Poll LibreNMS integrations for device data and availability';

    public function handle()
    {
        $integrations = $this->getIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('No active LibreNMS integrations found');
            return 0;
        }

        $this->info("Polling LibreNMS integrations for devices");

        foreach ($integrations as $integration) {
            $this->info("Processing integration: {$integration->name} (ID: {$integration->integration_id})");
            
            try {
                $libreNMSIntegration = app(\App\Integrations\LibreNMS\LibreNMSIntegration::class, ['integration' => $integration]);
                
                // Poll devices
                $this->pollDevices($libreNMSIntegration, $integration);
                
                $this->info("✓ Successfully polled integration: {$integration->name}");
            } catch (\Exception $e) {
                $this->error("Failed to poll integration {$integration->name}: {$e->getMessage()}");
                Log::error('LibreNMS poll failed', [
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
        $query = Integration::where('integration_type', 'LibreNMS')
            ->where('status', 'active');

        if ($integrationId = $this->option('integration')) {
            $query->where('integration_id', $integrationId);
        }

        return $query->get();
    }

    protected function pollDevices($libreNMSIntegration, Integration $integration)
    {
        $this->info("  Polling LibreNMS devices...");
        
        $errors = [];
        $devicesProcessed = 0;
        
        try {
            // Call the protected syncDevices method via reflection
            // Or we can extract it to a public method
            $reflection = new \ReflectionClass($libreNMSIntegration);
            $method = $reflection->getMethod('syncDevices');
            $method->setAccessible(true);
            $method->invoke($libreNMSIntegration, $errors);
            
            $this->info("  - Devices synced successfully");
            
            if (!empty($errors)) {
                $this->warn("  - Errors: " . count($errors));
                foreach ($errors as $error) {
                    Log::warning('LibreNMS device sync error', [
                        'integration_id' => $integration->integration_id,
                        'error' => $error
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->error("  - Failed to sync devices: {$e->getMessage()}");
            throw $e;
        }
    }
}
