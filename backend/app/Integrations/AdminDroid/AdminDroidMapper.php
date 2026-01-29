<?php

namespace App\Integrations\AdminDroid;

use Carbon\Carbon;

class AdminDroidMapper
{
    /**
     * Map M365 organization to standardized customer format
     */
    public static function mapOrganization(array $org): array
    {
        $id = $org['id'] ?? $org['tenantId'] ?? null;
        $name = $org['displayName'] ?? $org['name'] ?? 'M365 Organization';
        
        return [
            'external_id' => (string) $id,
            'name' => $name,
            'active' => true,
            'metadata' => [
                'tenant_id' => $id,
                'name' => $name,
                'verified_domains' => $org['verifiedDomains'] ?? [],
                'created_at' => $org['createdDateTime'] ?? null,
            ],
        ];
    }

    /**
     * Map M365 user to standardized format
     */
    public static function mapUser(array $user, ?int $integrationCustomerId = null): array
    {
        $id = $user['id'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'admindroid_user',
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'display_name' => $user['displayName'] ?? null,
                'email' => $user['mail'] ?? $user['userPrincipalName'] ?? null,
                'user_principal_name' => $user['userPrincipalName'] ?? null,
                'account_enabled' => $user['accountEnabled'] ?? true,
                'created_at' => $user['createdDateTime'] ?? null,
                'last_sign_in' => $user['lastSignInDateTime'] ?? $user['signInActivity']['lastSignInDateTime'] ?? null,
            ],
        ];
    }

    /**
     * Map AdminDroid M365 report into a standardized IntegrationData payload
     */
    public static function mapM365Report(array $report, ?int $integrationCustomerId = null): array
    {
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'admindroid_m365_report',
            'timestamp' => Carbon::now(),
            'payload' => $report,
        ];
    }

    /**
     * Map sign-in activity to standardized format
     */
    public static function mapSignInActivity(array $signIn, ?int $integrationCustomerId = null): array
    {
        $id = $signIn['id'] ?? null;
        $timestamp = $signIn['createdDateTime'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'admindroid_sign_in',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'user_id' => $signIn['userId'] ?? null,
                'user_display_name' => $signIn['userDisplayName'] ?? null,
                'user_principal_name' => $signIn['userPrincipalName'] ?? null,
                'app_display_name' => $signIn['appDisplayName'] ?? null,
                'ip_address' => $signIn['ipAddress'] ?? null,
                'location' => $signIn['location'] ?? null,
                'status' => ($signIn['status']['errorCode'] ?? 0) === 0 ? 'Success' : 'Failed',
                'error_code' => $signIn['status']['errorCode'] ?? null,
                'client_app' => $signIn['clientAppUsed'] ?? null,
                'device_detail' => $signIn['deviceDetail'] ?? null,
                'risk_level' => $signIn['riskLevelDuringSignIn'] ?? 'none',
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Map AdminDroid user activity into a standardized IntegrationData payload
     */
    public static function mapUserActivity(array $activity, ?int $integrationCustomerId = null): array
    {
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'admindroid_user_activity',
            'timestamp' => Carbon::now(),
            'payload' => $activity,
        ];
    }

    /**
     * Map mailbox usage to standardized format
     */
    public static function mapMailboxUsage(array $mailbox, ?int $integrationCustomerId = null): array
    {
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'admindroid_mailbox_usage',
            'timestamp' => Carbon::now(),
            'payload' => [
                'user_principal_name' => $mailbox['userPrincipalName'] ?? $mailbox['User Principal Name'] ?? null,
                'display_name' => $mailbox['displayName'] ?? $mailbox['Display Name'] ?? null,
                'storage_used_bytes' => $mailbox['storageUsedInBytes'] ?? $mailbox['Storage Used (Byte)'] ?? 0,
                'item_count' => $mailbox['itemCount'] ?? $mailbox['Item Count'] ?? 0,
                'last_activity_date' => $mailbox['lastActivityDate'] ?? $mailbox['Last Activity Date'] ?? null,
            ],
        ];
    }

    /**
     * Map Teams usage to standardized format
     */
    public static function mapTeamsUsage(array $teams, ?int $integrationCustomerId = null): array
    {
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'admindroid_teams_usage',
            'timestamp' => Carbon::now(),
            'payload' => [
                'user_principal_name' => $teams['userPrincipalName'] ?? $teams['User Principal Name'] ?? null,
                'last_activity_date' => $teams['lastActivityDate'] ?? $teams['Last Activity Date'] ?? null,
                'team_chat_message_count' => $teams['teamChatMessageCount'] ?? $teams['Team Chat Message Count'] ?? 0,
                'private_chat_message_count' => $teams['privateChatMessageCount'] ?? $teams['Private Chat Message Count'] ?? 0,
                'call_count' => $teams['callCount'] ?? $teams['Call Count'] ?? 0,
                'meeting_count' => $teams['meetingCount'] ?? $teams['Meeting Count'] ?? 0,
            ],
        ];
    }
}
