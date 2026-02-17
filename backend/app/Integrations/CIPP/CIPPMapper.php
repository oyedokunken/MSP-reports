<?php

declare(strict_types=1);

namespace App\Integrations\CIPP;

/**
 * Maps CIPP API data to standardized format for the platform.
 */
final class CIPPMapper
{
    /**
     * Map a CIPP tenant to standardized customer format
     * 
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>
     */
    public static function mapTenant(array $tenant): array
    {
        return [
            'external_id' => $tenant['customerId'] ?? $tenant['tenantId'] ?? $tenant['defaultDomainName'] ?? '',
            'name' => $tenant['displayName'] ?? $tenant['defaultDomainName'] ?? 'Unknown Tenant',
            'active' => true,
            'metadata' => [
                'tenant_id' => $tenant['tenantId'] ?? null,
                'customer_id' => $tenant['customerId'] ?? null,
                'default_domain' => $tenant['defaultDomainName'] ?? null,
                'initial_domain' => $tenant['initialDomainName'] ?? null,
                'domains' => $tenant['domains'] ?? [],
                'relationship_type' => $tenant['delegatedPrivilegeStatus'] ?? null,
                'gdap_enabled' => $tenant['gdapEnabled'] ?? false,
                'onmicrosoft_domain' => $tenant['onmicrosoftDomain'] ?? null,
            ],
        ];
    }

    /**
     * Map a CIPP user with MFA info to standardized format
     * 
     * @param array<string, mixed> $user
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapUser(array $user, string $tenantId): array
    {
        return [
            'external_id' => $user['ID'] ?? $user['id'] ?? $user['UPN'] ?? $user['userPrincipalName'] ?? '',
            'tenant_id' => $tenantId,
            'user_principal_name' => $user['UPN'] ?? $user['userPrincipalName'] ?? null,
            'display_name' => $user['DisplayName'] ?? $user['displayName'] ?? null,
            'email' => $user['UPN'] ?? $user['mail'] ?? $user['userPrincipalName'] ?? null,
            'account_enabled' => $user['AccountEnabled'] ?? $user['accountEnabled'] ?? true,
            'is_admin' => $user['IsAdmin'] ?? $user['isAdmin'] ?? false,
            'is_licensed' => $user['isLicensed'] ?? $user['IsLicensed'] ?? false,
            'mfa_status' => self::extractMfaStatus($user),
            'metadata' => [
                'given_name' => $user['givenName'] ?? null,
                'surname' => $user['surname'] ?? null,
                'job_title' => $user['jobTitle'] ?? null,
                'department' => $user['department'] ?? null,
                'office_location' => $user['officeLocation'] ?? null,
                'mobile_phone' => $user['mobilePhone'] ?? null,
                'business_phones' => $user['businessPhones'] ?? [],
                'user_type' => $user['userType'] ?? null,
                'created_datetime' => $user['createdDateTime'] ?? null,
                'last_sign_in' => $user['lastSignInDateTime'] ?? null,
                'assigned_licenses' => $user['assignedLicenses'] ?? [],
                'mfa_methods' => $user['MFAMethods'] ?? $user['mfaMethods'] ?? [],
                'per_user_mfa' => $user['PerUser'] ?? $user['PerUserMFA'] ?? $user['perUserMFA'] ?? null,
                'mfa_registration' => $user['MFARegistration'] ?? null,
                'mfa_capable' => $user['MFACapable'] ?? null,
                'covered_by_ca' => $user['CoveredByCA'] ?? null,
                'covered_by_sd' => $user['CoveredBySD'] ?? null,
                'ca_policies' => $user['CAPolicies'] ?? [],
            ],
        ];
    }

    /**
     * Extract MFA status from user data
     * 
     * @param array<string, mixed> $user
     * @return string
     */
    private static function extractMfaStatus(array $user): string
    {
        $perUser = $user['PerUser'] ?? $user['PerUserMFA'] ?? $user['perUserMFA'] ?? null;
        $mfaMethods = $user['MFAMethods'] ?? $user['mfaMethods'] ?? [];
        $coveredByCA = $user['CoveredByCA'] ?? null;
        $coveredBySD = $user['CoveredBySD'] ?? null;
        
        if (is_string($perUser) && strtolower($perUser) === 'enforced') {
            return 'enforced';
        }
        if (is_string($perUser) && strtolower($perUser) === 'enabled') {
            return 'enabled';
        }
        if (!empty($coveredByCA) && $coveredByCA !== 'None' && $coveredByCA !== false) {
            return 'enabled';
        }
        if ($coveredBySD === true || $coveredBySD === 'true') {
            return 'enabled';
        }
        if (!empty($mfaMethods)) {
            return 'configured';
        }
        return 'disabled';
    }

    /**
     * Map a license to standardized format
     * 
     * @param array<string, mixed> $license
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapLicense(array $license, string $tenantId): array
    {
        // CIPP ListLicenses returns: Tenant, License, CountUsed, CountAvailable, TotalLicenses
        // Also handle Graph API format: skuId, skuPartNumber, consumedUnits, prepaidUnits
        $consumed = $license['CountUsed'] ?? $license['consumedUnits'] ?? $license['ConsumedUnits'] ?? 0;
        $total = $license['TotalLicenses'] ?? $license['prepaidUnits']['enabled'] ?? $license['PrepaidUnits']['Enabled'] ?? 0;
        $available = $license['CountAvailable'] ?? ($total - $consumed);
        $licenseName = $license['License'] ?? $license['skuPartNumber'] ?? $license['SkuPartNumber'] ?? 'Unknown License';

        return [
            'external_id' => $license['skuId'] ?? $license['SkuId'] ?? $licenseName,
            'tenant_id' => $tenantId,
            'sku_id' => $license['skuId'] ?? $license['SkuId'] ?? null,
            'sku_part_number' => $license['License'] ?? $license['skuPartNumber'] ?? $license['SkuPartNumber'] ?? null,
            'display_name' => $licenseName,
            'total_licenses' => (int) $total,
            'consumed_licenses' => (int) $consumed,
            'available_licenses' => (int) $available,
            'metadata' => [
                'applies_to' => $license['appliesTo'] ?? null,
                'capability_status' => $license['capabilityStatus'] ?? null,
                'service_plans' => $license['servicePlans'] ?? [],
            ],
        ];
    }

    /**
     * Map a security alert to standardized format
     * 
     * @param array<string, mixed> $alert
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapSecurityAlert(array $alert, string $tenantId): array
    {
        return [
            'external_id' => $alert['id'] ?? $alert['Id'] ?? '',
            'tenant_id' => $tenantId,
            'title' => $alert['title'] ?? $alert['Title'] ?? 'Unknown Alert',
            'description' => $alert['description'] ?? $alert['Description'] ?? null,
            'severity' => strtolower($alert['severity'] ?? $alert['Severity'] ?? 'unknown'),
            'status' => strtolower($alert['status'] ?? $alert['Status'] ?? 'unknown'),
            'category' => $alert['category'] ?? $alert['Category'] ?? null,
            'created_at' => $alert['createdDateTime'] ?? $alert['CreatedDateTime'] ?? null,
            'last_updated_at' => $alert['lastModifiedDateTime'] ?? $alert['LastModifiedDateTime'] ?? null,
            'metadata' => [
                'alert_type' => $alert['alertType'] ?? null,
                'provider' => $alert['vendorInformation']['provider'] ?? null,
                'vendor' => $alert['vendorInformation']['vendor'] ?? null,
                'source' => $alert['source'] ?? null,
                'recommended_actions' => $alert['recommendedActions'] ?? [],
                'user_states' => $alert['userStates'] ?? [],
                'host_states' => $alert['hostStates'] ?? [],
            ],
        ];
    }

    /**
     * Map a security incident to standardized format
     * 
     * @param array<string, mixed> $incident
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapSecurityIncident(array $incident, string $tenantId): array
    {
        return [
            'external_id' => $incident['id'] ?? $incident['Id'] ?? '',
            'tenant_id' => $tenantId,
            'title' => $incident['displayName'] ?? $incident['DisplayName'] ?? $incident['title'] ?? 'Unknown Incident',
            'description' => $incident['description'] ?? $incident['Description'] ?? null,
            'severity' => strtolower($incident['severity'] ?? $incident['Severity'] ?? 'unknown'),
            'status' => strtolower($incident['status'] ?? $incident['Status'] ?? 'unknown'),
            'classification' => $incident['classification'] ?? $incident['Classification'] ?? null,
            'determination' => $incident['determination'] ?? $incident['Determination'] ?? null,
            'created_at' => $incident['createdDateTime'] ?? $incident['Created'] ?? null,
            'last_updated_at' => $incident['lastUpdateDateTime'] ?? $incident['Updated'] ?? null,
            'metadata' => [
                'incident_web_url' => $incident['incidentWebUrl'] ?? $incident['IncidentUrl'] ?? null,
                'redirect_incident_id' => $incident['redirectIncidentId'] ?? $incident['RedirectId'] ?? null,
                'assigned_to' => $incident['assignedTo'] ?? $incident['AssignedTo'] ?? null,
                'alerts' => $incident['alerts'] ?? [],
                'comments' => $incident['comments'] ?? [],
                'tags' => $incident['tags'] ?? $incident['Tags'] ?? [],
            ],
        ];
    }

    /**
     * Map a conditional access policy to standardized format
     * 
     * @param array<string, mixed> $policy
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapConditionalAccessPolicy(array $policy, string $tenantId): array
    {
        return [
            'external_id' => $policy['id'] ?? $policy['Id'] ?? '',
            'tenant_id' => $tenantId,
            'display_name' => $policy['displayName'] ?? $policy['DisplayName'] ?? 'Unknown Policy',
            'state' => strtolower($policy['state'] ?? $policy['State'] ?? 'unknown'),
            'created_at' => $policy['createdDateTime'] ?? null,
            'modified_at' => $policy['modifiedDateTime'] ?? null,
            'metadata' => [
                'conditions' => $policy['conditions'] ?? [],
                'grant_controls' => $policy['grantControls'] ?? [],
                'session_controls' => $policy['sessionControls'] ?? [],
            ],
        ];
    }

    /**
     * Map Defender state for a device
     * 
     * @param array<string, mixed> $device
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapDefenderState(array $device, string $tenantId): array
    {
        $protection = $device['windowsProtectionState'] ?? [];
        
        return [
            'external_id' => $device['id'] ?? $device['deviceId'] ?? $device['deviceName'] ?? '',
            'tenant_id' => $tenantId,
            'device_name' => $device['deviceName'] ?? $device['computerDnsName'] ?? 'Unknown Device',
            'health_status' => $device['healthStatus'] ?? $protection['deviceState'] ?? null,
            'risk_score' => $device['riskScore'] ?? null,
            'exposure_level' => $device['exposureLevel'] ?? null,
            'os_platform' => $device['osPlatform'] ?? null,
            'os_version' => $device['osVersion'] ?? null,
            'last_seen' => $device['lastSeen'] ?? $protection['lastReportedDateTime'] ?? null,
            'metadata' => [
                'onboarding_status' => $device['onboardingStatus'] ?? null,
                'sensor_health_state' => $device['sensorHealthState'] ?? null,
                'av_status' => $device['avStatus'] ?? null,
                'managed_by' => $device['managedBy'] ?? null,
                'first_seen' => $device['firstSeen'] ?? null,
                'machine_tags' => $device['machineTags'] ?? [],
                'protection_state' => $protection,
            ],
        ];
    }

    /**
     * Map Defender vulnerability data
     * 
     * @param array<string, mixed> $vulnerability
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapDefenderVulnerability(array $vulnerability, string $tenantId): array
    {
        return [
            'external_id' => $vulnerability['id'] ?? $vulnerability['cveId'] ?? '',
            'tenant_id' => $tenantId,
            'cve_id' => $vulnerability['cveId'] ?? null,
            'name' => $vulnerability['name'] ?? $vulnerability['cveId'] ?? 'Unknown Vulnerability',
            'description' => $vulnerability['description'] ?? null,
            'severity' => strtolower($vulnerability['severity'] ?? 'unknown'),
            'cvss_score' => $vulnerability['cvssV3'] ?? null,
            'exposed_machines' => $vulnerability['exposedMachines'] ?? 0,
            'published_on' => $vulnerability['publishedOn'] ?? null,
            'metadata' => [
                'software_vendor' => $vulnerability['softwareVendor'] ?? null,
                'software_name' => $vulnerability['softwareName'] ?? null,
                'software_version' => $vulnerability['softwareVersion'] ?? null,
                'exploit_types' => $vulnerability['exploitTypes'] ?? [],
                'exploit_verified' => $vulnerability['exploitVerified'] ?? false,
                'public_exploit' => $vulnerability['publicExploit'] ?? false,
            ],
        ];
    }

    /**
     * Map a sign-in event to standardized format
     * 
     * @param array<string, mixed> $signIn
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapSignIn(array $signIn, string $tenantId): array
    {
        $status = $signIn['status'] ?? [];
        $isSuccess = ($status['errorCode'] ?? 0) === 0;

        return [
            'external_id' => $signIn['id'] ?? '',
            'tenant_id' => $tenantId,
            'user_principal_name' => $signIn['userPrincipalName'] ?? null,
            'user_display_name' => $signIn['userDisplayName'] ?? null,
            'app_display_name' => $signIn['appDisplayName'] ?? null,
            'ip_address' => $signIn['ipAddress'] ?? null,
            'location' => self::formatLocation($signIn['location'] ?? []),
            'status' => $isSuccess ? 'success' : 'failure',
            'error_code' => $status['errorCode'] ?? null,
            'failure_reason' => $status['failureReason'] ?? null,
            'created_at' => $signIn['createdDateTime'] ?? null,
            'metadata' => [
                'client_app_used' => $signIn['clientAppUsed'] ?? null,
                'device_detail' => $signIn['deviceDetail'] ?? [],
                'conditional_access_status' => $signIn['conditionalAccessStatus'] ?? null,
                'is_interactive' => $signIn['isInteractive'] ?? null,
                'risk_detail' => $signIn['riskDetail'] ?? null,
                'risk_level_aggregated' => $signIn['riskLevelAggregated'] ?? null,
                'risk_state' => $signIn['riskState'] ?? null,
                'mfa_detail' => $signIn['mfaDetail'] ?? [],
            ],
        ];
    }

    /**
     * Format location data
     * 
     * @param array<string, mixed> $location
     * @return string|null
     */
    private static function formatLocation(array $location): ?string
    {
        $parts = array_filter([
            $location['city'] ?? null,
            $location['state'] ?? null,
            $location['countryOrRegion'] ?? null,
        ]);
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }
    /**
     * Map mailbox usage data to standardized format
     * 
     * @param array<string, mixed> $usage
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapMailboxUsage(array $usage, string $tenantId): array
    {
        return [
            'external_id' => $usage['ExchangeGuid'] ?? $usage['ExternalDirectoryObjectId'] ?? $usage['UPN'] ?? $usage['id'] ?? $usage['userPrincipalName'] ?? '',
            'tenant_id' => $tenantId,
            'user_principal_name' => $usage['UPN'] ?? $usage['userPrincipalName'] ?? null,
            'display_name' => $usage['displayName'] ?? $usage['DisplayName'] ?? null,
            'primary_smtp_address' => $usage['primarySmtpAddress'] ?? null,
            'recipient_type' => $usage['recipientType'] ?? null,
            'recipient_type_details' => $usage['recipientTypeDetails'] ?? null,
            'item_count' => $usage['itemCount'] ?? 0,
            'storage_used_bytes' => $usage['storageUsedInBytes'] ?? 0,
            'last_activity_date' => $usage['lastActivityDate'] ?? null,
            'metadata' => [
                'exchange_guid' => $usage['ExchangeGuid'] ?? null,
                'archive_guid' => $usage['ArchiveGuid'] ?? null,
                'forwarding_address' => $usage['ForwardingSmtpAddress'] ?? null,
                'hidden_from_gal' => $usage['HiddenFromAddressListsEnabled'] ?? false,
                'litigation_hold' => $usage['LitigationHoldEnabled'] ?? false,
                'retention_policy' => $usage['RetentionPolicy'] ?? null,
                'additional_addresses' => $usage['AdditionalEmailAddresses'] ?? null,
                'is_deleted' => $usage['isDeleted'] ?? ($usage['WhenSoftDeleted'] !== null),
            ],
        ];
    }

    /**
     * Map exchange protection data (anti-spam, etc) to standardized format
     * 
     * @param array<string, mixed> $protection
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapExchangeProtection(array $protection, string $tenantId): array
    {
        return [
            'external_id' => $protection['id'] ?? $protection['Identity'] ?? '',
            'tenant_id' => $tenantId,
            'name' => $protection['displayName'] ?? $protection['Name'] ?? 'Unknown Protection',
            'type' => $protection['type'] ?? $protection['PolicyType'] ?? 'unknown',
            'state' => strtolower($protection['state'] ?? $protection['State'] ?? 'unknown'),
            'metadata' => [
                'is_default' => $protection['isDefault'] ?? false,
                'priority' => $protection['priority'] ?? null,
                'settings' => $protection['settings'] ?? $protection,
            ],
        ];
    }
    /**
     * Map an audit log event to standardized format
     * 
     * @param array<string, mixed> $log
     * @param string $tenantId
     * @return array<string, mixed>
     */
    public static function mapAuditLog(array $log, string $tenantId): array
    {
        return [
            'external_id' => $log['id'] ?? $log['Id'] ?? '',
            'tenant_id' => $tenantId,
            'activity' => $log['activity'] ?? $log['Activity'] ?? 'Unknown Activity',
            'category' => $log['category'] ?? $log['Category'] ?? null,
            'operation' => $log['operation'] ?? $log['Operation'] ?? null,
            'result' => $log['result'] ?? $log['Result'] ?? null,
            'actor' => $log['userPrincipalName'] ?? $log['UserPrincipalName'] ?? $log['actor'] ?? null,
            'ip_address' => $log['ipAddress'] ?? $log['IPAddress'] ?? $log['clientIP'] ?? null,
            'created_at' => $log['createdDateTime'] ?? $log['CreatedDateTime'] ?? $log['creationDate'] ?? $log['creationTime'] ?? null,
            'metadata' => [
                'correlation_id' => $log['correlationId'] ?? null,
                'target_resources' => $log['targetResources'] ?? [],
                'additional_details' => $log['additionalDetails'] ?? [],
                'workload' => $log['workload'] ?? null,
                'client_app' => $log['clientApp'] ?? null,
            ],
        ];
    }
}
