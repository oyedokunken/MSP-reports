<?php

namespace App\Integrations\WatchguardAuthPoint;

use Carbon\Carbon;

class WatchguardAuthPointMapper
{
    /**
     * Map WatchGuard account to standardized customer format
     * 
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    public static function mapAccount(array $account, string $accountId): array
    {
        $name = $account['name'] ?? $account['accountName'] ?? 'WatchGuard Account';
        
        return [
            'external_id' => $accountId,
            'name' => $name,
            'active' => true,
            'metadata' => [
                'account_id' => $accountId,
                'name' => $name,
                'user_count' => $account['userCount'] ?? 0,
                'resource_count' => $account['resourceCount'] ?? 0,
            ],
        ];
    }

    /**
     * Map WatchGuard user to standardized format
     * 
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function mapUser(array $user, ?int $integrationCustomerId = null): array
    {
        $id = $user['id'] ?? $user['userId'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'watchguard_user',
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'username' => $user['username'] ?? $user['loginName'] ?? null,
                'email' => $user['email'] ?? null,
                'name' => $user['name'] ?? $user['displayName'] ?? null,
                'status' => $user['status'] ?? 'Active',
                'mfa_enabled' => $user['mfaEnabled'] ?? true,
                'last_login' => $user['lastLogin'] ?? $user['lastAuthentication'] ?? null,
                'created_at' => $user['createdAt'] ?? $user['dateCreated'] ?? null,
            ],
        ];
    }

    /**
     * Map WatchGuard AuthPoint authentication event into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    public static function mapAuthenticationEvent(array $event, ?int $integrationCustomerId = null): array
    {
        $id = $event['id'] ?? $event['eventId'] ?? $event['transactionId'] ?? null;
        $timestamp = $event['timestamp'] ?? $event['authTime'] ?? $event['dateTime'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'watchguard_authentication_event',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'user_id' => $event['userId'] ?? null,
                'username' => $event['username'] ?? $event['loginName'] ?? null,
                'resource_name' => $event['resourceName'] ?? $event['resource'] ?? null,
                'resource_id' => $event['resourceId'] ?? null,
                'auth_method' => $event['authMethod'] ?? $event['method'] ?? null,
                'result' => $event['result'] ?? $event['status'] ?? 'Unknown',
                'success' => ($event['result'] ?? $event['status'] ?? '') === 'Success' || 
                            ($event['success'] ?? false) === true,
                'ip_address' => $event['ipAddress'] ?? $event['clientIp'] ?? null,
                'location' => $event['location'] ?? $event['geoLocation'] ?? null,
                'device' => $event['device'] ?? $event['deviceInfo'] ?? null,
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Map WatchGuard AuthPoint user enrollment/token into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $enrollment
     * @return array<string, mixed>
     */
    public static function mapUserEnrollment(array $enrollment, ?int $integrationCustomerId = null): array
    {
        $id = $enrollment['id'] ?? $enrollment['tokenId'] ?? null;
        $timestamp = $enrollment['createdAt'] ?? $enrollment['enrolledAt'] ?? now();
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'watchguard_user_enrollment',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'user_id' => $enrollment['userId'] ?? null,
                'username' => $enrollment['username'] ?? null,
                'token_type' => $enrollment['tokenType'] ?? $enrollment['type'] ?? null,
                'device_name' => $enrollment['deviceName'] ?? $enrollment['name'] ?? null,
                'device_type' => $enrollment['deviceType'] ?? null,
                'status' => $enrollment['status'] ?? 'Active',
                'last_used' => $enrollment['lastUsed'] ?? $enrollment['lastAuthentication'] ?? null,
                'created_at' => $timestamp,
            ],
        ];
    }

    /**
    /**
     * Map WatchGuard resource (protected application) to standardized format
     * 
     * @param array<string, mixed> $resource
     * @return array<string, mixed>
     */
    public static function mapResource(array $resource, ?int $integrationCustomerId = null): array
    {
        $id = $resource['id'] ?? $resource['resourceId'] ?? null;
        
        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'watchguard_resource',
            'timestamp' => Carbon::now(),
            'payload' => [
                'id' => $id,
                'name' => $resource['name'] ?? $resource['resourceName'] ?? 'Unknown Resource',
                'type' => $resource['type'] ?? $resource['resourceType'] ?? null,
                'status' => $resource['status'] ?? 'Active',
                'auth_policy' => $resource['authPolicy'] ?? $resource['policy'] ?? null,
                'created_at' => $resource['createdAt'] ?? null,
            ],
        ];
    }
}
