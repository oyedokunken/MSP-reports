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

    public $tries = 5;
    public $timeout = 1800; // 30 minutes - increased for LibreNMS with many devices
    public $backoff = [60, 300, 900, 1800]; // Exponential backoff: 1 min, 5 min, 15 min, 30 min

    protected Integration $integration;
    protected SyncLog $syncLog;

    public function __construct(Integration $integration, SyncLog $syncLog)
    {
        $this->integration = $integration;
        $this->syncLog = $syncLog;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->integration->integration_id))
                ->releaseAfter(60) // Release lock after 60 seconds if job fails
                ->expireAfter(900) // Expire lock after 15 minutes (matches job timeout)
        ];
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

        // Check if integration has any existing data
        $hasExistingData = \App\Models\IntegrationCustomer::where('integration_id', $this->integration->integration_id)
            ->exists();

        // Only mark as error if there's no existing data
        // If data exists, mark as 'active' but with failed sync status
        // This allows reports to continue working with existing data
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
            'ITGlue' => ITGlueIntegration::class,
            'LibreNMS' => LibreNMSIntegration::class,
            'BitDefender' => BitDefenderIntegration::class,
            'SaaS' => SaaSIntegration::class,
        ];

        $className = $integrationClasses[$this->integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($this->integration);
        }

        return null;
    }
}
