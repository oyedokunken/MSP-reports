<?php

namespace App\Services;

use App\Models\ActiveDirectoryGroup;
use App\Models\ActiveDirectoryGroupMembership;
use App\Models\ActiveDirectoryLicenseAssignment;
use App\Models\ActiveDirectoryUser;
use App\Models\Company;
use App\Models\Integration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActiveDirectorySyncService
{
    /**
     * Get or create single Active Directory integration for this company
     */
    public function getOrCreateIntegration(int $companyId): Integration
    {
        return Integration::firstOrCreate(
            [
                'company_id' => $companyId,
                'integration_type' => 'ActiveDirectory',
            ],
            [
                'display_name' => 'Active Directory',
                'status' => 'active',
                'configuration' => [],
            ]
        );
    }

    /**
     * Process users from sync payload
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $users
     * @return int
     */
    public function processUsers(Integration $integration, int $customerId, array $users): int
    {
        $processed = 0;

        foreach ($users as $userData) {
            try {
                ActiveDirectoryUser::updateOrCreate(
                    [
                        'company_id' => $integration->company_id,
                        'customer_id' => $customerId,
                        'user_principal_name' => $userData['userPrincipalName'],
                    ],
                    [
                        'integration_id' => $integration->integration_id,
                        'sam_account_name' => $userData['samAccountName'] ?? null,
                        'display_name' => $userData['displayName'] ?? null,
                        'given_name' => $userData['givenName'] ?? null,
                        'surname' => $userData['surname'] ?? null,
                        'email' => $userData['email'] ?? null,
                        'department' => $userData['department'] ?? null,
                        'title' => $userData['title'] ?? null,
                        'manager' => $userData['manager'] ?? null,
                        'manager_dn' => $userData['managerDN'] ?? null,
                        'enabled' => $userData['enabled'] ?? true,
                        'last_logon' => $this->parseDate($userData['lastLogon'] ?? null),
                        'password_last_set' => $this->parseDate($userData['passwordLastSet'] ?? null),
                        'password_never_expires' => $userData['passwordNeverExpires'] ?? false,
                        'account_expiration' => $this->parseDate($userData['accountExpiration'] ?? null),
                        'locked_out' => $userData['lockedOut'] ?? false,
                        'created_at_ad' => $this->parseDate($userData['created']),
                        'modified_at_ad' => $this->parseDate($userData['modified']),
                        'distinguished_name' => $userData['distinguishedName'] ?? null,
                        'description' => $userData['description'] ?? null,
                        'office' => $userData['office'] ?? null,
                        'office_phone' => $userData['officePhone'] ?? null,
                        'mobile_phone' => $userData['mobilePhone'] ?? null,
                        'company' => $userData['company'] ?? null,
                    ]
                );

                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process AD user', [
                    'upn' => $userData['userPrincipalName'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process groups from sync payload
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $groups
     * @return int
     */
    public function processGroups(Integration $integration, int $customerId, array $groups): int
    {
        $processed = 0;

        foreach ($groups as $groupData) {
            try {
                ActiveDirectoryGroup::updateOrCreate(
                    [
                        'company_id' => $integration->company_id,
                        'customer_id' => $customerId,
                        'distinguished_name' => $groupData['distinguishedName'],
                    ],
                    [
                        'integration_id' => $integration->integration_id,
                        'name' => $groupData['name'],
                        'sam_account_name' => $groupData['samAccountName'] ?? null,
                        'group_category' => $groupData['groupCategory'] ?? 'Security',
                        'group_scope' => $groupData['groupScope'] ?? 'Global',
                        'description' => $groupData['description'] ?? null,
                        'created_at_ad' => $this->parseDate($groupData['created']),
                        'modified_at_ad' => $this->parseDate($groupData['modified']),
                        'managed_by' => $groupData['managedBy'] ?? null,
                        'managed_by_dn' => $groupData['managedByDN'] ?? null,
                    ]
                );

                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process AD group', [
                    'name' => $groupData['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process group memberships from sync payload
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $memberships
     * @return int
     */
    public function processMemberships(Integration $integration, int $customerId, array $memberships): int
    {
        $processed = 0;

        // For full sync, remove all existing memberships for this customer first
        // This handles removed memberships
        ActiveDirectoryGroupMembership::where('company_id', $integration->company_id)
            ->where('integration_id', $integration->integration_id)
            ->where('customer_id', $customerId)
            ->delete();

        foreach ($memberships as $membershipData) {
            try {
                ActiveDirectoryGroupMembership::create([
                    'company_id' => $integration->company_id,
                    'integration_id' => $integration->integration_id,
                    'customer_id' => $customerId,
                    'group_name' => $membershipData['groupName'],
                    'group_dn' => $membershipData['groupDN'],
                    'user_sam_account_name' => $membershipData['userSamAccountName'],
                    'user_dn' => $membershipData['userDN'],
                    'membership_type' => $membershipData['membershipType'] ?? 'Direct',
                ]);

                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process AD group membership', [
                    'group' => $membershipData['groupName'] ?? 'unknown',
                    'user' => $membershipData['userSamAccountName'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process license assignments from sync payload
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param array<int, array<string, mixed>> $licenseAssignments
     * @return int
     */
    public function processLicenseAssignments(Integration $integration, int $customerId, array $licenseAssignments): int
    {
        $processed = 0;

        // For full sync, remove all existing license assignments for this customer first
        ActiveDirectoryLicenseAssignment::where('company_id', $integration->company_id)
            ->where('integration_id', $integration->integration_id)
            ->where('customer_id', $customerId)
            ->delete();

        foreach ($licenseAssignments as $assignmentData) {
            try {
                ActiveDirectoryLicenseAssignment::create([
                    'company_id' => $integration->company_id,
                    'integration_id' => $integration->integration_id,
                    'customer_id' => $customerId,
                    'user_sam_account_name' => $assignmentData['userSamAccountName'],
                    'user_dn' => $assignmentData['userDN'] ?? null,
                    'license_name' => $assignmentData['licenseName'],
                    'license_type' => $assignmentData['licenseType'] ?? 'SPLA',
                    'cost_per_user' => $assignmentData['costPerUser'] ?? 0,
                    'assigned_via_group' => $assignmentData['assignedViaGroup'],
                    'assigned_via_group_dn' => $assignmentData['assignedViaGroupDN'] ?? null,
                ]);

                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process AD license assignment', [
                    'user' => $assignmentData['userSamAccountName'] ?? 'unknown',
                    'license' => $assignmentData['licenseName'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Clean up orphaned records (users/groups that no longer exist in AD)
     * Only runs on full syncs, scoped to specific customer
     * 
     * @param Integration $integration
     * @param int $customerId
     * @param string $syncType
     * @return array{users: int, groups: int}
     */
    public function cleanupOrphans(Integration $integration, int $customerId, string $syncType): array
    {
        if ($syncType !== 'full') {
            return ['users' => 0, 'groups' => 0];
        }

        // Mark users not seen in this sync as disabled
        // (Agent should send all users in full sync, so missing = deleted)
        // We don't delete them to preserve historical references
        // Scoped to this customer only

        $stats = [
            'users' => 0,
            'groups' => 0,
        ];

        // Could implement orphan detection by comparing modified_at_ad with sync timestamp
        // For now, we rely on the agent sending accurate data
        // Note: cleanup is scoped to customer_id to avoid affecting other customers

        return $stats;
    }

    /**
     * Parse date string to Carbon instance
     * 
     * @param string|null $date
     * @return \Carbon\Carbon|null
     */
    private function parseDate(?string $date): ?\Carbon\Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get status and statistics for a specific customer
     * 
     * @param Integration $integration
     * @param int|null $customerId
     * @return array{users: array{total: int, enabled: int, disabled: int, locked_out: int}, groups: array{total: int}, memberships: array{total: int}, last_sync: array{started_at: string|null, completed_at: string|null, status: string, records_synced: int}|null}
     */
    public function getCustomerStatus(Integration $integration, ?int $customerId): array
    {
        $baseQuery = fn($model) => $model::where('company_id', $integration->company_id)
            ->where('integration_id', $integration->integration_id);

        // If customer-scoped, filter by customer
        if ($customerId) {
            $baseQuery = fn($model) => $model::where('company_id', $integration->company_id)
                ->where('integration_id', $integration->integration_id)
                ->where('customer_id', $customerId);
        }

        $userCount = $baseQuery(ActiveDirectoryUser::class)->count();
        $enabledUserCount = $baseQuery(ActiveDirectoryUser::class)->where('enabled', true)->count();
        $disabledUserCount = $baseQuery(ActiveDirectoryUser::class)->where('enabled', false)->count();
        $lockedOutCount = $baseQuery(ActiveDirectoryUser::class)->where('locked_out', true)->count();
        $groupCount = $baseQuery(ActiveDirectoryGroup::class)->count();
        $membershipCount = $baseQuery(ActiveDirectoryGroupMembership::class)->count();

        // Get last sync log for this integration
        $lastSync = \App\Models\SyncLog::where('integration_id', $integration->integration_id)
            ->orderBy('started_at', 'desc')
            ->first();

        return [
            'users' => [
                'total' => $userCount,
                'enabled' => $enabledUserCount,
                'disabled' => $disabledUserCount,
                'locked_out' => $lockedOutCount,
            ],
            'groups' => [
                'total' => $groupCount,
            ],
            'memberships' => [
                'total' => $membershipCount,
            ],
            'last_sync' => $lastSync ? [
                'started_at' => $lastSync->started_at?->toIso8601String(),
                'completed_at' => $lastSync->completed_at?->toIso8601String(),
                'status' => $lastSync->status,
                'records_synced' => $lastSync->records_synced,
            ] : null,
        ];
    }
}
