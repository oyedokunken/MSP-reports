<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\Integration;
use App\Models\GeneratedReport;
use App\Notifications\ReportDelivered;
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

    /**
     * Send report delivery confirmation notification to company admins
     */
    public function sendReportDeliveryConfirmation(GeneratedReport $report, array $deliveryResults): void
    {
        $company = $report->company;
        $customer = $report->customer;
        
        if (!$company || !$customer) {
            Log::warning('Cannot send delivery confirmation: missing company or customer', [
                'report_id' => $report->generated_report_id,
            ]);
            return;
        }

        // Get users who should receive delivery confirmations
        // Check company settings first, then fall back to users with report permissions
        $notificationEmails = $company->settings['reports']['delivery_notifications'] ?? [];
        
        if (empty($notificationEmails)) {
            // Get users with report view/generate permissions
            $users = $company->users()
                ->where(function ($query) {
                    $query->whereJsonContains('permissions->reports->view', true)
                          ->orWhereJsonContains('permissions->reports->generate', true);
                })
                ->get();
        } else {
            $users = User::whereIn('email', $notificationEmails)
                ->where('company_id', $company->company_id)
                ->get();
        }

        if ($users->isEmpty()) {
            Log::info('No users to notify for report delivery', [
                'report_id' => $report->generated_report_id,
            ]);
            return;
        }

        // Build delivery summary
        $successMethods = [];
        $destinations = [];
        foreach ($deliveryResults as $method => $result) {
            if ($result['success'] ?? false) {
                $successMethods[] = ucfirst($method);
                if (isset($result['destination'])) {
                    $destinations[] = $result['destination'];
                }
            }
        }

        $reportName = $report->reportSchedule?->report_type ?? 'Report';
        $customerName = $customer->name ?? 'Unknown Customer';
        $deliveryMethodsStr = implode(', ', $successMethods);
        $destinationsStr = implode(', ', $destinations);

        // Send in-app notification to each user
        foreach ($users as $user) {
            try {
                $user->notify(new ReportDelivered([
                    'title' => 'Report Delivered Successfully',
                    'description' => "{$reportName} for {$customerName} was delivered via {$deliveryMethodsStr}",
                    'metadata' => [
                        'report_id' => $report->generated_report_id,
                        'customer_id' => $customer->customer_id,
                        'customer_name' => $customerName,
                        'report_type' => $reportName,
                        'delivery_methods' => $successMethods,
                        'destinations' => $destinations,
                        'delivered_at' => now()->toIso8601String(),
                    ]
                ]));
            } catch (\Exception $e) {
                Log::error('Failed to send delivery notification to user', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also send email confirmation if configured
        $emailNotifications = $company->settings['reports']['email_delivery_confirmations'] ?? false;
        if ($emailNotifications) {
            $this->sendDeliveryConfirmationEmail($report, $customer, $company, $successMethods, $destinationsStr);
        }

        Log::info('Report delivery confirmation sent', [
            'report_id' => $report->generated_report_id,
            'notified_users' => $users->pluck('user_id')->toArray(),
        ]);
    }

    /**
     * Send email confirmation for report delivery
     */
    protected function sendDeliveryConfirmationEmail(
        GeneratedReport $report,
        $customer,
        Company $company,
        array $deliveryMethods,
        string $destinations
    ): void {
        $notificationEmails = $company->settings['reports']['delivery_notifications'] ?? [];
        
        if (empty($notificationEmails)) {
            return;
        }

        $reportName = $report->reportSchedule?->report_type ?? 'Report';
        $customerName = $customer->name ?? 'Unknown Customer';
        $deliveryMethodsStr = implode(', ', $deliveryMethods);
        $deliveredAt = $report->delivered_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');

        $message = <<<MSG
Report Delivery Confirmation

Report: {$reportName}
Customer: {$customerName}
Delivered via: {$deliveryMethodsStr}
Destination: {$destinations}
Delivered at: {$deliveredAt}

This is an automated confirmation that your scheduled report was delivered successfully.

--
MSP Reports Platform
MSG;

        try {
            foreach ($notificationEmails as $email) {
                Mail::raw($message, function ($mail) use ($email, $company, $customerName) {
                    $mail->to($email)
                        ->subject("[{$company->name}] Report Delivered: {$customerName}");
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send delivery confirmation email', [
                'report_id' => $report->generated_report_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
