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
            'external_id' => $user['id'] ?? $user['userPrincipalName'] ?? '',
            'tenant_id' => $tenantId,
            'user_principal_name' => $user['userPrincipalName'] ?? null,
            'display_name' => $user['displayName'] ?? null,
            'email' => $user['mail'] ?? $user['userPrincipalName'] ?? null,
            'account_enabled' => $user['accountEnabled'] ?? true,
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
                'per_user_mfa' => $user['PerUserMFA'] ?? $user['perUserMFA'] ?? null,
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
        $perUserMfa = $user['PerUserMFA'] ?? $user['perUserMFA'] ?? null;
        $mfaMethods = $user['MFAMethods'] ?? $user['mfaMethods'] ?? [];
        
        if ($perUserMfa === 'Enforced') {
            return 'enforced';
        }
        if ($perUserMfa === 'Enabled') {
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
        $consumed = $license['consumedUnits'] ?? $license['ConsumedUnits'] ?? 0;
        $total = $license['prepaidUnits']['enabled'] ?? $license['PrepaidUnits']['Enabled'] ?? 0;
        $available = $total - $consumed;

        return [
            'external_id' => $license['skuId'] ?? $license['SkuId'] ?? '',
            'tenant_id' => $tenantId,
            'sku_id' => $license['skuId'] ?? $license['SkuId'] ?? null,
            'sku_part_number' => $license['skuPartNumber'] ?? $license['SkuPartNumber'] ?? null,
            'display_name' => $license['skuPartNumber'] ?? $license['SkuPartNumber'] ?? 'Unknown License',
            'total_licenses' => $total,
            'consumed_licenses' => $consumed,
            'available_licenses' => $available,
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
            'title' => $incident['displayName'] ?? $incident['title'] ?? 'Unknown Incident',
            'description' => $incident['description'] ?? null,
            'severity' => strtolower($incident['severity'] ?? 'unknown'),
            'status' => strtolower($incident['status'] ?? 'unknown'),
            'classification' => $incident['classification'] ?? null,
            'determination' => $incident['determination'] ?? null,
            'created_at' => $incident['createdDateTime'] ?? null,
            'last_updated_at' => $incident['lastUpdateDateTime'] ?? null,
            'metadata' => [
                'incident_web_url' => $incident['incidentWebUrl'] ?? null,
                'redirect_incident_id' => $incident['redirectIncidentId'] ?? null,
                'assigned_to' => $incident['assignedTo'] ?? null,
                'alerts' => $incident['alerts'] ?? [],
                'comments' => $incident['comments'] ?? [],
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
        return [
            'external_id' => $device['id'] ?? $device['deviceId'] ?? '',
            'tenant_id' => $tenantId,
            'device_name' => $device['deviceName'] ?? $device['computerDnsName'] ?? 'Unknown Device',
            'health_status' => $device['healthStatus'] ?? null,
            'risk_score' => $device['riskScore'] ?? null,
            'exposure_level' => $device['exposureLevel'] ?? null,
            'os_platform' => $device['osPlatform'] ?? null,
            'os_version' => $device['osVersion'] ?? null,
            'last_seen' => $device['lastSeen'] ?? null,
            'metadata' => [
                'onboarding_status' => $device['onboardingStatus'] ?? null,
                'sensor_health_state' => $device['sensorHealthState'] ?? null,
                'av_status' => $device['avStatus'] ?? null,
                'managed_by' => $device['managedBy'] ?? null,
                'first_seen' => $device['firstSeen'] ?? null,
                'machine_tags' => $device['machineTags'] ?? [],
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
}
