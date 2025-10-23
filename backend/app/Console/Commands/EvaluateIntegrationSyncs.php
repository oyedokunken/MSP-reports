<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Services\SyncEngine;
use Carbon\Carbon;

class EvaluateIntegrationSyncs extends Command
{
    protected $signature = 'integrations:evaluate-syncs';
    protected $description = 'Evaluate integration sync schedules and queue syncs that are due';

    public function handle(SyncEngine $syncEngine)
    {
        $this->info('Evaluating integration sync schedules...');

        $now = Carbon::now();
        $integrationsProcessed = 0;
        $syncsQueued = 0;

        $integrations = Integration::where('status', 'active')
            ->orWhere('status', 'importing_historical')
            ->get();

        foreach ($integrations as $integration) {
            $company = $integration->company;
            
            $autoSyncEnabled = $company->settings['integrations']['auto_sync_enabled'] ?? true;
            
            if (!$autoSyncEnabled) {
                $this->info("Auto-sync disabled for company {$company->company_id}");
                continue;
            }

            $syncSchedule = $company->settings['integrations']['sync_schedule'] ?? '0 */4 * * *';
            
            if ($this->isSyncDue($integration, $syncSchedule, $now)) {
                try {
                    $syncLog = $syncEngine->queueSync($integration);
                    $syncsQueued++;
                    
                    $this->info("Queued sync for integration {$integration->integration_id} ({$integration->integration_type})");
                } catch (\Exception $e) {
                    $this->error("Failed to queue sync for integration {$integration->integration_id}: {$e->getMessage()}");
                }
            }

            $integrationsProcessed++;
        }

        $this->info("Processed {$integrationsProcessed} integrations, queued {$syncsQueued} syncs");

        return 0;
    }

    private function isSyncDue(Integration $integration, string $cronSchedule, Carbon $now): bool
    {
        if (!$integration->last_sync_at) {
            return true;
        }

        $intervalHours = $this->parseCronInterval($cronSchedule);
        
        if ($intervalHours === null) {
            $intervalHours = 4;
        }

        $lastSync = Carbon::parse($integration->last_sync_at);
        $nextSyncDue = $lastSync->copy()->addHours($intervalHours);

        return $now->gte($nextSyncDue);
    }

    private function parseCronInterval(string $cronSchedule): ?int
    {
        if (preg_match('/\*\/(\d+)/', $cronSchedule, $matches)) {
            return (int)$matches[1];
        }

        $patterns = [
            '0 */1 * * *' => 1,   // Hourly
            '0 */2 * * *' => 2,   // Every 2 hours
            '0 */4 * * *' => 4,   // Every 4 hours
            '0 */6 * * *' => 6,   // Every 6 hours
            '0 */12 * * *' => 12, // Every 12 hours
            '0 0 * * *' => 24,    // Daily
        ];

        return $patterns[$cronSchedule] ?? null;
    }
}
