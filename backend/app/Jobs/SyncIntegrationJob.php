<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\SyncEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Integrations\Autotask\AutotaskIntegration;
use App\Integrations\Datto\DattoIntegration;
use App\Integrations\Sendmarc\SendmarcIntegration;
use App\Mail\IntegrationSyncFailedMail;

class SyncIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [60, 300, 900]; // Exponential backoff: 1 min, 5 min, 15 min

    protected Integration $integration;
    protected SyncLog $syncLog;

    public function __construct(Integration $integration, SyncLog $syncLog)
    {
        $this->integration = $integration;
        $this->syncLog = $syncLog;
    }

    public function handle(SyncEngine $syncEngine): void
    {
        try {

            $this->syncLog->update(['status' => 'running']);
            
            $integrationClass = $this->loadIntegrationClass();

            if (!$integrationClass) {
                throw new \Exception("Integration type {$this->integration->integration_type} not found.");
            }
            
            // Rate limiting now handled at API client level (AutotaskClient, DattoClient)
            // Each individual API call is rate-limited, providing accurate tracking
            
            $result = $integrationClass->sync();
            
            $recordsSynced = $result['records_synced'] ?? 0;
            $recordsFailed = $result['records_failed'] ?? 0;
            $customersMapped = $result['customers_mapped'] ?? [];
            
            Log::info('Integration sync result', [
                'integration_id' => $this->integration->integration_id,
                'records_synced' => $recordsSynced,
                'records_failed' => $recordsFailed,
                'customers_mapped_count' => count($customersMapped),
                'customers_mapped' => $customersMapped,
            ]);
            
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
        Log::error('Integration sync job failed permanently after all retries', [
            'integration_id' => $this->integration->integration_id,
            'integration_type' => $this->integration->integration_type,
            'company_id' => $this->integration->company_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Update sync log
        $this->syncLog->update([
            'status' => 'failed',
            'error_messages' => [$exception->getMessage()],
            'completed_at' => now(),
        ]);

        // Mark integration as error after 3 failures
        $this->integration->update([
            'status' => 'error',
            'last_sync_status' => 'failed',
            'last_sync_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);

        // Send notification email to company admins
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
    }

    private function loadIntegrationClass()
    {
        $integrationClasses = [
            'Autotask' => AutotaskIntegration::class,
            'Datto' => DattoIntegration::class,
            'Sendmarc' => SendmarcIntegration::class,
        ];

        $className = $integrationClasses[$this->integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($this->integration);
        }

        return null;
    }
}
