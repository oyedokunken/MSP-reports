<?php

declare(strict_types=1);

namespace App\Integrations\ThreatLocker;

use Carbon\Carbon;

final class ThreatLockerMapper
{
    /**
     * Map ThreatLocker organization to standardized customer format
     * 
     * @param array<string, mixed> $org
     * @return array<string, mixed>
     */
    public static function mapOrganization(array $org): array
    {
        $id = $org['organizationId'] ?? $org['id'] ?? $org['Id'] ?? null;
        $name = $org['name'] ?? $org['Name'] ?? $org['organizationName'] ?? 'Unknown';
        $parentId = $org['parentId'] ?? $org['ParentId'] ?? null;

        return [
            'external_id' => (string) $id,
            'name' => $name,
            'active' => true,
            'metadata' => [
                'organization_id' => $id,
                'parent_id' => $parentId,
                'computer_count' => $org['computerCount'] ?? $org['ComputerCount'] ?? null,
                'source' => 'threatlocker',
            ],
        ];
    }

    /**
     * Map ThreatLocker computer/endpoint to standardized format
     * 
     * @param array<string, mixed> $computer
     * @param string $organizationId
     * @return array<string, mixed>
     */
    public static function mapComputer(array $computer, string $organizationId): array
    {
        $computerId = $computer['computerId'] ?? $computer['id'] ?? $computer['ComputerId'] ?? null;
        $computerName = $computer['computerName'] ?? $computer['name'] ?? $computer['ComputerName'] ?? 'Unknown';

        $lastCheckIn = $computer['lastCheckin'] ?? $computer['lastCheckIn'] ?? $computer['LastCheckin'] ?? null;
        $lastCheckInDate = null;
        if ($lastCheckIn) {
            try {
                $lastCheckInDate = Carbon::parse($lastCheckIn)->toIso8601String();
            } catch (\Exception $e) {
                $lastCheckInDate = $lastCheckIn;
            }
        }

        return [
            'external_id' => (string) $computerId,
            'data_type' => 'threatlocker_computer',
            'organization_id' => $organizationId,
            'data' => [
                'computer_id' => $computerId,
                'computer_name' => $computerName,
                'organization_id' => $organizationId,
                'os_name' => $computer['osName'] ?? $computer['OsName'] ?? null,
                'os_version' => $computer['osVersion'] ?? $computer['OsVersion'] ?? null,
                'last_check_in' => $lastCheckInDate,
                'ip_address' => $computer['ipAddress'] ?? $computer['IpAddress'] ?? null,
                'agent_version' => $computer['agentVersion'] ?? $computer['AgentVersion'] ?? null,
                'is_online' => $computer['isOnline'] ?? $computer['IsOnline'] ?? null,
                'computer_group_id' => $computer['computerGroupId'] ?? $computer['ComputerGroupId'] ?? null,
                'computer_group_name' => $computer['computerGroupName'] ?? $computer['ComputerGroupName'] ?? null,
                'is_server' => $computer['isServer'] ?? $computer['IsServer'] ?? null,
                'domain' => $computer['domain'] ?? $computer['Domain'] ?? null,
                'logged_on_user' => $computer['loggedOnUser'] ?? $computer['LoggedOnUser'] ?? null,
                'has_computer_password' => $computer['hasComputerPassword'] ?? false,
            ],
        ];
    }

    /**
     * Map ThreatLocker policy data into a standardized IntegrationData payload
     * 
     * @param array<string, mixed> $policy
     * @param string $organizationId
     * @return array<string, mixed>
     */
    public static function mapPolicy(array $policy, string $organizationId): array
    {
        $policyId = $policy['policyId'] ?? $policy['id'] ?? $policy['PolicyId'] ?? null;
        $policyName = $policy['name'] ?? $policy['policyName'] ?? $policy['Name'] ?? 'Unknown';

        return [
            'external_id' => (string) $policyId,
            'data_type' => 'threatlocker_policy',
            'organization_id' => $organizationId,
            'data' => [
                'policy_id' => $policyId,
                'policy_name' => $policyName,
                'organization_id' => $organizationId,
                'policy_type' => $policy['policyType'] ?? $policy['PolicyType'] ?? null,
                'status' => $policy['status'] ?? $policy['Status'] ?? null,
                'is_enabled' => $policy['isEnabled'] ?? $policy['IsEnabled'] ?? true,
                'action' => $policy['action'] ?? $policy['Action'] ?? null,
                'description' => $policy['description'] ?? $policy['Description'] ?? null,
                'computer_group_id' => $policy['computerGroupId'] ?? $policy['ComputerGroupId'] ?? null,
                'computer_group_name' => $policy['computerGroupName'] ?? $policy['ComputerGroupName'] ?? null,
                'application_name' => $policy['applicationName'] ?? $policy['ApplicationName'] ?? null,
                'created_at' => $policy['createdDate'] ?? $policy['CreatedDate'] ?? null,
                'modified_at' => $policy['modifiedDate'] ?? $policy['ModifiedDate'] ?? null,
            ],
        ];
    }

    /**
     * Map ThreatLocker application to standardized format
     *
     * @param array<string, mixed> $app
     * @param string $organizationId
     * @return array<string, mixed>
     */
    public static function mapApplication(array $app, string $organizationId): array
    {
        $appId = $app['applicationId'] ?? $app['id'] ?? $app['ApplicationId'] ?? null;
        $appName = $app['name'] ?? $app['applicationName'] ?? $app['Name'] ?? 'Unknown';

        return [
            'external_id' => (string) $appId,
            'data_type' => 'threatlocker_application',
            'organization_id' => $organizationId,
            'data' => [
                'application_id' => $appId,
                'application_name' => $appName,
                'organization_id' => $organizationId,
                'description' => $app['description'] ?? $app['Description'] ?? null,
                'os_type' => $app['osType'] ?? $app['OsType'] ?? null,
                'is_built_in' => $app['isBuiltInApplication'] ?? $app['IsBuiltInApplication'] ?? false,
                'is_hidden' => $app['isHidden'] ?? $app['IsHidden'] ?? false,
                'is_temporary' => $app['isTemporary'] ?? $app['IsTemporary'] ?? false,
                'file_count' => $app['fileCount'] ?? $app['FileCount'] ?? null,
                'category' => $app['category'] ?? $app['Category'] ?? null,
            ],
        ];
    }

    /**
     * Map ThreatLocker application file to standardized format
     *
     * @param array<string, mixed> $file
     * @param string $organizationId
     * @param string $applicationId
     * @return array<string, mixed>
     */
    public static function mapApplicationFile(array $file, string $organizationId, string $applicationId): array
    {
        $fileId = $file['applicationFileId'] ?? $file['id'] ?? $file['ApplicationFileId'] ?? null;

        return [
            'external_id' => (string) $fileId,
            'data_type' => 'threatlocker_application_file',
            'organization_id' => $organizationId,
            'data' => [
                'file_id' => $fileId,
                'application_id' => $applicationId,
                'organization_id' => $organizationId,
                'file_name' => $file['fileName'] ?? $file['FileName'] ?? null,
                'full_path' => $file['fullPath'] ?? $file['FullPath'] ?? null,
                'process_path' => $file['processPath'] ?? $file['ProcessPath'] ?? null,
                'hash' => $file['hash'] ?? $file['Hash'] ?? null,
                'certificate' => $file['certificate'] ?? $file['Certificate'] ?? null,
                'file_type' => $file['fileType'] ?? $file['FileType'] ?? null,
            ],
        ];
    }

    /**
     * Map ThreatLocker storage control policy to standardized format
     *
     * @param array<string, mixed> $policy
     * @param string $organizationId
     * @return array<string, mixed>
     */
    public static function mapStoragePolicy(array $policy, string $organizationId): array
    {
        $policyId = $policy['storagePolicyId'] ?? $policy['id'] ?? $policy['StoragePolicyId'] ?? null;
        $policyName = $policy['name'] ?? $policy['policyName'] ?? $policy['Name'] ?? 'Unknown';

        return [
            'external_id' => (string) $policyId,
            'data_type' => 'threatlocker_storage_policy',
            'organization_id' => $organizationId,
            'data' => [
                'policy_id' => $policyId,
                'policy_name' => $policyName,
                'organization_id' => $organizationId,
                'status' => $policy['status'] ?? $policy['Status'] ?? null,
                'is_enabled' => $policy['isEnabled'] ?? $policy['IsEnabled'] ?? true,
                'action' => $policy['action'] ?? $policy['Action'] ?? null,
                'description' => $policy['description'] ?? $policy['Description'] ?? null,
                'computer_group_id' => $policy['computerGroupId'] ?? $policy['ComputerGroupId'] ?? null,
                'computer_group_name' => $policy['computerGroupName'] ?? $policy['ComputerGroupName'] ?? null,
                'device_type' => $policy['deviceType'] ?? $policy['DeviceType'] ?? null,
            ],
        ];
    }

    /**
     * Map ThreatLocker storage device to standardized format
     *
     * @param array<string, mixed> $device
     * @param string $organizationId
     * @return array<string, mixed>
     */
    public static function mapStorageDevice(array $device, string $organizationId): array
    {
        $deviceId = $device['storageDeviceId'] ?? $device['id'] ?? $device['StorageDeviceId'] ?? null;

        return [
            'external_id' => (string) $deviceId,
            'data_type' => 'threatlocker_storage_device',
            'organization_id' => $organizationId,
            'data' => [
                'device_id' => $deviceId,
                'organization_id' => $organizationId,
                'device_name' => $device['deviceName'] ?? $device['DeviceName'] ?? null,
                'serial_number' => $device['serialNumber'] ?? $device['SerialNumber'] ?? null,
                'vendor_id' => $device['vendorId'] ?? $device['VendorId'] ?? null,
                'product_id' => $device['productId'] ?? $device['ProductId'] ?? null,
                'device_type' => $device['deviceType'] ?? $device['DeviceType'] ?? null,
                'is_approved' => $device['isApproved'] ?? $device['IsApproved'] ?? null,
            ],
        ];
    }
}
