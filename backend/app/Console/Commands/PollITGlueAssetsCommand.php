<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class PollITGlueAssetsCommand extends Command
{
    protected $signature = 'itglue:poll-assets 
        {--integration= : Specific integration ID to poll}';

    protected $description = 'Poll ITGlue integrations for SSL certificates, domains, and flexible assets';

    public function handle()
    {
        $integrations = $this->getIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('No active ITGlue integrations found');
            return 0;
        }

        $this->info("Polling ITGlue integrations for assets");

        foreach ($integrations as $integration) {
            $this->info("Processing integration: {$integration->name} (ID: {$integration->integration_id})");
            
            try {
                $itGlueIntegration = app(\App\Integrations\ITGlue\ITGlueIntegration::class, ['integration' => $integration]);
                
                // Poll SSL certs, domains, and assets
                $this->pollAssets($itGlueIntegration, $integration);
                
                $this->info("✓ Successfully polled integration: {$integration->name}");
            } catch (\Exception $e) {
                $this->error("Failed to poll integration {$integration->name}: {$e->getMessage()}");
                Log::error('ITGlue poll failed', [
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
        $query = Integration::where('integration_type', 'ITGlue')
            ->where('status', 'active');

        if ($integrationId = $this->option('integration')) {
            $query->where('integration_id', $integrationId);
        }

        return $query->get();
    }

    protected function pollAssets($itGlueIntegration, Integration $integration)
    {
        $this->info("  Polling ITGlue assets...");
        
        $errors = [];
        
        try {
            // Call the protected syncAdditionalData method via reflection
            $reflection = new \ReflectionClass($itGlueIntegration);
            $method = $reflection->getMethod('syncAdditionalData');
            $method->setAccessible(true);
            $method->invoke($itGlueIntegration, $errors);
            
            $this->info("  - Assets synced successfully");
            
            if (!empty($errors)) {
                $this->warn("  - Errors: " . count($errors));
                foreach ($errors as $error) {
                    Log::warning('ITGlue asset sync error', [
                        'integration_id' => $integration->integration_id,
                        'error' => $error
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->error("  - Failed to sync assets: {$e->getMessage()}");
            throw $e;
        }
    }
}
