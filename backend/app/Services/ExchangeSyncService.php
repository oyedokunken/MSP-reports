<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ExchangeEmailAlias;
use App\Models\ExchangeMailbox;
use App\Models\ExchangeMailboxPermission;
use App\Models\ExchangeMailboxStatistic;
use App\Models\Integration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExchangeSyncService
{
    /**
     * Get or create the Exchange integration for a company
     * There should only be ONE Exchange integration per company
     */
    public function getOrCreateIntegration(int $companyId): Integration
    {
        $integration = Integration::where('company_id', $companyId)
            ->where('integration_type', 'Exchange')
            ->first();

        if (!$integration) {
            $integration = Integration::create([
                'company_id' => $companyId,
                'integration_type' => 'Exchange',
                'display_name' => 'Microsoft Exchange Server',
                'status' => 'active',
                'credentials' => [],  // No credentials - uses API keys per customer
                'configuration' => [],
            ]);

            Log::info('Created Exchange integration', [
                'company_id' => $companyId,
                'integration_id' => $integration->integration_id,
            ]);
        }

        return $integration;
    }

    /**
     * Process sync data from Exchange agent
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param string $syncType
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     * @return array{mailboxes: int, aliases: int, permissions: int, statistics: int}
     */
    public function processSyncData(
        Integration $integration,
        int $customerId,
        string $syncType,
        array $data,
        array $metadata
    ): array {
        DB::beginTransaction();

        try {
            $result = [
                'mailboxes' => 0,
                'aliases' => 0,
                'permissions' => 0,
                'statistics' => 0,
            ];

            // Process mailboxes
            if (!empty($data['mailboxes'])) {
                $result['mailboxes'] = $this->processMailboxes(
                    $integration,
                    $customerId,
                    $data['mailboxes']
                );
            }

            // Process email aliases
            if (!empty($data['emailAliases'])) {
                $result['aliases'] = $this->processEmailAliases(
                    $integration,
                    $customerId,
                    $data['emailAliases']
                );
            }

            // Process permissions
            if (!empty($data['permissions'])) {
                $result['permissions'] = $this->processPermissions(
                    $integration,
                    $customerId,
                    $data['permissions']
                );
            }

            // Process statistics
            if (!empty($data['statistics'])) {
                $result['statistics'] = $this->processStatistics(
                    $integration,
                    $customerId,
                    $data['statistics'],
                    $metadata['sync_timestamp']
                );
            }

            // Update integration last sync timestamp
            $integration->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
            ]);

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();

            $integration->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process mailboxes from sync data
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $mailboxes
     * @return int
     */
    protected function processMailboxes(Integration $integration, int $customerId, array $mailboxes): int
    {
        $processed = 0;

        foreach ($mailboxes as $mailboxData) {
            try {
                // Convert sizes from bytes to MB
                $totalItemSizeMB = isset($mailboxData['totalItemSize'])
                    ? round($mailboxData['totalItemSize'] / 1024 / 1024, 2)
                    : null;

                $totalDeletedItemSizeMB = isset($mailboxData['totalDeletedItemSize'])
                    ? round($mailboxData['totalDeletedItemSize'] / 1024 / 1024, 2)
                    : null;

                $archiveSizeMB = isset($mailboxData['archiveSize'])
                    ? round($mailboxData['archiveSize'] / 1024 / 1024, 2)
                    : null;

                ExchangeMailbox::updateOrCreate(
                    [
                        'company_id' => $integration->company_id,
                        'customer_id' => $customerId,
                        'guid' => $mailboxData['guid'],
                    ],
                    [
                        'integration_id' => $integration->integration_id,
                        'identity' => $mailboxData['identity'],
                        'display_name' => $mailboxData['displayName'],
                        'alias' => $mailboxData['alias'] ?? null,
                        'primary_smtp_address' => $mailboxData['primarySmtpAddress'],
                        'user_principal_name' => $mailboxData['userPrincipalName'] ?? null,
                        'sam_account_name' => $mailboxData['samAccountName'] ?? null,
                        'recipient_type' => $mailboxData['recipientType'],
                        'recipient_type_details' => $mailboxData['recipientTypeDetails'],
                        'is_shared' => $mailboxData['isShared'] ?? false,
                        'is_resource' => $mailboxData['isResource'] ?? false,
                        'resource_type' => $mailboxData['resourceType'] ?? null,
                        'hidden_from_address_lists' => $mailboxData['hiddenFromAddressLists'] ?? false,
                        'external_directory_object_id' => $mailboxData['externalDirectoryObjectId'] ?? null,
                        'database_name' => $mailboxData['database'] ?? null,
                        'server_name' => $mailboxData['serverName'] ?? null,
                        'distinguished_name' => $mailboxData['distinguishedName'] ?? null,
                        'license_type' => $mailboxData['licenseType'] ?? null,
                        'assigned_licenses' => $mailboxData['assignedLicenses'] ?? null,
                        'prohibit_send_quota_mb' => isset($mailboxData['prohibitSendQuota'])
                            ? round($mailboxData['prohibitSendQuota'] / 1024 / 1024, 2)
                            : null,
                        'prohibit_send_receive_quota_mb' => isset($mailboxData['prohibitSendReceiveQuota'])
                            ? round($mailboxData['prohibitSendReceiveQuota'] / 1024 / 1024, 2)
                            : null,
                        'issue_warning_quota_mb' => isset($mailboxData['issueWarningQuota'])
                            ? round($mailboxData['issueWarningQuota'] / 1024 / 1024, 2)
                            : null,
                        'use_database_quota_defaults' => $mailboxData['useDatabaseQuotaDefaults'] ?? true,
                        'total_item_size_mb' => $totalItemSizeMB,
                        'total_deleted_item_size_mb' => $totalDeletedItemSizeMB,
                        'item_count' => $mailboxData['itemCount'] ?? null,
                        'deleted_item_count' => $mailboxData['deletedItemCount'] ?? null,
                        'last_logon_time' => $this->parseDate($mailboxData['lastLogonTime'] ?? null),
                        'last_logoff_time' => $this->parseDate($mailboxData['lastLogoffTime'] ?? null),
                        'archive_enabled' => $mailboxData['archiveEnabled'] ?? false,
                        'archive_guid' => $mailboxData['archiveGuid'] ?? null,
                        'archive_name' => $mailboxData['archiveName'] ?? null,
                        'archive_database' => $mailboxData['archiveDatabase'] ?? null,
                        'archive_quota_mb' => isset($mailboxData['archiveQuota'])
                            ? round($mailboxData['archiveQuota'] / 1024 / 1024, 2)
                            : null,
                        'archive_warning_quota_mb' => isset($mailboxData['archiveWarningQuota'])
                            ? round($mailboxData['archiveWarningQuota'] / 1024 / 1024, 2)
                            : null,
                        'archive_size_mb' => $archiveSizeMB,
                        'litigation_hold_enabled' => $mailboxData['litigationHoldEnabled'] ?? false,
                        'litigation_hold_date' => $this->parseDate($mailboxData['litigationHoldDate'] ?? null),
                        'litigation_hold_owner' => $mailboxData['litigationHoldOwner'] ?? null,
                        'retention_policy' => $mailboxData['retentionPolicy'] ?? null,
                        'retention_comment' => $mailboxData['retentionComment'] ?? null,
                        'retention_url' => $mailboxData['retentionUrl'] ?? null,
                        'forwarding_address' => $mailboxData['forwardingAddress'] ?? null,
                        'forwarding_smtp_address' => $mailboxData['forwardingSmtpAddress'] ?? null,
                        'deliver_to_mailbox_and_forward' => $mailboxData['deliverToMailboxAndForward'] ?? false,
                        'organizational_unit' => $mailboxData['organizationalUnit'] ?? null,
                        'office' => $mailboxData['office'] ?? null,
                        'department' => $mailboxData['department'] ?? null,
                        'company' => $mailboxData['company'] ?? null,
                        'custom_attribute_1' => $mailboxData['customAttribute1'] ?? null,
                        'custom_attribute_2' => $mailboxData['customAttribute2'] ?? null,
                        'custom_attribute_3' => $mailboxData['customAttribute3'] ?? null,
                        'when_created' => $this->parseDate($mailboxData['whenCreated'] ?? null),
                        'when_changed' => $this->parseDate($mailboxData['whenChanged'] ?? null),
                        'exchange_version' => $mailboxData['exchangeVersion'] ?? null,
                    ]
                );

                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process Exchange mailbox', [
                    'company_id' => $integration->company_id,
                    'customer_id' => $customerId,
                    'guid' => $mailboxData['guid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process email aliases from sync data
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $aliases
     * @return int
     */
    protected function processEmailAliases(Integration $integration, int $customerId, array $aliases): int
    {
        $processed = 0;

        // Group aliases by mailbox GUID
        $aliasesByMailbox = [];
        foreach ($aliases as $aliasData) {
            $guid = $aliasData['mailboxGuid'];
            if (!isset($aliasesByMailbox[$guid])) {
                $aliasesByMailbox[$guid] = [];
            }
            $aliasesByMailbox[$guid][] = $aliasData;
        }

        foreach ($aliasesByMailbox as $mailboxGuid => $mailboxAliases) {
            try {
                // Find the mailbox
                $mailbox = ExchangeMailbox::where('company_id', $integration->company_id)
                    ->where('customer_id', $customerId)
                    ->where('guid', $mailboxGuid)
                    ->first();

                if (!$mailbox) {
                    Log::warning('Mailbox not found for aliases', [
                        'company_id' => $integration->company_id,
                        'customer_id' => $customerId,
                        'mailbox_guid' => $mailboxGuid,
                    ]);
                    continue;
                }

                // Delete existing aliases for this mailbox
                ExchangeEmailAlias::where('company_id', $integration->company_id)
                    ->where('customer_id', $customerId)
                    ->where('mailbox_id', $mailbox->id)
                    ->delete();

                // Create new aliases
                foreach ($mailboxAliases as $aliasData) {
                    ExchangeEmailAlias::create([
                        'company_id' => $integration->company_id,
                        'integration_id' => $integration->integration_id,
                        'customer_id' => $customerId,
                        'mailbox_id' => $mailbox->id,
                        'email_address' => $aliasData['emailAddress'],
                        'address_type' => $aliasData['addressType'] ?? 'SMTP',
                        'is_primary' => $aliasData['isPrimary'] ?? false,
                    ]);

                    $processed++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process Exchange email aliases', [
                    'company_id' => $integration->company_id,
                    'customer_id' => $customerId,
                    'mailbox_guid' => $mailboxGuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process mailbox permissions from sync data
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $permissions
     * @return int
     */
    protected function processPermissions(Integration $integration, int $customerId, array $permissions): int
    {
        $processed = 0;

        // Group permissions by mailbox GUID
        $permissionsByMailbox = [];
        foreach ($permissions as $permData) {
            $guid = $permData['mailboxGuid'];
            if (!isset($permissionsByMailbox[$guid])) {
                $permissionsByMailbox[$guid] = [];
            }
            $permissionsByMailbox[$guid][] = $permData;
        }

        foreach ($permissionsByMailbox as $mailboxGuid => $mailboxPermissions) {
            try {
                // Find the mailbox
                $mailbox = ExchangeMailbox::where('company_id', $integration->company_id)
                    ->where('customer_id', $customerId)
                    ->where('guid', $mailboxGuid)
                    ->first();

                if (!$mailbox) {
                    Log::warning('Mailbox not found for permissions', [
                        'company_id' => $integration->company_id,
                        'customer_id' => $customerId,
                        'mailbox_guid' => $mailboxGuid,
                    ]);
                    continue;
                }

                // Delete existing permissions for this mailbox
                ExchangeMailboxPermission::where('company_id', $integration->company_id)
                    ->where('customer_id', $customerId)
                    ->where('mailbox_id', $mailbox->id)
                    ->delete();

                // Create new permissions
                foreach ($mailboxPermissions as $permData) {
                    ExchangeMailboxPermission::create([
                        'company_id' => $integration->company_id,
                        'integration_id' => $integration->integration_id,
                        'customer_id' => $customerId,
                        'mailbox_id' => $mailbox->id,
                        'permission_type' => $permData['permissionType'],
                        'user_identity' => $permData['userIdentity'],
                        'user_sam_account_name' => $permData['userSamAccountName'] ?? null,
                        'user_display_name' => $permData['userDisplayName'] ?? null,
                        'access_rights' => $permData['accessRights'] ?? null,
                        'inherited' => $permData['inherited'] ?? false,
                        'deny' => $permData['deny'] ?? false,
                        'automapping' => $permData['automapping'] ?? true,
                    ]);

                    $processed++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to process Exchange mailbox permissions', [
                    'company_id' => $integration->company_id,
                    'customer_id' => $customerId,
                    'mailbox_guid' => $mailboxGuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process mailbox statistics from sync data
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $statistics
     * @param string $syncTimestamp
     * @return int
     */
    protected function processStatistics(Integration $integration, int $customerId, array $statistics, string $syncTimestamp): int
    {
        $processed = 0;

        foreach ($statistics as $statData) {
            try {
                // Find the mailbox
                $mailbox = ExchangeMailbox::where('company_id', $integration->company_id)
                    ->where('customer_id', $customerId)
                    ->where('guid', $statData['mailboxGuid'])
                    ->first();

                if (!$mailbox) {
                    Log::warning('Mailbox not found for statistics', [
                        'company_id' => $integration->company_id,
                        'customer_id' => $customerId,
                        'mailbox_guid' => $statData['mailboxGuid'],
                    ]);
                    continue;
                }

                // Convert sizes from bytes to MB
                $totalItemSizeMB = isset($statData['totalItemSize'])
                    ? round($statData['totalItemSize'] / 1024 / 1024, 2)
                    : null;

                $totalDeletedItemSizeMB = isset($statData['totalDeletedItemSize'])
                    ? round($statData['totalDeletedItemSize'] / 1024 / 1024, 2)
                    : null;

                // Create statistics record
                ExchangeMailboxStatistic::create([
                    'company_id' => $integration->company_id,
                    'integration_id' => $integration->integration_id,
                    'customer_id' => $customerId,
                    'mailbox_id' => $mailbox->id,
                    'total_item_size_mb' => $totalItemSizeMB,
                    'total_deleted_item_size_mb' => $totalDeletedItemSizeMB,
                    'item_count' => $statData['itemCount'] ?? null,
                    'deleted_item_count' => $statData['deletedItemCount'] ?? null,
                    'associated_item_count' => $statData['associatedItemCount'] ?? null,
                    'folder_count' => $statData['folderCount'] ?? null,
                    'database_name' => $statData['database'] ?? null,
                    'database_guid' => $statData['databaseGuid'] ?? null,
                    'is_archival' => $statData['isArchival'] ?? false,
                    'last_logon_time' => $this->parseDate($statData['lastLogonTime'] ?? null),
                    'last_logoff_time' => $this->parseDate($statData['lastLogoffTime'] ?? null),
                    'last_logon_user_account' => $statData['lastLogonUserAccount'] ?? null,
                    'last_logged_on_user_account' => $statData['lastLoggedOnUserAccount'] ?? null,
                    'statistics_timestamp' => Carbon::parse($syncTimestamp),
                ]);

                // Also update the mailbox with latest statistics
                $mailbox->update([
                    'total_item_size_mb' => $totalItemSizeMB,
                    'total_deleted_item_size_mb' => $totalDeletedItemSizeMB,
                    'item_count' => $statData['itemCount'] ?? null,
                    'deleted_item_count' => $statData['deletedItemCount'] ?? null,
                    'last_logon_time' => $this->parseDate($statData['lastLogonTime'] ?? null),
                    'last_logoff_time' => $this->parseDate($statData['lastLogoffTime'] ?? null),
                ]);

                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process Exchange mailbox statistics', [
                    'company_id' => $integration->company_id,
                    'customer_id' => $customerId,
                    'mailbox_guid' => $statData['mailboxGuid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Get status for a specific customer
     * 
     * @param Integration $integration
     * @param int $customerId
     * @return array{total_mailboxes: int, user_mailboxes: int, shared_mailboxes: int, resource_mailboxes: int, total_storage_mb: float, total_storage_gb: float, last_sync: string|null, last_sync_status: string|null}
     */
    public function getCustomerStatus(Integration $integration, int $customerId): array
    {
        $mailboxCount = ExchangeMailbox::where('company_id', $integration->company_id)
            ->where('customer_id', $customerId)
            ->count();

        $userMailboxCount = ExchangeMailbox::where('company_id', $integration->company_id)
            ->where('customer_id', $customerId)
            ->userMailboxes()
            ->count();

        $sharedMailboxCount = ExchangeMailbox::where('company_id', $integration->company_id)
            ->where('customer_id', $customerId)
            ->sharedMailboxes()
            ->count();

        $resourceMailboxCount = ExchangeMailbox::where('company_id', $integration->company_id)
            ->where('customer_id', $customerId)
            ->resourceMailboxes()
            ->count();

        $totalStorageMB = ExchangeMailbox::where('company_id', $integration->company_id)
            ->where('customer_id', $customerId)
            ->sum('total_item_size_mb');

        return [
            'total_mailboxes' => $mailboxCount,
            'user_mailboxes' => $userMailboxCount,
            'shared_mailboxes' => $sharedMailboxCount,
            'resource_mailboxes' => $resourceMailboxCount,
            'total_storage_mb' => $totalStorageMB,
            'total_storage_gb' => round($totalStorageMB / 1024, 2),
            'last_sync' => $integration->last_sync_at?->toIso8601String(),
            'last_sync_status' => $integration->last_sync_status,
        ];
    }

    /**
     * Parse date string to Carbon instance
     * 
     * @param string|null $date
     * @return \Carbon\Carbon|null
     */
    private function parseDate(?string $date)
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
