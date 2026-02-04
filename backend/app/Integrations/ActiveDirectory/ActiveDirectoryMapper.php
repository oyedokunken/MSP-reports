<?php

declare(strict_types=1);

namespace App\Integrations\ActiveDirectory;

/**
 * Maps Active Directory data pushed by the PowerShell agent
 * to the standardized format used by the platform.
 */
final class ActiveDirectoryMapper
{
    /**
     * Map an AD user to standardized format
     * 
     * @param array<string, mixed> $adUser
     * @return array<string, mixed>
     */
    public static function mapUser(array $adUser): array
    {
        return [
            'external_id' => $adUser['ObjectGUID'] ?? $adUser['SamAccountName'] ?? '',
            'username' => $adUser['SamAccountName'] ?? '',
            'email' => $adUser['EmailAddress'] ?? $adUser['UserPrincipalName'] ?? null,
            'display_name' => $adUser['DisplayName'] ?? $adUser['Name'] ?? '',
            'first_name' => $adUser['GivenName'] ?? null,
            'last_name' => $adUser['Surname'] ?? null,
            'enabled' => $adUser['Enabled'] ?? true,
            'metadata' => [
                'distinguished_name' => $adUser['DistinguishedName'] ?? null,
                'user_principal_name' => $adUser['UserPrincipalName'] ?? null,
                'description' => $adUser['Description'] ?? null,
                'department' => $adUser['Department'] ?? null,
                'title' => $adUser['Title'] ?? null,
                'company' => $adUser['Company'] ?? null,
                'manager' => $adUser['Manager'] ?? null,
                'office' => $adUser['Office'] ?? null,
                'phone' => $adUser['TelephoneNumber'] ?? null,
                'mobile' => $adUser['MobilePhone'] ?? null,
                'last_logon' => $adUser['LastLogonDate'] ?? null,
                'password_last_set' => $adUser['PasswordLastSet'] ?? null,
                'password_never_expires' => $adUser['PasswordNeverExpires'] ?? false,
                'account_expires' => $adUser['AccountExpirationDate'] ?? null,
                'locked_out' => $adUser['LockedOut'] ?? false,
                'member_of' => $adUser['MemberOf'] ?? [],
            ],
        ];
    }

    /**
     * Map an AD security group to standardized format
     * 
     * @param array<string, mixed> $adGroup
     * @return array<string, mixed>
     */
    public static function mapSecurityGroup(array $adGroup): array
    {
        return [
            'external_id' => $adGroup['ObjectGUID'] ?? $adGroup['SamAccountName'] ?? '',
            'name' => $adGroup['Name'] ?? $adGroup['SamAccountName'] ?? '',
            'description' => $adGroup['Description'] ?? null,
            'metadata' => [
                'distinguished_name' => $adGroup['DistinguishedName'] ?? null,
                'sam_account_name' => $adGroup['SamAccountName'] ?? null,
                'group_category' => $adGroup['GroupCategory'] ?? null,
                'group_scope' => $adGroup['GroupScope'] ?? null,
                'managed_by' => $adGroup['ManagedBy'] ?? null,
                'member_count' => count($adGroup['Members'] ?? []),
            ],
        ];
    }

    /**
     * Map group membership data
     * 
     * @param array<string, mixed> $membership
     * @return array<string, mixed>
     */
    public static function mapGroupMembership(array $membership): array
    {
        return [
            'group_id' => $membership['GroupGUID'] ?? $membership['GroupSamAccountName'] ?? '',
            'member_id' => $membership['MemberGUID'] ?? $membership['MemberSamAccountName'] ?? '',
            'member_type' => $membership['MemberType'] ?? 'user', // user, group, computer
            'metadata' => [
                'group_name' => $membership['GroupName'] ?? null,
                'member_name' => $membership['MemberName'] ?? null,
            ],
        ];
    }
}
