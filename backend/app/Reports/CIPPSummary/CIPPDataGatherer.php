<?php

declare(strict_types=1);

namespace App\Reports\CIPPSummary;

use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CIPPDataGatherer
{
    protected Customer $customer;
    protected int $companyId;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $this->companyId = $customer->company_id;
    }

    /**
     * Get integration customer IDs linked to CIPP integration
     * 
     * @return array<int>
     */
    protected function getIntegrationCustomerIds(): array
    {
        return IntegrationCustomer::query()
            ->whereIn('integration_customer_id', function ($query) {
                $query->select('integration_customer_id')
                    ->from('customer_mappings')
                    ->where('customer_id', $this->customer->customer_id);
            })
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'CIPP');
            })
            ->pluck('integration_customer_id')
            ->toArray();
    }

    /**
     * Get the CIPP integration ID for this company
     */
    protected function getCIPPIntegrationId(): ?int
    {
        return \App\Models\Integration::where('company_id', $this->companyId)
            ->where('integration_type', 'CIPP')
            ->value('integration_id');
    }

    /**
     * Query integration data by type within date range
     * 
     * @param array<int>|null $integrationCustomerIds
     * @return Collection<int, IntegrationData>
     */
    protected function queryData(string $dataType, Carbon $startDate, Carbon $endDate, ?array $integrationCustomerIds = null): Collection
    {
        $query = IntegrationData::where('company_id', $this->companyId)
            ->where('data_type', $dataType)
            ->whereBetween('data_timestamp', [$startDate, $endDate]);

        $integrationId = $this->getCIPPIntegrationId();
        if ($integrationId) {
            $query->where('integration_id', $integrationId);
        }

        if ($integrationCustomerIds !== null && !empty($integrationCustomerIds)) {
            $query->whereIn('integration_customer_id', $integrationCustomerIds);
        }

        return $query->get();
    }

    /**
     * Get latest snapshot data (not time-ranged, just most recent)
     * 
     * @param array<int>|null $integrationCustomerIds
     * @return Collection<int, IntegrationData>
     */
    protected function queryLatestData(string $dataType, ?array $integrationCustomerIds = null): Collection
    {
        $query = IntegrationData::where('company_id', $this->companyId)
            ->where('data_type', $dataType)
            ->orderBy('data_timestamp', 'desc');

        $integrationId = $this->getCIPPIntegrationId();
        if ($integrationId) {
            $query->where('integration_id', $integrationId);
        }

        if ($integrationCustomerIds !== null && !empty($integrationCustomerIds)) {
            $query->whereIn('integration_customer_id', $integrationCustomerIds);
        }

        // Get latest sync batch (within last 48 hours)
        $query->where('data_timestamp', '>=', now()->subHours(48));

        return $query->get();
    }

    /**
     * Gather user & MFA data
     * 
     * @return array<string, mixed>
     */
    public function gatherUserData(Carbon $startDate, Carbon $endDate): array
    {
        $users = $this->queryLatestData('user');

        $totalUsers = $users->count();
        $mfaEnabled = 0;
        $mfaDisabled = 0;
        $admins = 0;
        $licensed = 0;
        $blocked = 0;
        $userList = [];

        foreach ($users as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $mfaStatus = $payload['mfa_status'] ?? $payload['MFAStatus'] ?? $payload['per_user'] ?? 'unknown';
            $isAdmin = $payload['is_admin'] ?? $payload['isAdmin'] ?? false;
            $isLicensed = $payload['is_licensed'] ?? $payload['isLicensed'] ?? true;
            $isBlocked = $payload['account_enabled'] === false || ($payload['accountEnabled'] ?? true) === false;

            if (in_array(strtolower((string)$mfaStatus), ['enabled', 'enforced', 'true', '1'])) {
                $mfaEnabled++;
            } else {
                $mfaDisabled++;
            }

            if ($isAdmin) $admins++;
            if ($isLicensed) $licensed++;
            if ($isBlocked) $blocked++;

            $userList[] = [
                'name' => $payload['display_name'] ?? $payload['displayName'] ?? $payload['name'] ?? 'Unknown',
                'email' => $payload['email'] ?? $payload['userPrincipalName'] ?? '',
                'mfa_status' => $mfaStatus,
                'is_admin' => $isAdmin,
                'is_licensed' => $isLicensed,
                'account_enabled' => !$isBlocked,
                'last_sign_in' => $payload['last_sign_in'] ?? $payload['lastSignIn'] ?? null,
            ];
        }

        $mfaPercentage = $totalUsers > 0 ? round(($mfaEnabled / $totalUsers) * 100, 1) : 0;

        return [
            'total_users' => $totalUsers,
            'mfa_enabled' => $mfaEnabled,
            'mfa_disabled' => $mfaDisabled,
            'mfa_percentage' => $mfaPercentage,
            'admins' => $admins,
            'licensed' => $licensed,
            'blocked' => $blocked,
            'users' => collect($userList)->sortBy('name')->values()->toArray(),
        ];
    }

    /**
     * Gather license utilization data
     * 
     * @return array<string, mixed>
     */
    public function gatherLicenseData(Carbon $startDate, Carbon $endDate): array
    {
        $licenses = $this->queryLatestData('license');

        $licenseList = [];
        $totalAssigned = 0;
        $totalAvailable = 0;

        foreach ($licenses as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $skuName = $payload['sku_name'] ?? $payload['skuPartNumber'] ?? $payload['name'] ?? 'Unknown License';
            $assigned = (int)($payload['consumed_units'] ?? $payload['consumedUnits'] ?? $payload['assigned'] ?? 0);
            $total = (int)($payload['total_units'] ?? $payload['prepaidUnits'] ?? $payload['total'] ?? 0);
            $available = max(0, $total - $assigned);
            $utilization = $total > 0 ? round(($assigned / $total) * 100, 1) : 0;

            $totalAssigned += $assigned;
            $totalAvailable += $available;

            $licenseList[] = [
                'name' => $skuName,
                'assigned' => $assigned,
                'total' => $total,
                'available' => $available,
                'utilization' => $utilization,
            ];
        }

        // Sort by utilization descending
        usort($licenseList, fn($a, $b) => $b['utilization'] <=> $a['utilization']);

        return [
            'total_license_types' => count($licenseList),
            'total_assigned' => $totalAssigned,
            'total_available' => $totalAvailable,
            'licenses' => $licenseList,
        ];
    }

    /**
     * Gather security alerts and incidents
     * 
     * @return array<string, mixed>
     */
    public function gatherSecurityData(Carbon $startDate, Carbon $endDate): array
    {
        $alerts = $this->queryData('security_alert', $startDate, $endDate);
        $incidents = $this->queryData('security_incident', $startDate, $endDate);

        $alertList = [];
        $severityCounts = ['high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0];
        $statusCounts = ['new' => 0, 'in_progress' => 0, 'resolved' => 0];

        foreach ($alerts as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $severity = strtolower($payload['severity'] ?? 'informational');
            $status = strtolower($payload['status'] ?? 'new');

            if (isset($severityCounts[$severity])) $severityCounts[$severity]++;
            if (isset($statusCounts[$status])) $statusCounts[$status]++;

            $alertList[] = [
                'title' => $payload['title'] ?? $payload['name'] ?? 'Unknown Alert',
                'severity' => $severity,
                'status' => $status,
                'category' => $payload['category'] ?? 'General',
                'created_at' => $payload['created_at'] ?? $record->data_timestamp->toDateTimeString(),
            ];
        }

        $incidentList = [];
        foreach ($incidents as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $incidentList[] = [
                'title' => $payload['title'] ?? $payload['name'] ?? $payload['incidentName'] ?? 'Unknown Incident',
                'severity' => strtolower($payload['severity'] ?? 'medium'),
                'status' => strtolower($payload['status'] ?? 'active'),
                'classification' => $payload['classification'] ?? 'Unknown',
                'created_at' => $payload['created_at'] ?? $record->data_timestamp->toDateTimeString(),
            ];
        }

        return [
            'total_alerts' => count($alertList),
            'total_incidents' => count($incidentList),
            'severity_counts' => $severityCounts,
            'status_counts' => $statusCounts,
            'alerts' => collect($alertList)->sortByDesc('created_at')->values()->take(20)->toArray(),
            'incidents' => collect($incidentList)->sortByDesc('created_at')->values()->take(10)->toArray(),
        ];
    }

    /**
     * Gather Defender status data
     * 
     * @return array<string, mixed>
     */
    public function gatherDefenderData(Carbon $startDate, Carbon $endDate): array
    {
        $defenders = $this->queryLatestData('defender_state');

        $totalDevices = $defenders->count();
        $protected = 0;
        $unprotected = 0;
        $outdatedDefinitions = 0;
        $deviceList = [];

        foreach ($defenders as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $isProtected = $payload['is_managed'] ?? $payload['isManaged'] ?? $payload['real_time_protection'] ?? true;
            $definitionsUpToDate = $payload['definitions_up_to_date'] ?? $payload['signatureUpToDate'] ?? true;

            if ($isProtected) {
                $protected++;
            } else {
                $unprotected++;
            }

            if (!$definitionsUpToDate) {
                $outdatedDefinitions++;
            }

            $deviceList[] = [
                'name' => $payload['device_name'] ?? $payload['computerDnsName'] ?? $payload['name'] ?? 'Unknown',
                'os' => $payload['os_platform'] ?? $payload['osPlatform'] ?? 'Unknown',
                'is_protected' => $isProtected,
                'definitions_up_to_date' => $definitionsUpToDate,
                'last_seen' => $payload['last_seen'] ?? null,
            ];
        }

        $protectionRate = $totalDevices > 0 ? round(($protected / $totalDevices) * 100, 1) : 0;

        return [
            'total_devices' => $totalDevices,
            'protected' => $protected,
            'unprotected' => $unprotected,
            'outdated_definitions' => $outdatedDefinitions,
            'protection_rate' => $protectionRate,
            'devices' => collect($deviceList)->sortBy('name')->values()->take(50)->toArray(),
        ];
    }

    /**
     * Gather conditional access policy data
     * 
     * @return array<string, mixed>
     */
    public function gatherConditionalAccessData(Carbon $startDate, Carbon $endDate): array
    {
        $policies = $this->queryLatestData('conditional_access');

        $policyList = [];
        $enabledCount = 0;
        $disabledCount = 0;
        $reportOnlyCount = 0;

        foreach ($policies as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $state = strtolower($payload['state'] ?? $payload['status'] ?? 'enabled');

            if ($state === 'enabled' || $state === 'on') {
                $enabledCount++;
            } elseif ($state === 'reportonly' || $state === 'report-only') {
                $reportOnlyCount++;
            } else {
                $disabledCount++;
            }

            $policyList[] = [
                'name' => $payload['display_name'] ?? $payload['displayName'] ?? $payload['name'] ?? 'Unknown Policy',
                'state' => $state,
                'grant_controls' => $payload['grant_controls'] ?? $payload['grantControls'] ?? [],
                'conditions' => $payload['conditions'] ?? [],
                'modified_at' => $payload['modified_at'] ?? $payload['modifiedDateTime'] ?? null,
            ];
        }

        return [
            'total_policies' => count($policyList),
            'enabled' => $enabledCount,
            'disabled' => $disabledCount,
            'report_only' => $reportOnlyCount,
            'policies' => $policyList,
        ];
    }

    /**
     * Gather mailbox usage and exchange protection data
     * 
     * @return array<string, mixed>
     */
    public function gatherEmailData(Carbon $startDate, Carbon $endDate): array
    {
        $mailboxes = $this->queryLatestData('mailbox_usage');
        $protection = $this->queryLatestData('exchange_protection');

        $totalMailboxes = $mailboxes->count();
        $totalStorageGB = 0;
        $mailboxList = [];

        foreach ($mailboxes as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $storageBytes = (float)($payload['storage_used'] ?? $payload['storageUsedInBytes'] ?? $payload['total_item_size'] ?? 0);
            $storageGB = round($storageBytes / (1024 * 1024 * 1024), 2);
            $totalStorageGB += $storageGB;

            $mailboxList[] = [
                'display_name' => $payload['display_name'] ?? $payload['displayName'] ?? $payload['name'] ?? 'Unknown',
                'email' => $payload['email'] ?? $payload['userPrincipalName'] ?? '',
                'storage_gb' => $storageGB,
                'item_count' => (int)($payload['item_count'] ?? $payload['itemCount'] ?? 0),
                'last_activity' => $payload['last_activity_date'] ?? $payload['lastActivityDate'] ?? null,
            ];
        }

        // Sort by storage descending
        usort($mailboxList, fn($a, $b) => $b['storage_gb'] <=> $a['storage_gb']);

        $protectionList = [];
        foreach ($protection as $record) {
            $payload = $record->data_payload;
            if (!$payload) continue;

            $protectionList[] = [
                'name' => $payload['name'] ?? $payload['display_name'] ?? 'Unknown',
                'spam_filter' => $payload['spam_filter_enabled'] ?? $payload['spamFilterEnabled'] ?? true,
                'malware_filter' => $payload['malware_filter_enabled'] ?? $payload['malwareFilterEnabled'] ?? true,
                'phishing_protection' => $payload['phishing_protection'] ?? $payload['antiPhishEnabled'] ?? true,
            ];
        }

        return [
            'total_mailboxes' => $totalMailboxes,
            'total_storage_gb' => round($totalStorageGB, 2),
            'avg_storage_gb' => $totalMailboxes > 0 ? round($totalStorageGB / $totalMailboxes, 2) : 0,
            'mailboxes' => array_slice($mailboxList, 0, 30),
            'protection_policies' => $protectionList,
        ];
    }

    /**
     * Check if any CIPP data exists for this customer
     */
    public function hasData(): bool
    {
        $integrationId = $this->getCIPPIntegrationId();
        if (!$integrationId) return false;

        return IntegrationData::where('company_id', $this->companyId)
            ->where('integration_id', $integrationId)
            ->where('data_timestamp', '>=', now()->subDays(90))
            ->exists();
    }
}
