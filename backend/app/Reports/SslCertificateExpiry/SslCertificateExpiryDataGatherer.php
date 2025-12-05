<?php

namespace App\Reports\SslCertificateExpiry;

use App\Models\Customer;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SslCertificateExpiryDataGatherer
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

    public function getCertificates(): Collection
    {
        // Get latest SSL certificate data for this customer
        $rawData = IntegrationData::where('company_id', $this->customer->company_id)
            ->where('data_type', 'ssl_certificate')
            ->whereHas('integrationCustomer.customerMappings', function($query) {
                $query->where('customer_id', $this->customer->customer_id);
            })
            ->orderBy('data_timestamp', 'desc')
            ->get();

        \Log::info("SSL Certificate raw data count", [
            'customer_id' => $this->customer->customer_id,
            'count' => $rawData->count(),
            'sample' => $rawData->first()->data_payload ?? null,
        ]);

        $certificates = $rawData
            ->unique(function($item) {
                // Deduplicate by certificate name
                return $item->data_payload['name'] ?? $item->data_payload['certificate_id'] ?? $item->integration_data_id;
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
                        \Log::warning("Failed to parse SSL expiry date", [
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
                    } elseif ($daysUntilExpiry <= 7) {
                        $severity = 'critical';
                    } elseif ($daysUntilExpiry <= 14) {
                        $severity = 'high';
                    } elseif ($daysUntilExpiry <= 30) {
                        $severity = 'warning';
                    }
                }
                
                // ITGlue SSL certificates data
                $domain = $payload['name'] ?? 'Unknown Domain';
                $issuer = $payload['issuer_organization'] ?? $payload['issued_by'] ?? 'Not Available';
                
                return [
                    'domain' => $domain,
                    'common_name' => $payload['host'] ?? $domain,
                    'issuer' => $issuer,
                    'issuer_country' => $payload['issuer_country'] ?? null,
                    'signature_algorithm' => $payload['signature_algorithm'] ?? null,
                    'expiry_date' => $expiryDate ? $expiryDate->toDateString() : 'Unknown',
                    'expiry_date_formatted' => $expiryDate ? $expiryDate->format('M d, Y') : 'Unknown',
                    'days_until_expiry' => $daysUntilExpiry,
                    'severity' => $severity,
                    'certificate_id' => $payload['certificate_id'] ?? null,
                    'organization_id' => $payload['organization_id'] ?? null,
                    'organization_name' => $payload['organization_name'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'created_at' => $payload['created_at'] ?? null,
                    'updated_at' => $payload['updated_at'] ?? null,
                ];
            })
            ->sortBy('days_until_expiry')
            ->values();

        \Log::info("SSL Certificate processed data", [
            'customer_id' => $this->customer->customer_id,
            'count' => $certificates->count(),
            'sample' => $certificates->first(),
        ]);

        return $certificates;
    }

    public function getSummary(Collection $certificates): array
    {
        $total = $certificates->count();
        $critical = $certificates->where('severity', 'critical')->count();
        $expired = $certificates->where('severity', 'expired')->count();
        $high = $certificates->where('severity', 'high')->count();
        $warning = $certificates->where('severity', 'warning')->count();
        $healthy = $certificates->whereIn('severity', ['healthy'])->count();
        
        // Expiring soon = critical + high + warning
        $expiringSoon = $critical + $expired + $high + $warning;

        return [
            'total_certificates' => $total,
            'expiring_soon' => $expiringSoon,
            'critical' => $critical + $expired, // Combine expired and critical
            'warning' => $warning,
            'healthy' => $healthy,
        ];
    }

    public function getAlerts(Collection $certificates): array
    {
        $alerts = [];

        // Critical/Expired certificates
        $critical = $certificates->filter(function($cert) {
            return in_array($cert['severity'], ['expired', 'critical']);
        });

        if ($critical->count() > 0) {
            $alerts[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'title' => 'Critical: Certificates Expired or Expiring Within 7 Days',
                'description' => sprintf(
                    '%d certificate(s) require immediate attention. Renew these certificates now to prevent service disruption.',
                    $critical->count()
                ),
                'certificates' => $critical->values()->all(),
            ];
        }

        // High priority (8-14 days)
        $high = $certificates->where('severity', 'high');

        if ($high->count() > 0) {
            $alerts[] = [
                'type' => 'high',
                'severity' => 'high',
                'title' => 'High Priority: Certificates Expiring Within 14 Days',
                'description' => sprintf(
                    '%d certificate(s) expiring soon. Schedule renewal within the next week.',
                    $high->count()
                ),
                'certificates' => $high->values()->all(),
            ];
        }

        // Warning (15-30 days)
        $warning = $certificates->where('severity', 'warning');

        if ($warning->count() > 0) {
            $alerts[] = [
                'type' => 'warning',
                'severity' => 'warning',
                'title' => 'Warning: Certificates Expiring Within 30 Days',
                'description' => sprintf(
                    '%d certificate(s) expiring this month. Plan renewal soon.',
                    $warning->count()
                ),
                'certificates' => $warning->values()->all(),
            ];
        }

        return $alerts;
    }
}
