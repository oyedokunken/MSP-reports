<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\SyncEngine;
use App\Integrations\Autotask\AutotaskIntegration;
use App\Integrations\Datto\DattoIntegration;
use App\Integrations\Sendmarc\SendmarcIntegration;
use App\Integrations\ITGlue\ITGlueIntegration;
use App\Integrations\LibreNMS\LibreNMSIntegration;
use App\Integrations\BitDefender\BitDefenderIntegration;
use App\Integrations\SaaS\SaaSIntegration;
use App\Integrations\VeeamOne\VeeamOneIntegration;
use App\Integrations\ThreatLocker\ThreatLockerIntegration;
use App\Integrations\Vonahi\VonahiIntegration;

class SyncIntegrationManual extends Command
{
    protected $signature = 'integrations:sync
                            {integration : Integration ID to sync}
                            {--skip-historical : Skip historical data polling}
                            {--dry-run : Show what would be synced without actually syncing}';

    protected $description = 'Manually run an integration sync (bypasses queue for debugging)';

    /** @var array<string, class-string> */
    protected array $integrationClasses = [
        'Autotask' => AutotaskIntegration::class,
        'Datto' => DattoIntegration::class,
        'Sendmarc' => SendmarcIntegration::class,
        'ITGlue' => ITGlueIntegration::class,
        'LibreNMS' => LibreNMSIntegration::class,
        'BitDefender' => BitDefenderIntegration::class,
        'SaaS' => SaaSIntegration::class,
        'VeeamOne' => VeeamOneIntegration::class,
        'ThreatLocker' => ThreatLockerIntegration::class,
        'Vonahi' => VonahiIntegration::class,
    ];

    public function handle(SyncEngine $syncEngine): int
    {
        $integrationId = $this->argument('integration');
        
        $integration = Integration::find($integrationId);
        
        if (!$integration) {
            $this->error("Integration #{$integrationId} not found.");
            $this->newLine();
            $this->info("Available integrations:");
            Integration::all()->each(function ($i) {
                $this->line("  [{$i->integration_id}] {$i->display_name} ({$i->integration_type}) - {$i->status->value}");
            });
            return 1;
        }

        $this->info("Integration: {$integration->display_name} ({$integration->integration_type})");
        $this->info("Company: {$integration->company->name}");
        $this->info("Status: {$integration->status->value}");
        $this->info("Last sync: " . ($integration->last_sync_at ?? 'Never'));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN - No changes will be made");
            $this->newLine();
        }

        // Check for existing running syncs
        $existingSync = SyncLog::where('integration_id', $integration->integration_id)
            ->whereIn('status', ['queued', 'running'])
            ->first();

        if ($existingSync) {
            $this->warn("Warning: There's already a sync in progress (ID: {$existingSync->sync_id}, Status: {$existingSync->status})");
            if (!$this->confirm('Continue anyway?', false)) {
                return 0;
            }
        }

        $className = $this->integrationClasses[$integration->integration_type] ?? null;

        if (!$className || !class_exists($className)) {
            $this->error("Integration type '{$integration->integration_type}' is not supported.");
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info("Would sync using: {$className}");
            return 0;
        }

        // Create sync log
        $syncLog = SyncLog::create([
            'integration_id' => $integration->integration_id,
            'sync_type' => 'current_state',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->info("Created SyncLog #{$syncLog->sync_id}");
        $this->newLine();

        // Update integration status
        $integration->update(['last_sync_status' => 'syncing']);

        try {
            $this->info("Loading integration class...");
            $instance = new $className($integration);

            // Set up heartbeat
            if (method_exists($instance, 'setHeartbeatCallback')) {
                $instance->setHeartbeatCallback(function() use ($syncLog) {
                    $syncLog->update(['last_heartbeat_at' => now()]);
                    $this->output->write('.');
                });
            }

            if (method_exists($instance, 'setSyncLogId')) {
                /** @var \App\Integrations\Contracts\IntegrationInterface $instance */
                $instance->setSyncLogId($syncLog->sync_id);
            }

            $this->info("Starting sync...");
            $this->newLine();

            /** @var \App\Integrations\Contracts\IntegrationInterface $instance */
            $result = $instance->sync();

            $recordsSynced = $result['records_synced'] ?? 0;
            $recordsFailed = $result['records_failed'] ?? 0;
            $customersMapped = $result['customers_mapped'] ?? [];
            $errors = $result['errors'] ?? [];
            $batchesDispatched = $result['batches_dispatched'] ?? 0;

            $this->newLine();
            $this->info("Sync phase completed:");
            $this->line("  Records synced: {$recordsSynced}");
            $this->line("  Records failed: {$recordsFailed}");
            $this->line("  Customers mapped: " . (is_array($customersMapped) ? count($customersMapped) : $customersMapped));
            $this->line("  Batches dispatched: {$batchesDispatched}");

            if (!empty($errors)) {
                $this->newLine();
                $this->warn("Errors:");
                foreach ($errors as $error) {
                    $this->line("  - {$error}");
                }
            }

            // Historical data polling
            if (!$this->option('skip-historical')) {
                $metadata = $instance->getMetadata();
                $skipHistorical = $integration->integration_type === 'Datto';

                if (($metadata['supports_historical'] ?? false) && !$skipHistorical) {
                    $this->newLine();
                    $this->info("Polling historical data (last 4 hours)...");

                    $startDate = now()->subHours(4);
                    $endDate = now();

                    try {
                        $dataResult = $instance->getHistoricalData($startDate, $endDate);
                        $recordsSynced += ($dataResult['total_processed'] ?? 0);

                        $this->line("  Historical records: " . ($dataResult['total_processed'] ?? 0));

                        if (!empty($dataResult['errors'])) {
                            $errors = array_merge($errors, $dataResult['errors']);
                            foreach ($dataResult['errors'] as $error) {
                                $this->warn("  - {$error}");
                            }
                        }
                    } catch (\Exception $e) {
                        $this->error("Historical data polling failed: {$e->getMessage()}");
                        $errors[] = "Data polling failed: " . $e->getMessage();
                    }
                }
            }

            // Complete sync log
            if ($batchesDispatched > 0) {
                $this->newLine();
                $this->warn("Note: {$batchesDispatched} batch jobs were dispatched to the queue.");
                $this->warn("Run 'php artisan queue:work --once' to process them, or check worker logs.");

                // Set up Redis counter for batch tracking using the Master Lock logic
                $redis = null;
                try {
                    $redis = \Illuminate\Support\Facades\Redis::connection();
                    $redis->set("sync_log:{$syncLog->sync_id}:master_running", 0); // Manual dispatch is already done
                    $redis->incrBy("sync_log:{$syncLog->sync_id}:pending", $batchesDispatched);
                    $redis->expire("sync_log:{$syncLog->sync_id}:pending", 86400);
                } catch (\Exception $e) {
                    $this->warn("Could not set Redis batch counter: {$e->getMessage()}");
                }

                $syncLog->update([
                    'status' => 'running',
                    'records_synced' => $recordsSynced,
                ]);

                // Final check if workers already finished
                if ($redis && (int) $redis->get("sync_log:{$syncLog->sync_id}:pending") <= 0) {
                     $this->info("All batches finished instantly. Finalizing...");
                     // Re-use syncEngine logic
                     $syncLog->update(['status' => 'success', 'completed_at' => now()]);
                }
            } else {
                $syncEngine->completeSyncLog(
                    $syncLog,
                    'success',
                    $recordsSynced,
                    $recordsFailed,
                    $errors,
                    $customersMapped
                );
            }

            $this->newLine();
            $this->info("Sync completed successfully!");

            return 0;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("Sync failed: {$e->getMessage()}");
            $this->newLine();
            $this->error("Stack trace:");
            $this->line($e->getTraceAsString());

            $syncEngine->completeSyncLog(
                $syncLog,
                'failed',
                0,
                0,
                [$e->getMessage()]
            );

            return 1;
        }
    }
}
