<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Company;
use App\Models\Integration;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Models\ApiKey;
use App\Models\ReportTemplate;
use App\Notifications\SystemAlert;
use App\Notifications\SyncCompleted;
use App\Notifications\ReportGenerated;
use Illuminate\Support\Facades\Log;

class InAppNotificationService
{
    /**
     * Notify all users in a company
     * 
     * @param \Illuminate\Notifications\Notification|mixed $notification
     */
    protected function notifyCompanyUsers(Company $company, $notification, ?string $permission = null): void
    {
        $query = $company->users();
        
        if ($permission) {
            [$section, $action] = explode('.', $permission);
            $query->whereJsonContains("permissions->{$section}->{$action}", true);
        }
        
        $users = $query->get();
        
        foreach ($users as $user) {
            try {
                $user->notify($notification);
            } catch (\Exception $e) {
                Log::error('Failed to send notification', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notify a specific user
     * 
     * @param \Illuminate\Notifications\Notification|mixed $notification
     */
    protected function notifyUser(User $user, $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Integration created notification
     */
    public function integrationCreated(Integration $integration, User $actor): void
    {
        $this->notifyCompanyUsers(
            $integration->company,
            new SystemAlert([
                'type' => 'sync',
                'severity' => 'green',
                'title' => 'Integration Added',
                'description' => "{$actor->name} added a new integration: {$integration->display_name} ({$integration->integration_type})",
                'metadata' => [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->display_name,
                    'integration_type' => $integration->integration_type,
                    'actor' => $actor->name,
                    'action_url' => "/platform/integrations",
                ]
            ]),
            'integrations.view'
        );
    }

    /**
     * Integration sync started notification
     */
    public function syncStarted(Integration $integration, User $actor): void
    {
        $this->notifyCompanyUsers(
            $integration->company,
            new SystemAlert([
                'type' => 'sync',
                'severity' => 'blue',
                'title' => 'Sync Started',
                'description' => "Sync started for {$integration->display_name}",
                'metadata' => [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->display_name,
                    'actor' => $actor->name,
                    'action_url' => "/platform/integrations",
                ]
            ]),
            'integrations.view'
        );
    }

    /**
     * Integration sync completed notification
     */
    public function syncCompleted(Integration $integration, int $recordsSynced, bool $success = true): void
    {
        $this->notifyCompanyUsers(
            $integration->company,
            new SyncCompleted([
                'type' => 'sync',
                'severity' => $success ? 'green' : 'red',
                'title' => $success ? 'Sync Completed' : 'Sync Failed',
                'description' => $success 
                    ? "{$integration->display_name} synced successfully. {$recordsSynced} records processed."
                    : "{$integration->display_name} sync failed. Please check the integration settings.",
                'metadata' => [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->display_name,
                    'records_synced' => $recordsSynced,
                    'success' => $success,
                    'action_url' => "/platform/integrations",
                ]
            ]),
            'integrations.view'
        );
    }

    /**
     * Integration deleted notification
     */
    public function integrationDeleted(string $integrationName, Company $company, User $actor): void
    {
        $this->notifyCompanyUsers(
            $company,
            new SystemAlert([
                'type' => 'sync',
                'severity' => 'yellow',
                'title' => 'Integration Removed',
                'description' => "{$actor->name} removed the integration: {$integrationName}",
                'metadata' => [
                    'integration_name' => $integrationName,
                    'actor' => $actor->name,
                    'action_url' => "/platform/integrations",
                ]
            ]),
            'integrations.view'
        );
    }

    /**
     * Customer created notification
     */
    public function customerCreated(Customer $customer, User $actor): void
    {
        $this->notifyCompanyUsers(
            $customer->company,
            new SystemAlert([
                'type' => 'system',
                'severity' => 'green',
                'title' => 'Customer Added',
                'description' => "{$actor->name} added a new customer: {$customer->canonical_name}",
                'metadata' => [
                    'customer_id' => $customer->customer_id,
                    'customer_name' => $customer->canonical_name,
                    'actor' => $actor->name,
                    'action_url' => "/platform/customers/master",
                ]
            ]),
            'customers.view'
        );
    }

    /**
     * Customer mapped notification
     */
    public function customerMapped(Customer $customer, string $integrationCustomerName, User $actor): void
    {
        $this->notifyCompanyUsers(
            $customer->company,
            new SystemAlert([
                'type' => 'system',
                'severity' => 'blue',
                'title' => 'Customer Mapped',
                'description' => "{$actor->name} mapped '{$integrationCustomerName}' to {$customer->canonical_name}",
                'metadata' => [
                    'customer_id' => $customer->customer_id,
                    'customer_name' => $customer->canonical_name,
                    'integration_customer_name' => $integrationCustomerName,
                    'actor' => $actor->name,
                    'action_url' => "/platform/customers/map",
                ]
            ]),
            'customers.view'
        );
    }

    /**
     * Schedule created notification
     */
    public function scheduleCreated(ReportSchedule $schedule, User $actor): void
    {
        $customerName = $schedule->customer->canonical_name ?? 'Unknown';
        
        $this->notifyCompanyUsers(
            $schedule->company,
            new SystemAlert([
                'type' => 'report',
                'severity' => 'green',
                'title' => 'Report Schedule Created',
                'description' => "{$actor->name} created a " . ($schedule->frequency->value ?? 'custom') . " report schedule for {$customerName}",
                'metadata' => [
                    'schedule_id' => $schedule->report_schedule_id,
                    'customer_name' => $customerName,
                    'frequency' => $schedule->frequency,
                    'report_type' => $schedule->report_type,
                    'actor' => $actor->name,
                    'action_url' => "/platform/schedules",
                ]
            ]),
            'reports.view'
        );
    }

    /**
     * Report generated notification
     */
    public function reportGenerated(int $reportId, string $reportType, string $customerName, Company $company): void
    {
        $this->notifyCompanyUsers(
            $company,
            new ReportGenerated([
                'type' => 'report',
                'severity' => 'green',
                'title' => 'Report Generated',
                'description' => "{$reportType} report generated for {$customerName}",
                'metadata' => [
                    'report_id' => $reportId,
                    'report_type' => $reportType,
                    'customer_name' => $customerName,
                    'action_url' => "/platform/reports",
                ]
            ]),
            'reports.view'
        );
    }

    /**
     * Report sent notification
     */
    public function reportSent(int $reportId, string $reportType, string $customerName, string $destination, Company $company): void
    {
        $this->notifyCompanyUsers(
            $company,
            new SystemAlert([
                'type' => 'report',
                'severity' => 'green',
                'title' => 'Report Delivered',
                'description' => "{$reportType} report for {$customerName} sent to {$destination}",
                'metadata' => [
                    'report_id' => $reportId,
                    'report_type' => $reportType,
                    'customer_name' => $customerName,
                    'destination' => $destination,
                    'action_url' => "/platform/reports",
                ]
            ]),
            'reports.view'
        );
    }

    /**
     * API key created notification
     */
    public function apiKeyCreated(ApiKey $apiKey, User $actor): void
    {
        $this->notifyCompanyUsers(
            $apiKey->company,
            new SystemAlert([
                'type' => 'system',
                'severity' => 'blue',
                'title' => 'API Key Created',
                'description' => "{$actor->name} created a new API key: {$apiKey->display_name}",
                'metadata' => [
                    'api_key_id' => $apiKey->api_key_id,
                    'api_key_name' => $apiKey->display_name,
                    'actor' => $actor->name,
                    'action_url' => "/platform/settings/api-keys",
                ]
            ]),
            'users.manage'
        );
    }

    /**
     * API key revoked notification
     */
    public function apiKeyRevoked(ApiKey $apiKey, User $actor): void
    {
        $this->notifyCompanyUsers(
            $apiKey->company,
            new SystemAlert([
                'type' => 'system',
                'severity' => 'yellow',
                'title' => 'API Key Revoked',
                'description' => "{$actor->name} revoked API key: {$apiKey->display_name}",
                'metadata' => [
                    'api_key_id' => $apiKey->api_key_id,
                    'api_key_name' => $apiKey->display_name,
                    'actor' => $actor->name,
                    'action_url' => "/platform/settings/api-keys",
                ]
            ]),
            'users.manage'
        );
    }

    /**
     * Template created notification
     */
    public function templateCreated(ReportTemplate $template, User $actor): void
    {
        $this->notifyCompanyUsers(
            $template->company,
            new SystemAlert([
                'type' => 'report',
                'severity' => 'green',
                'title' => 'Report Template Created',
                'description' => "{$actor->name} created a new report template: {$template->template_name}",
                'metadata' => [
                    'template_id' => $template->template_id,
                    'template_name' => $template->template_name,
                    'actor' => $actor->name,
                    'action_url' => "/platform/templates",
                ]
            ]),
            'reports.configure'
        );
    }

    /**
     * User created notification
     */
    public function userCreated(User $newUser, User $actor): void
    {
        $this->notifyCompanyUsers(
            $newUser->company,
            new SystemAlert([
                'type' => 'system',
                'severity' => 'green',
                'title' => 'User Added',
                'description' => "{$actor->name} added a new user: {$newUser->name} ({$newUser->email})",
                'metadata' => [
                    'user_id' => $newUser->user_id,
                    'user_name' => $newUser->name,
                    'user_email' => $newUser->email,
                    'actor' => $actor->name,
                    'action_url' => "/platform/users",
                ]
            ]),
            'users.view'
        );
    }

    /**
     * User deleted notification
     */
    public function userDeleted(string $userName, string $userEmail, Company $company, User $actor): void
    {
        $this->notifyCompanyUsers(
            $company,
            new SystemAlert([
                'type' => 'system',
                'severity' => 'yellow',
                'title' => 'User Removed',
                'description' => "{$actor->name} removed user: {$userName} ({$userEmail})",
                'metadata' => [
                    'user_name' => $userName,
                    'user_email' => $userEmail,
                    'actor' => $actor->name,
                    'action_url' => "/platform/users",
                ]
            ]),
            'users.view'
        );
    }

    /**
     * Historical import started notification
     */
    public function historicalImportStarted(Integration $integration, User $actor): void
    {
        $this->notifyCompanyUsers(
            $integration->company,
            new SystemAlert([
                'type' => 'sync',
                'severity' => 'blue',
                'title' => 'Historical Import Started',
                'description' => "Historical data import started for {$integration->display_name}",
                'metadata' => [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->display_name,
                    'actor' => $actor->name,
                    'action_url' => "/platform/integrations",
                ]
            ]),
            'integrations.view'
        );
    }

    /**
     * Historical import completed notification
     */
    public function historicalImportCompleted(Integration $integration, int $recordsImported, bool $success = true): void
    {
        $this->notifyCompanyUsers(
            $integration->company,
            new SystemAlert([
                'type' => 'sync',
                'severity' => $success ? 'green' : 'red',
                'title' => $success ? 'Historical Import Completed' : 'Historical Import Failed',
                'description' => $success 
                    ? "Historical import for {$integration->display_name} completed. {$recordsImported} records imported."
                    : "Historical import for {$integration->display_name} failed.",
                'metadata' => [
                    'integration_id' => $integration->integration_id,
                    'integration_name' => $integration->display_name,
                    'records_imported' => $recordsImported,
                    'success' => $success,
                    'action_url' => "/platform/integrations",
                ]
            ]),
            'integrations.view'
        );
    }

    /**
     * Health check notification
     * 
     * @param array<int, array<string, mixed>> $results
     */
    public function healthCheckCompleted(array $results, Company $company): void
    {
        $allHealthy = collect($results)->every(fn($r) => $r['status'] === 'healthy');
        $failedCount = collect($results)->filter(fn($r) => $r['status'] !== 'healthy')->count();
        
        $this->notifyCompanyUsers(
            $company,
            new SystemAlert([
                'type' => 'system',
                'severity' => $allHealthy ? 'green' : 'yellow',
                'title' => 'Health Check Complete',
                'description' => $allHealthy 
                    ? 'All system components are healthy'
                    : "{$failedCount} component(s) reported issues",
                'metadata' => [
                    'results' => $results,
                    'all_healthy' => $allHealthy,
                    'action_url' => "/admin/status",
                ]
            ]),
            'company_settings.view'
        );
    }
}
