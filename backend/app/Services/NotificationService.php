<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send sync failure notification to company admins
     */
    public function sendSyncFailureNotification(Integration $integration, string $errorMessage): void
    {
        $company = $integration->company;
        $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];

        if (empty($notificationEmails)) {
            // Fall back to company admins
            $notificationEmails = $company->users()
                ->whereJsonContains('permissions->integrations->manage', true)
                ->pluck('email')
                ->toArray();
        }

        if (empty($notificationEmails)) {
            Log::warning('No notification recipients for sync failure', [
                'integration_id' => $integration->integration_id,
                'company_id' => $company->company_id,
            ]);
            return;
        }

        try {
            foreach ($notificationEmails as $email) {
                Mail::raw(
                    $this->buildSyncFailureMessage($integration, $errorMessage),
                    function ($message) use ($email, $integration, $company) {
                        $message->to($email)
                            ->subject("[{$company->name}] Integration Sync Failed: {$integration->display_name}");
                    }
                );
            }

            Log::info('Sync failure notification sent', [
                'integration_id' => $integration->integration_id,
                'recipients' => $notificationEmails,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send sync failure notification', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send historical import completion notification
     */
    public function sendImportCompletionNotification(Integration $integration, array $stats): void
    {
        $company = $integration->company;
        $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];

        if (empty($notificationEmails)) {
            return;
        }

        try {
            foreach ($notificationEmails as $email) {
                Mail::raw(
                    $this->buildImportCompletionMessage($integration, $stats),
                    function ($message) use ($email, $integration, $company) {
                        $message->to($email)
                            ->subject("[{$company->name}] Historical Import Complete: {$integration->display_name}");
                    }
                );
            }

            Log::info('Import completion notification sent', [
                'integration_id' => $integration->integration_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send import completion notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send report generation failure notification
     */
    public function sendReportFailureNotification(int $companyId, int $scheduleId, string $errorMessage): void
    {
        $company = Company::find($companyId);
        if (!$company) {
            return;
        }

        $notificationEmails = $company->settings['reports']['failure_notifications'] 
            ?? $company->settings['integrations']['sync_failure_notifications'] 
            ?? [];

        if (empty($notificationEmails)) {
            return;
        }

        try {
            foreach ($notificationEmails as $email) {
                Mail::raw(
                    "Report generation failed for schedule ID: {$scheduleId}\n\nError: {$errorMessage}",
                    function ($message) use ($email, $company) {
                        $message->to($email)
                            ->subject("[{$company->name}] Report Generation Failed");
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send report failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function buildSyncFailureMessage(Integration $integration, string $errorMessage): string
    {
        return <<<MSG
Integration sync has failed.

Integration: {$integration->display_name}
Type: {$integration->integration_type}
Time: {$integration->updated_at}

Error Details:
{$errorMessage}

Please check the integration configuration and try again.

--
MSP Reports Platform
MSG;
    }

    protected function buildImportCompletionMessage(Integration $integration, array $stats): string
    {
        $recordsImported = $stats['records_imported'] ?? 0;
        $duration = $stats['duration'] ?? 'N/A';

        return <<<MSG
Historical data import has completed.

Integration: {$integration->display_name}
Type: {$integration->integration_type}
Records Imported: {$recordsImported}
Duration: {$duration}

The data is now available for reporting.

--
MSP Reports Platform
MSG;
    }
}
