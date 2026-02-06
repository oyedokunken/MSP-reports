<?php

declare(strict_types=1);

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
use App\Mail\IntegrationSyncFailedMail;
use App\Integrations\Contracts\IntegrationInterface;

final class SyncIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5; 
    public int $timeout = 3600; 
    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 1800]; 
    public bool $deleteWhenMissingModels = true;
    // public $queue = 'sync'; // Removed to avoid conflict with Queueable trait

    protected Integration $integration;
    protected SyncLog $syncLog;

    public function __construct(Integration $integration, SyncLog $syncLog)
    {
        $this->integration = $integration;
        $this->syncLog = $syncLog;
        $this->onQueue('sync');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string)$this->integration->integration_id))
                ->releaseAfter(60) 
                ->expireAfter(3600) 
        ];
    }

    public function handle(SyncEngine $syncEngine): void
    {
        Log::info('=== SYNC JOB STARTED ===', [
            'integration_id' => $this->integration->integration_id,
            'integration_type' => $this->integration->integration_type,
            'display_name' => $this->integration->display_name,
            'sync_log_id' => $this->syncLog->sync_id,
            'company_id' => $this->integration->company_id,
        ]);

        try {

            // Start Master Lock - prevents workers from completing the sync before we are done dispatching
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $masterKey = "sync_log:{$this->syncLog->sync_id}:master_running";
            $redis->set($masterKey, 1);
            $redis->expire($masterKey, 3600); // 1 hour safety

            // Update status to running immediately
            $this->syncLog->update([
                'status' => 'running',
                'error_messages' => null
            ]);

            // Update Integration status to reflect active sync in UI
            $this->integration->update([
                'last_sync_status' => 'syncing' 
            ]);

            Log::info('Sync status updated to running', [
                'integration_id' => $this->integration->integration_id,
                'sync_log_id' => $this->syncLog->sync_id,
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
            
            Log::info('Loading integration class...', [
                'integration_type' => $this->integration->integration_type,
            ]);
            
            $integrationClass = $this->loadIntegrationClass();

            if (!$integrationClass) {
                throw new \Exception("Integration type {$this->integration->integration_type} not found.");
            }
            
            Log::info('Executing sync() method...', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => $this->integration->integration_type,
            ]);
            
            /** @var IntegrationInterface $integrationClass */
            $result = $integrationClass->sync();
            
            Log::info('Sync() method completed', [
                'integration_id' => $this->integration->integration_id,
                'result' => $result,
            ]);
            
            $recordsSynced = $result['records_synced'] ?? 0;
            
            $metadata = $integrationClass->getMetadata();
            
            $skipHistorical = $this->integration->integration_type === 'Datto';
            
            if (($metadata['supports_historical'] ?? false) && !$skipHistorical) {
                $startDate = now()->subHours(4); 
                $endDate = now();
                
                try {
                    /** @var IntegrationInterface $integrationClass */
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
            
            $recordsFailed = $result['records_failed'] ?? 0;
            $customersMapped = $result['customers_mapped'] ?? [];
            
            Log::info('Integration sync result', [
                'integration_id' => $this->integration->integration_id,
                'records_synced' => $recordsSynced,
                'records_failed' => $recordsFailed,
                'customers_mapped_count' => is_array($customersMapped) ? count($customersMapped) : 0,
                'customers_mapped' => $customersMapped,
                'batches_dispatched' => $result['batches_dispatched'] ?? 0
            ]);
            // DEFER COMPLETION IF BATCHES ARE RUNNING
            if (($result['batches_dispatched'] ?? 0) > 0) {
                 $batchCount = (int) $result['batches_dispatched'];
                 $key = "sync_log:{$this->syncLog->sync_id}:pending";
                 
                 // ATOMIC INCREMENT BEFORE releasing master lock to prevent race condition
                 $redis->incrBy($key, $batchCount);
                 $redis->expire($key, 86400); 

                 // NOW safe to release master lock - workers can now finalize if they hit 0
                 $redis->set($masterKey, 0);

                if ($this->syncLog->fresh()->status === 'running') {
                    $this->syncLog->update([
                        'records_synced' => $recordsSynced
                    ]);
                }
                 
                Log::info('Integration sync deferred - background batches initialized', [
                    'integration_id' => $this->integration->integration_id,
                    'batches_dispatched' => $batchCount,
                    'sync_id' => $this->syncLog->sync_id
                ]);

                // Final check: Maybe all workers finished WHILE we were doing incrBy?
                $remaining = (int) $redis->get($key);
                if ($remaining <= 0) {
                    $this->finalizeSync();
                }
            } else {
                // If no batches, release lock and complete now
                $redis->set($masterKey, 0);
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
            // Ensure lock is released even on failure
            if (isset($masterKey)) {
                \Illuminate\Support\Facades\Redis::connection()->set($masterKey, 0);
            }

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

    /**
     * Finalize sync - only called if Master is done AND workers are done
     */
    private function finalizeSync(): void
    {
        $redis = \Illuminate\Support\Facades\Redis::connection();
        $pendingKey = "sync_log:{$this->syncLog->sync_id}:pending";
        $failedKey  = "sync_log:{$this->syncLog->sync_id}:failed";
        
        $failedCount = (int) $redis->get($failedKey) ?: 0;
        $status = $failedCount > 0 ? 'partial' : 'success';
        
        $syncEngine = app(\App\Services\SyncEngine::class);
        $syncEngine->completeSyncLog(
            $this->syncLog->fresh(),
            $status,
            $this->syncLog->records_synced,
            $failedCount,
            $failedCount > 0 ? ["{$failedCount} batch(es) failed"] : []
        );

        $redis->del($pendingKey, $failedKey);
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

    /**
     * @return IntegrationInterface|null
     */
    private function loadIntegrationClass(): ?IntegrationInterface
    {
        $integrationType = $this->integration->integration_type;
        
        // Dynamically load integration class based on naming convention
        $className = "App\\Integrations\\{$integrationType}\\{$integrationType}Integration";
        
        Log::info('Loading integration class', [
            'integration_id' => $this->integration->integration_id,
            'integration_type' => $integrationType,
            'class_name' => $className,
            'class_exists' => class_exists($className),
        ]);

        if (!class_exists($className)) {
            Log::error('Integration class not found', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => $integrationType,
                'expected_class' => $className,
            ]);
            return null;
        }

        $instance = new $className($this->integration);
        
        // Inject heartbeat callback if supported
        if (method_exists($instance, 'setHeartbeatCallback')) {
            $instance->setHeartbeatCallback(function(int $incrementalRecords = 0) {
                $this->sendHeartbeat($incrementalRecords);
            });
        }

        // Inject SyncLogID if supported (via HasHeartbeat trait)
        if (method_exists($instance, 'setSyncLogId')) {
            $instance->setSyncLogId($this->syncLog->sync_id);
        }
        
        Log::info('Integration class loaded successfully', [
            'integration_id' => $this->integration->integration_id,
            'integration_type' => $integrationType,
        ]);
        
        /** @var IntegrationInterface $instance */
        return $instance;
    }

    public function sendHeartbeat(int $incrementalRecords = 0): void
    {
        $updateData = ['last_heartbeat_at' => now()];
        
        if ($incrementalRecords > 0) {
            \Illuminate\Support\Facades\DB::table('sync_logs')
                ->where('sync_id', $this->syncLog->sync_id)
                ->increment('records_synced', $incrementalRecords, $updateData);
        } else {
            \Illuminate\Support\Facades\DB::table('sync_logs')
                ->where('sync_id', $this->syncLog->sync_id)
                ->update($updateData);
        }
    }
}
