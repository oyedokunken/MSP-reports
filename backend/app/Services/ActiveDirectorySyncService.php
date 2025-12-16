<?php

namespace App\Services;

use App\Models\ActiveDirectoryGroup;
use App\Models\ActiveDirectoryGroupMembership;
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
                        'integration_id' => $integration->id,
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
                        'integration_id' => $integration->id,
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
     */
    public function processMemberships(Integration $integration, int $customerId, array $memberships): int
    {
        $processed = 0;

        // For full sync, remove all existing memberships for this customer first
        // This handles removed memberships
        ActiveDirectoryGroupMembership::where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->where('customer_id', $customerId)
            ->delete();

        foreach ($memberships as $membershipData) {
            try {
                ActiveDirectoryGroupMembership::create([
                    'company_id' => $integration->company_id,
                    'integration_id' => $integration->id,
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
     * Clean up orphaned records (users/groups that no longer exist in AD)
     * Only runs on full syncs, scoped to specific customer
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
     */
    private function parseDate(?string $date)
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
}
