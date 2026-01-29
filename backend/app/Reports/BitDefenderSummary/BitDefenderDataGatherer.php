<?php

declare(strict_types=1);

namespace App\Reports\BitDefenderSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class BitDefenderDataGatherer
{
    /**
     * Fetch endpoint data for the customer's mapped BitDefender integration customers.
     * Returns only the latest snapshot for each unique endpoint.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherEndpoints(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        // Get all endpoint snapshots
        $allEndpoints = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'bitdefender_endpoint')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);

        // Deduplicate by endpoint_id, keeping only the latest snapshot
        // Also try name+ip as fallback if endpoint_id is missing
        $uniqueEndpoints = collect();
        $seenEndpointIds = [];

        foreach ($allEndpoints as $endpoint) {
            $endpointId = $endpoint['payload']['endpoint_id'] ?? null;
            
            // If no endpoint_id, create a composite key from name + IP
            if (!$endpointId) {
                $name = $endpoint['payload']['name'] ?? '';
                $ip = $endpoint['payload']['ip'] ?? '';
                $endpointId = md5($name . '|' . $ip);
            }
            
            // Skip if already seen
            if (in_array($endpointId, $seenEndpointIds)) {
                continue;
            }
            
            $seenEndpointIds[] = $endpointId;
            $uniqueEndpoints->push($endpoint);
        }

        return $uniqueEndpoints;
    }

    /**
     * Fetch threat detection data.
     * Returns only unique threats (deduplicated by threat_id).
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @return Collection<int, mixed>
     */
    public function gatherThreatDetections(Customer $customer, array $integrationCustomerIds): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        // Get all threat detections
        $allThreats = IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'bitdefender_threat_detection')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->orderBy('data_timestamp', 'desc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
            ]);

        // Deduplicate by threat_id, keeping only the latest snapshot
        $uniqueThreats = collect();
        $seenThreatIds = [];

        foreach ($allThreats as $threat) {
            $threatId = $threat['payload']['threat_id'] ?? null;
            
            // Skip if no threat_id or already seen
            if (!$threatId || in_array($threatId, $seenThreatIds)) {
                continue;
            }
            
            $seenThreatIds[] = $threatId;
            $uniqueThreats->push($threat);
        }

        return $uniqueThreats;
    }

    /**
     * Fetch all endpoint snapshots in a date range for trend analysis.
     * 
     * @param Customer $customer
     * @param array<int, int> $integrationCustomerIds
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection<int, mixed>
     */
    public function gatherAllEndpointsInRange(Customer $customer, array $integrationCustomerIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($integrationCustomerIds)) {
            return collect();
        }

        return IntegrationData::where('company_id', $customer->company_id)
            ->where('data_type', 'bitdefender_endpoint')
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('data_timestamp', 'asc')
            ->get()
            ->map(fn ($row) => [
                'integration_customer_id' => $row->integration_customer_id,
                'timestamp' => $row->data_timestamp,
                'payload' => $row->data_payload,
                'external_id' => $row->external_id,
            ]);
    }
}
