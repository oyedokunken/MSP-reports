<?php

namespace App\Reports\DomainExpiry;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DomainExpiryDataGatherer
{
    private Customer $customer;
    private Carbon $startDate;
    private Carbon $endDate;

    public function __construct(Customer $customer, Carbon $startDate, Carbon $endDate)
    {
        $this->customer = $customer;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getDomains(): Collection
    {
        // Get latest domain data for this customer
        $rawData = IntegrationData::where('company_id', $this->customer->company_id)
            ->where('data_type', 'domain')
            ->whereHas('integrationCustomer.customerMappings', function($query) {
                $query->where('customer_id', $this->customer->customer_id);
            })
            ->orderBy('data_timestamp', 'desc')
            ->get();

        \Log::info("Domain Expiry raw data count", [
            'customer_id' => $this->customer->customer_id,
            'count' => $rawData->count(),
            'sample' => $rawData->first()->data_payload ?? null,
        ]);

        $domains = $rawData
            ->unique(function($item) {
                // Deduplicate by domain name
                return $item->data_payload['name'] ?? $item->data_payload['domain_id'] ?? $item->integration_data_id;
            })
            ->map(function($item) {
                $payload = $item->data_payload;
                
                // Parse expiry date - ITGlue uses 'expires_on' field
                $expiryDate = null;
                $daysUntilExpiry = $payload['days_until_expiry'] ?? null;
                
                if (isset($payload['expires_on']) && !empty($payload['expires_on'])) {
                    try {
                        $expiryDate = Carbon::parse($payload['expires_on']);
                        // Recalculate days if not provided
                        if ($daysUntilExpiry === null) {
                            $daysUntilExpiry = now()->diffInDays($expiryDate, false);
                        }
                    } catch (\Exception $e) {
                        \Log::warning("Failed to parse domain expiry date", [
                            'expires_on' => $payload['expires_on'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // Determine severity
                $severity = 'healthy';
                if ($daysUntilExpiry !== null) {
                    if ($daysUntilExpiry <= 0) {
                        $severity = 'expired';
                    } elseif ($daysUntilExpiry <= 14) {
                        $severity = 'critical';
                    } elseif ($daysUntilExpiry <= 30) {
                        $severity = 'warning';
                    } elseif ($daysUntilExpiry <= 60) {
                        $severity = 'info';
                    }
                }
                
                return [
                    'domain_name' => $payload['name'] ?? 'Unknown Domain',
                    'expiry_date' => $expiryDate ? $expiryDate->toDateString() : 'Unknown',
                    'expiry_date_formatted' => $expiryDate ? $expiryDate->format('M d, Y') : 'Unknown',
                    'days_until_expiry' => $daysUntilExpiry,
                    'severity' => $severity,
                    'domain_id' => $payload['domain_id'] ?? null,
                    'organization_id' => $payload['organization_id'] ?? null,
                    'organization_name' => $payload['organization_name'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'registrar' => $payload['registrar'] ?? null,
                    'created_at' => $payload['created_at'] ?? null,
                    'updated_at' => $payload['updated_at'] ?? null,
                ];
            })
            ->sortBy('days_until_expiry')
            ->values();

        \Log::info("Domain Expiry processed data", [
            'customer_id' => $this->customer->customer_id,
            'count' => $domains->count(),
            'sample' => $domains->first(),
        ]);

        return $domains;
    }

    public function getSummary(Collection $domains): array
    {
        $total = $domains->count();
        $critical = $domains->where('severity', 'critical')->count();
        $expired = $domains->where('severity', 'expired')->count();
        $warning = $domains->where('severity', 'warning')->count();
        $info = $domains->where('severity', 'info')->count();
        $healthy = $domains->whereIn('severity', ['healthy'])->count();
        
        // Expiring soon = critical + expired + warning
        $expiringSoon = $critical + $expired + $warning;

        return [
            'total_domains' => $total,
            'expiring_soon' => $expiringSoon,
            'critical' => $critical + $expired, // Combine expired and critical
            'warning' => $warning,
            'healthy' => $healthy,
        ];
    }

    public function getAlerts(Collection $domains): array
    {
        $alerts = [];

        // Critical/Expired domains
        $critical = $domains->filter(function($domain) {
            return in_array($domain['severity'], ['expired', 'critical']);
        });

        if ($critical->count() > 0) {
            $alerts[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'title' => 'Critical: Domains Expired or Expiring Within 14 Days',
                'description' => sprintf(
                    '%d domain(s) require immediate renewal. Renew these domains now to prevent service disruption and potential loss of domain ownership.',
                    $critical->count()
                ),
                'domains' => $critical->values()->all(),
            ];
        }

        // Warning (15-30 days)
        $warning = $domains->where('severity', 'warning');

        if ($warning->count() > 0) {
            $alerts[] = [
                'type' => 'warning',
                'severity' => 'warning',
                'title' => 'Warning: Domains Expiring Within 30 Days',
                'description' => sprintf(
                    '%d domain(s) expiring this month. Schedule renewal soon to avoid last-minute issues.',
                    $warning->count()
                ),
                'domains' => $warning->values()->all(),
            ];
        }

        // Info (31-60 days)
        $info = $domains->where('severity', 'info');

        if ($info->count() > 0) {
            $alerts[] = [
                'type' => 'info',
                'severity' => 'info',
                'title' => 'Info: Domains Expiring Within 60 Days',
                'description' => sprintf(
                    '%d domain(s) expiring in the next 2 months. Plan renewal ahead of time.',
                    $info->count()
                ),
                'domains' => $info->values()->all(),
            ];
        }

        return $alerts;
    }
}
