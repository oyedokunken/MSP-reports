<?php

declare(strict_types=1);

namespace App\Reports\ThreatLockerSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ThreatLockerDataGatherer
{
    /**
     * Gather computer/endpoint data for the customer.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherComputers(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'threatlocker_computer')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('external_id')
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'external_id' => $row->external_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ])
            ->values();
    }

    /**
     * Gather application control policy data.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherPolicies(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'threatlocker_policy')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('external_id')
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'external_id' => $row->external_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ])
            ->values();
    }

    /**
     * Gather managed application data.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherApplications(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'threatlocker_application')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('external_id')
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'external_id' => $row->external_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ])
            ->values();
    }

    /**
     * Gather storage control policy data.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherStoragePolicies(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'threatlocker_storage_policy')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('external_id')
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'external_id' => $row->external_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ])
            ->values();
    }

    /**
     * Gather storage device data.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherStorageDevices(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'threatlocker_storage_device')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->unique('external_id')
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'external_id' => $row->external_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ])
            ->values();
    }

    /**
     * Gather audit log data within a date range.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherAuditLogs(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'threatlocker_audit')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'external_id' => $row->external_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);
    }

    /**
     * Check if any ThreatLocker data exists for the customer.
     *
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return bool
     */
    public function hasData(Customer $customer, array $integrationCustomerIds): bool
    {
        if (empty($integrationCustomerIds)) {
            return false;
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', [
                'threatlocker_computer',
                'threatlocker_policy',
                'threatlocker_application',
            ])
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();
    }
}
