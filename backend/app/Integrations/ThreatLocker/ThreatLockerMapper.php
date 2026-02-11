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
                'os_name' => $computer['operatingSystem'] ?? $computer['osName'] ?? null,
                'os_version' => $computer['osVersion'] ?? null,
                'last_check_in' => $lastCheckInDate,
                'last_check_in_ip' => $computer['lastCheckinIPAddress'] ?? $computer['ipAddress'] ?? null,
                'agent_version' => $computer['threatLockerVersion'] ?? $computer['agentVersion'] ?? null,
                'is_online' => $computer['isOnline'] ?? null,
                'computer_group_id' => $computer['computerGroupId'] ?? null,
                'computer_group_name' => $computer['group'] ?? $computer['computerGroupName'] ?? null,
                'is_server' => $computer['isServer'] ?? null,
                'domain' => $computer['domain'] ?? null,
                'logged_on_user' => $computer['username'] ?? $computer['loggedOnUser'] ?? null,
                'has_computer_password' => $computer['hasComputerPassword'] ?? false,
                
                // Mapped from user provided JSON
                'threat_locker_version' => $computer['threatLockerVersion'] ?? null,
                'service_version' => $computer['serviceVersion'] ?? null,
                'target_threat_locker_version' => $computer['targetThreatLockerVersion'] ?? null,
                'driver_status' => $computer['driverStatusString'] ?? null,
                'mode' => $computer['mode'] ?? null,
                'action' => $computer['action'] ?? null,
                'is_tamper_protection_disabled' => $computer['isTamperProtectionDisabled'] ?? false,
                'is_isolation_mode' => $computer['isIsolationMode'] ?? false,
                'is_lock_down_mode' => $computer['isLockDownMode'] ?? false,
                'is_ops_alerts_disabled' => $computer['isOpsAlertsDisabled'] ?? false,
                'maintenance_type_id' => $computer['maintenanceTypeId'] ?? null,
                'maintenance_end_date' => $computer['maintenanceEndDate'] ?? null,
                'start_date_time' => $computer['startDateTime'] ?? null,
                'os_type' => $computer['osType'] ?? null,
                'version_major' => $computer['versionMajor'] ?? null,
                'version_minor' => $computer['versionMinor'] ?? null,
                'deny_count_1d' => $computer['denyCountOneDay'] ?? 0,
                'deny_count_7d' => $computer['denyCountSevenDays'] ?? 0,
                'total_rows' => $computer['totalRows'] ?? null,
                'is_deleted' => $computer['isDeleted'] ?? false,
                'has_zero_denies_4d' => $computer['hasZeroDeniesFourDays'] ?? false,
                'ip_os' => $computer['ipOperatingSystem'] ?? null,
                'restart_needed' => $computer['restartComputerNeeded'] ?? false,
                'should_restart' => $computer['shouldRestart'] ?? false,
                'date_added' => $computer['dateAdded'] ?? null,
                'date_created' => $computer['dateCreated'] ?? null,
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

    /**
     * Map ThreatLocker audit log to standardized format
     *
     * @param array<string, mixed> $audit
     * @param string $organizationId
     * @return array<string, mixed>
     */
     public static function mapAudit(array $audit, string $organizationId): array
     {
         $auditId = $audit['auditId'] ?? $audit['id'] ?? null;
         $date = $audit['date'] ?? null;
         $dateIso = null;
         if ($date) {
             try {
                 $dateIso = Carbon::parse($date)->toIso8601String();
             } catch (\Exception $e) {
                 $dateIso = $date;
             }
         }

         return [
             'external_id' => (string) $auditId,
             'data_type' => 'threatlocker_audit',
             'organization_id' => $organizationId,
             'data' => [
                 'audit_id' => $auditId,
                 'organization_id' => $organizationId,
                 'date' => $dateIso,
                 'computer_name' => $audit['computerName'] ?? null,
                 'computer_id' => $audit['computerId'] ?? null,
                 'action' => $audit['action'] ?? null,
                 'kind_of_action' => $audit['kindOfAction'] ?? null,
                 'application' => $audit['application'] ?? null,
                 'process_name' => $audit['processName'] ?? null,
                 'file_path' => $audit['filePath'] ?? null,
                 'user' => $audit['user'] ?? null,
                 'policy_name' => $audit['policyName'] ?? null,
                 'policy_id' => $audit['policyId'] ?? null,
                 'certificate' => $audit['certificate'] ?? null,
                 'hash' => $audit['hash'] ?? null,
                 'interface' => $audit['interface'] ?? null,
                 'ip_address' => $audit['ipAddress'] ?? null,
                 'is_server' => $audit['isServer'] ?? false,
             ],
         ];
     }
     /**
     * Map ThreatLocker system audit log (login) to standardized format
     *
     * @param array<string, mixed> $audit
     * @param string $organizationId
     * @return array<string, mixed>
     */
     public static function mapSystemAudit(array $audit, string $organizationId): array
     {
         $auditId = $audit['systemAuditId'] ?? $audit['id'] ?? null;
         $date = $audit['dateTime'] ?? null;
         $dateIso = null;
         if ($date) {
             try {
                 $dateIso = Carbon::parse($date)->toIso8601String();
             } catch (\Exception $e) {
                 $dateIso = $date;
             }
         }

         return [
             'external_id' => (string) $auditId,
             'data_type' => 'threatlocker_system_audit',
             'organization_id' => $organizationId,
             'data' => [
                 'system_audit_id' => $auditId,
                 'organization_id' => $organizationId,
                 'date' => $dateIso,
                 'effective_action' => $audit['effectiveAction'] ?? null,
                 'email_address' => $audit['emailAddress'] ?? null,
                 'ip_address' => $audit['ipAddress'] ?? null,
                 'country' => $audit['country'] ?? null,
                 'region' => $audit['region'] ?? null,
                 'city' => $audit['city'] ?? null,
                 'latitude' => $audit['latitude'] ?? null,
                 'longitude' => $audit['longitude'] ?? null,
             ],
         ];
     }
 }

