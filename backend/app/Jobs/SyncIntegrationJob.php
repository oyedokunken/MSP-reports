<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\SyncEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Integrations\Autotask\AutotaskIntegration;
use App\Integrations\Datto\DattoIntegration;
use App\Integrations\Sendmarc\SendmarcIntegration;
use App\Integrations\ITGlue\ITGlueIntegration;
use App\Integrations\LibreNMS\LibreNMSIntegration;
use App\Integrations\BitDefender\BitDefenderIntegration;
use App\Integrations\SaaS\SaaSIntegration;
use App\Mail\IntegrationSyncFailedMail;

class SyncIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5; // Reduced tries to avoid endless loops, since we have long timeout
    public $timeout = 3600; // Increased to 60 minutes to handle Datto and LibreNMS
    public $backoff = [60, 300, 900, 1800]; // Exponential backoff: 1 min, 5 min, 15 min, 30 min
    public $deleteWhenMissingModels = true;

    protected Integration $integration;
    protected SyncLog $syncLog;

    public function __construct(Integration $integration, SyncLog $syncLog)
    {
        $this->integration = $integration;
        $this->syncLog = $syncLog;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->integration->integration_id))
                ->releaseAfter(60) 
                ->expireAfter(3600) 
        ];
    }

    public function handle(SyncEngine $syncEngine): void
    {
        try {

            $this->syncLog->update([
                'status' => 'running',
                'error_messages' => null // Clear any errors from previous retries
            ]);

            // Update Integration status to reflect active sync in UI immediately
            $this->integration->update([
                'last_sync_status' => 'syncing' 
            ]);

            // Prevent overlapping syncs (Double-check)
            // The WithoutOverlapping middleware handles job locking, but since we dispatch batches and return,
            // the lock releases early. We must check DB state to ensure no other logical sync is active.
            $activeSyncExists = \App\Models\SyncLog::where('integration_id', $this->integration->integration_id)
                ->whereIn('status', ['running', 'processing_batches']) // 'processing_batches' is legacy but safe to check
                ->where('sync_id', '!=', $this->syncLog->sync_id)
                ->where('created_at', '>', now()->subHours(2)) // Ignore stale (>2h) syncs that cleanup hasn't caught yet
                ->exists();

            if ($activeSyncExists) {
                throw new \Exception("Sync aborted: Another sync is currently in progress.");
            }
            
            $integrationClass = $this->loadIntegrationClass();

            if (!$integrationClass) {
                throw new \Exception("Integration type {$this->integration->integration_type} not found.");
            }
            
            $result = $integrationClass->sync();
            
            $recordsSynced = $result['records_synced'] ?? 0;
            
            if (method_exists($integrationClass, 'getMetadata') && method_exists($integrationClass, 'getHistoricalData')) {
                $metadata = $integrationClass->getMetadata();
                
                $skipHistorical = $this->integration->integration_type === 'Datto';
                
                if (($metadata['supports_historical'] ?? false) && !$skipHistorical) {
                    $startDate = now()->subHours(4); 
                    $endDate = now();
                    
                    try {
                        $dataResult = $integrationClass->getHistoricalData($startDate, $endDate);
                        
                        $recordsSynced += ($dataResult['total_processed'] ?? 0);
                        if (!empty($dataResult['errors'])) {
                            $result['errors'] = array_merge($result['errors'] ?? [], $dataResult['errors']);
                        }
                    } catch (\Exception $e) {
                         Log::error('Integration data polling failed', [
                            'integration_id' => $this->integration->integration_id,
                            'type' => $this->integration->integration_type,
                            'error' => $e->getMessage()
                        ]);
                        $result['errors'][] = "Data polling failed: " . $e->getMessage();
                    }
                }
            }
            $recordsFailed = $result['records_failed'] ?? 0;
            $customersMapped = $result['customers_mapped'] ?? [];
            
            Log::info('Integration sync result', [
                'integration_id' => $this->integration->integration_id,
                'records_synced' => $recordsSynced,
                'records_failed' => $recordsFailed,
                'customers_mapped_count' => count($customersMapped),
                'customers_mapped' => $customersMapped,
                'batches_dispatched' => $result['batches_dispatched'] ?? 0
            ]);
            
            // DEFER COMPLETIION IF BATCHES ARE RUNNING
            if (($result['batches_dispatched'] ?? 0) > 0) {
                 $batchCount = $result['batches_dispatched'];
                 
                 // Initialize Redis counters
                 \Illuminate\Support\Facades\Redis::connection()->set("sync_log:{$this->syncLog->sync_id}:pending", $batchCount);
                 // 1 day expiry
                 \Illuminate\Support\Facades\Redis::connection()->expire("sync_log:{$this->syncLog->sync_id}:pending", 86400);

                 // Update status to intermediate state
                 $this->syncLog->update([
                     'status' => 'running', 
                     'records_synced' => $recordsSynced // Update initial count (customers)
                 ]);
                 
                 Log::info('Integration sync deferred - processing background batches', [
                     'integration_id' => $this->integration->integration_id,
                     'batches' => $batchCount,
                     'sync_id' => $this->syncLog->sync_id
                 ]);
                 
                 // DO NOT call completeSyncLog here - let the final batch job do it
            } else {
                $syncEngine->completeSyncLog(
                    $this->syncLog,
                    'success',
                    $recordsSynced,
                    $recordsFailed,
                    $result['errors'] ?? [],
                    $customersMapped
                );
    
                Log::info('Integration sync completed', [
                    'integration_id' => $this->integration->integration_id,
                    'records_synced' => $recordsSynced,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Integration sync failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            $syncEngine->completeSyncLog(
                $this->syncLog,
                'failed',
                0,
                0,
                [$e->getMessage()]
            );
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        try {
            Log::error('Integration sync job failed permanently after all retries', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => $this->integration->integration_type,
                'company_id' => $this->integration->company_id,
                'attempts' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            // CRITICAL: Update sync log first to prevent stuck 'running' status
            $this->syncLog->update([
                'status' => 'failed',
                'error_messages' => [$exception->getMessage()],
                'completed_at' => now(),
            ]);

            $hasExistingData = \App\Models\IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->exists();

            $newStatus = $hasExistingData ? 'active' : 'error';

            $this->integration->update([
                'status' => $newStatus,
                'last_sync_status' => 'failed',
                'last_sync_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            Log::warning('Integration sync failed but keeping status as active due to existing data', [
                'integration_id' => $this->integration->integration_id,
                'has_existing_data' => $hasExistingData,
                'new_status' => $newStatus,
            ]);

            $company = $this->integration->company;
            $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];
            
            if (!empty($notificationEmails)) {
                try {
                    foreach ($notificationEmails as $email) {
                        Mail::to($email)->send(
                            new IntegrationSyncFailedMail($this->integration, $exception, $this->attempts())
                        );
                    }
                    
                    Log::info('Integration sync failure notifications sent', [
                        'integration_id' => $this->integration->integration_id,
                        'recipients' => $notificationEmails,
                    ]);
                } catch (\Exception $mailException) {
                    Log::error('Failed to send integration sync failure notification', [
                        'integration_id' => $this->integration->integration_id,
                        'mail_error' => $mailException->getMessage(),
                    ]);
                }
            } else {
                Log::warning('No notification emails configured for integration sync failure', [
                    'integration_id' => $this->integration->integration_id,
                    'company_id' => $company->company_id,
                ]);
            }
        } catch (\Throwable $e) {
            // Last resort failsafe: directly update database if anything goes wrong
            Log::critical('Failed to update sync log in failed() method - using DB failsafe', [
                'sync_id' => $this->syncLog->sync_id ?? 'unknown',
                'integration_id' => $this->integration->integration_id ?? 'unknown',
                'original_error' => $exception->getMessage(),
                'failsafe_error' => $e->getMessage(),
            ]);
            
            try {
                \Illuminate\Support\Facades\DB::table('sync_logs')
                    ->where('sync_id', $this->syncLog->sync_id)
                    ->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_messages' => json_encode(['Critical failure in failed() callback: ' . $e->getMessage()])
                    ]);
            } catch (\Throwable $dbException) {
                Log::emergency('DB failsafe also failed in SyncIntegrationJob::failed()', [
                    'sync_id' => $this->syncLog->sync_id ?? 'unknown',
                    'db_error' => $dbException->getMessage(),
                ]);
            }
        }
    }

    private function loadIntegrationClass()
    {
        $integrationClasses = [
            'Autotask' => AutotaskIntegration::class,
            'Datto' => DattoIntegration::class,
            'Sendmarc' => SendmarcIntegration::class,
            'ITGlue' => ITGlueIntegration::class,
            'LibreNMS' => LibreNMSIntegration::class,
            'BitDefender' => BitDefenderIntegration::class,
            'SaaS' => SaaSIntegration::class,
        ];

        $className = $integrationClasses[$this->integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            $instance = new $className($this->integration);
            
            // Inject heartbeat callback if supported
            if (method_exists($instance, 'setHeartbeatCallback')) {
                $instance->setHeartbeatCallback(function() {
                    $this->sendHeartbeat();
                });
            }

            // Inject SyncLogID if supported (via HasHeartbeat trait)
            if (method_exists($instance, 'setSyncLogId') && $this->syncLog) {
                $instance->setSyncLogId($this->syncLog->sync_id);
            }
            
            return $instance;
        }

        return null;
    }

    public function sendHeartbeat(): void
    {
        if ($this->syncLog) {
            // Update timestamp directly in DB to avoid model event overhead/cache issues
            \Illuminate\Support\Facades\DB::table('sync_logs')
                ->where('sync_id', $this->syncLog->sync_id)
                ->update(['last_heartbeat_at' => now()]);
        }
    }
}
