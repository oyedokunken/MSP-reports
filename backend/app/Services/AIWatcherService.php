<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Notifications\AIWatcherMessage;
use Illuminate\Support\Facades\Log;

class AIWatcherService
{
    /**
     * Event type to AI message configuration mapping.
     * Each entry defines the AI assistant's contextual response for that event.
     *
     * @return array<string, array{title: string, message: string, doc_path?: string, doc_label?: string, severity: string}>
     */
    protected function getEventConfig(): array
    {
        return [
            // Integration events
            'integration_created' => [
                'title' => 'Integration Added',
                'message' => "Your new integration **{name}** has been added successfully! 🎉\n\nNext steps:\n• Run your first sync to pull in customer data\n• Set up customer mapping once data arrives\n• Configure historical imports for trend analysis\n\n[Learn more about integrations](/docs/integrations)",
                'doc_path' => '/docs/integrations',
                'doc_label' => 'Integration Guide',
                'severity' => 'green',
            ],
            'integration_updated' => [
                'title' => 'Integration Updated',
                'message' => "Integration **{name}** has been updated.\n\nIf you changed credentials, consider running a test sync to verify the connection.\n\n[View integration settings](/platform/integrations)",
                'doc_path' => '/docs/integrations',
                'doc_label' => 'Integration Guide',
                'severity' => 'blue',
            ],
            'integration_deleted' => [
                'title' => 'Integration Removed',
                'message' => "Integration **{name}** has been removed.\n\n⚠️ Any customer mappings from this integration will need to be reviewed. Historical data from this integration is retained per your retention policy.\n\n[Review customer mappings](/platform/customers/map)",
                'severity' => 'yellow',
            ],
            'integration_synced' => [
                'title' => 'Sync Started',
                'message' => "A sync has been triggered for **{name}**. This runs in the background and you'll be notified when it completes.\n\n[View sync status](/platform/integrations)",
                'severity' => 'blue',
            ],
            'integration_source_of_truth_changed' => [
                'title' => 'Source of Truth Changed',
                'message' => "**{name}** is now your source of truth for customer names.\n\nThis means customer names from this integration will be treated as canonical. Other integrations' customers will map to these names.\n\n[Learn about source of truth](/docs/customers#source-of-truth)",
                'doc_path' => '/docs/customers#source-of-truth',
                'doc_label' => 'Source of Truth Guide',
                'severity' => 'blue',
            ],

            // Historical import events
            'historical_import_queued' => [
                'title' => 'Historical Import Queued',
                'message' => "Historical data import has been queued for **{name}**. This will run in the background and may take several hours depending on the data volume.\n\n💡 **Tip:** Recent data is imported first so you can start using it while older data backfills.\n\n[Monitor import progress](/platform/integrations)",
                'doc_path' => '/docs/integrations#historical-imports',
                'doc_label' => 'Historical Import Guide',
                'severity' => 'blue',
            ],

            // Customer events
            'customer_created' => [
                'title' => 'Customer Added',
                'message' => "New customer **{name}** has been created! 🎉\n\nNext steps:\n• Map integration records to this customer\n• Set up report schedules\n• Configure delivery destinations\n\n[Manage customers](/platform/customers/master)",
                'doc_path' => '/docs/customers',
                'doc_label' => 'Customer Management Guide',
                'severity' => 'green',
            ],
            'customer_updated' => [
                'title' => 'Customer Updated',
                'message' => "Customer **{name}** has been updated.\n\n[View customer details](/platform/customers/master)",
                'severity' => 'blue',
            ],
            'customer_batch_auto_matched' => [
                'title' => 'Auto-Match Complete',
                'message' => "Batch auto-matching has been run. Check the results to verify the mappings are correct.\n\n💡 **Tip:** You can adjust the matching threshold in [Company Settings](/admin/settings) to fine-tune accuracy.\n\n[Review mappings](/platform/customers/map)",
                'doc_path' => '/docs/customers#auto-matching',
                'doc_label' => 'Auto-Match Guide',
                'severity' => 'green',
            ],

            // User events
            'user_created' => [
                'title' => 'User Added',
                'message' => "New user **{name}** has been added to the platform.\n\nMake sure their permissions are configured correctly for their role.\n\n[Manage users](/admin/users)",
                'doc_path' => '/docs/users',
                'doc_label' => 'User Management Guide',
                'severity' => 'green',
            ],
            'user_deleted' => [
                'title' => 'User Removed',
                'message' => "User **{name}** has been removed from the platform.\n\nTheir audit history is preserved for compliance purposes.\n\n[View audit logs](/admin/audit-logs)",
                'severity' => 'yellow',
            ],
            'user_permissions_updated' => [
                'title' => 'Permissions Updated',
                'message' => "Permissions have been updated for **{name}**.\n\nChanges take effect immediately on their next page load.\n\n[Learn about permissions](/docs/users#permissions)",
                'doc_path' => '/docs/users#permissions',
                'doc_label' => 'Permissions Guide',
                'severity' => 'blue',
            ],

            // Report events
            'report_generated' => [
                'title' => 'Report Generated',
                'message' => "A report has been generated successfully! 📊\n\nYou can download it from the reports page or configure automatic email delivery.\n\n[View reports](/platform/reports)",
                'doc_path' => '/docs/reports',
                'doc_label' => 'Reports Guide',
                'severity' => 'green',
            ],
            'report_bulk_generated' => [
                'title' => 'Bulk Reports Generated',
                'message' => "Bulk report generation has been triggered. Reports will be generated in the background.\n\n[Monitor progress](/platform/reports)",
                'severity' => 'blue',
            ],
            'report_emailed' => [
                'title' => 'Report Emailed',
                'message' => "A report has been emailed to the configured recipients. ✉️\n\n[View delivery status](/platform/reports)",
                'severity' => 'green',
            ],

            // Template events
            'template_created' => [
                'title' => 'Template Created',
                'message' => "Report template **{name}** has been created! 🎨\n\nYou can now apply this template to customers to create report schedules.\n\n[Apply template to customers](/platform/templates)",
                'doc_path' => '/docs/templates',
                'doc_label' => 'Template Guide',
                'severity' => 'green',
            ],
            'template_applied' => [
                'title' => 'Template Applied',
                'message' => "Template **{name}** has been applied to a customer. A report schedule has been created automatically.\n\n[View schedules](/platform/schedules)",
                'severity' => 'green',
            ],
            'template_bulk_applied' => [
                'title' => 'Template Bulk Applied',
                'message' => "Template **{name}** has been applied to multiple customers. Report schedules have been created for each.\n\n[View schedules](/platform/schedules)",
                'severity' => 'green',
            ],

            // Schedule events
            'schedule_updated' => [
                'title' => 'Schedule Updated',
                'message' => "A report schedule has been updated. The next report will generate according to the new settings.\n\n[View schedules](/platform/schedules)",
                'severity' => 'blue',
            ],
            'schedule_reset' => [
                'title' => 'Schedule Reset',
                'message' => "A report schedule has been reset to its template defaults.\n\n[View schedules](/platform/schedules)",
                'severity' => 'blue',
            ],

            // Company/Settings events
            'settings_updated' => [
                'title' => 'Settings Updated',
                'message' => "Company settings have been updated. Changes take effect immediately.\n\n[Review settings](/admin/settings)",
                'doc_path' => '/docs/settings',
                'doc_label' => 'Settings Guide',
                'severity' => 'blue',
            ],
            'branding_updated' => [
                'title' => 'Branding Updated',
                'message' => "Your white-label branding has been updated! 🎨\n\nThe new branding will appear on all future reports and emails.\n\n[Preview branding](/admin/settings)",
                'doc_path' => '/docs/branding',
                'doc_label' => 'Branding Guide',
                'severity' => 'green',
            ],
            'logo_uploaded' => [
                'title' => 'Logo Updated',
                'message' => "Your company logo has been updated! It will appear on reports, emails, and the platform header.\n\n[View branding settings](/admin/settings)",
                'severity' => 'green',
            ],

            // API Key events
            'api_key_created' => [
                'title' => 'API Key Created',
                'message' => "A new API key has been created. 🔑\n\n⚠️ **Important:** Make sure to copy the key now — it won't be shown again!\n\nUse this key for programmatic access to the platform API.\n\n[Learn about API keys](/docs/api-keys)",
                'doc_path' => '/docs/api-keys',
                'doc_label' => 'API Key Guide',
                'severity' => 'blue',
            ],
            'api_key_revoked' => [
                'title' => 'API Key Revoked',
                'message' => "An API key has been revoked. Any applications using this key will immediately lose access.\n\n[Manage API keys](/admin/api-keys)",
                'severity' => 'yellow',
            ],

            // Auth events
            'user_login' => [
                'title' => 'Welcome Back',
                'message' => "Welcome back, **{name}**! 👋\n\nHere's a quick overview of what's happening:\n• Check [Integrations](/platform/integrations) for sync status\n• View [Reports](/platform/reports) for recent generations\n• Review [Notifications](/platform/notifications) for updates",
                'severity' => 'green',
            ],
        ];
    }

    /**
     * Process an audit event and send an AI watcher message if applicable.
     *
     * @param int $companyId
     * @param string $eventType
     * @param array<string, mixed> $eventData
     * @param int|null $actorId The user who triggered the event (receives the AI message)
     */
    public function processAuditEvent(int $companyId, string $eventType, array $eventData = [], ?int $actorId = null): void
    {
        try {
            $config = $this->getEventConfig();

            if (!isset($config[$eventType])) {
                return;
            }

            $eventConfig = $config[$eventType];

            // Resolve the name placeholder from event data
            $name = $eventData['integration_name']
                ?? $eventData['customer_name']
                ?? $eventData['user_name']
                ?? $eventData['template_name']
                ?? $eventData['name']
                ?? '';

            $message = str_replace('{name}', $name, $eventConfig['message']);

            // Find the actor (user who triggered the event) to send them the AI message
            $user = $actorId ? User::find($actorId) : auth()->user();

            if (!$user) {
                return;
            }

            $user->notify(new AIWatcherMessage([
                'type' => 'ai_watcher',
                'severity' => $eventConfig['severity'],
                'title' => '🤖 ' . $eventConfig['title'],
                'description' => $message,
                'metadata' => [
                    'event_type' => $eventType,
                    'doc_path' => $eventConfig['doc_path'] ?? null,
                    'doc_label' => $eventConfig['doc_label'] ?? null,
                    'is_ai_watcher' => true,
                    ...$eventData,
                ],
            ]));
        } catch (\Throwable $e) {
            Log::warning('AI Watcher failed to process event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
