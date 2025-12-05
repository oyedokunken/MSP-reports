<?php

namespace App\Reports\AssetSummary;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class AssetSummary extends BaseReport
{
    public function getRequiredDataTypes(): array
    {
        return ['configuration', 'flexible_asset', 'ssl_certificate', 'domain'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 0; // Snapshot data
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Get the ITGlue integration ID for this customer
        $integrationCustomer = $customer->integrationCustomers()
            ->whereHas('integration', function ($query) {
                $query->where('integration_type', 'ITGlue');
            })
            ->first();

        if (!$integrationCustomer) {
            return [
                'assets' => [],
                'licenses' => [],
                'ssl_certs' => [],
                'domains' => [],
                'stats' => ['total' => 0, 'expiring_soon' => 0],
            ];
        }

        // Fetch Data from IntegrationData
        $data = IntegrationData::where('integration_customer_id', $integrationCustomer->integration_customer_id)
            ->whereIn('data_type', ['configuration', 'flexible_asset', 'ssl_certificate', 'domain'])
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->get();

        // Process Assets (Configurations)
        $assets = $data->where('data_type', 'configuration')->map(function ($item) {
            $payload = $item->data_payload;
            return [
                'name' => $payload['name'] ?? 'Unknown Asset',
                'type' => $payload['configuration_type_name'] ?? 'Unknown Type',
                'serial' => $payload['serial_number'] ?? 'N/A',
                'warranty_expires_at' => $payload['warranty_expiration_date'] ?? null,
                'status' => $payload['status_name'] ?? 'Active',
            ];
        })->values();

        // Process Licenses (Flexible Assets)
        $licenses = $data->where('data_type', 'flexible_asset')->map(function ($item) {
            $payload = $item->data_payload;
            $traits = $payload['traits'] ?? [];
            
            // Try to find license-specific fields in traits
            // ITGlue traits are often keyed by ID or slug, so we look for common keywords
            $active = $traits['active_count'] ?? $traits['active'] ?? $traits['quantity'] ?? null;
            $consumed = $traits['consumed_count'] ?? $traits['consumed'] ?? $traits['used'] ?? null;
            $unused = $traits['unused_count'] ?? $traits['unused'] ?? null;
            
            // If we found license counts, calculate rate
            $rate = null;
            if (is_numeric($active) && $active > 0 && is_numeric($consumed)) {
                $rate = round(($consumed / $active) * 100, 1) . '%';
            }

            return [
                'name' => $payload['name'] ?? 'Unknown Item',
                'type' => $payload['flexible_asset_type_name'] ?? 'Flexible Asset',
                'active_count' => $active,
                'consumed_count' => $consumed,
                'unused_count' => $unused,
                'consumption_rate' => $rate,
                'traits' => $traits, // Keep original traits as fallback
            ];
        })->values();

        // Process SSL Certs
        $sslCerts = $data->where('data_type', 'ssl_certificate')->map(function ($item) {
            $payload = $item->data_payload;
            return [
                'name' => $payload['name'] ?? 'Unknown Cert',
                'expires_at' => $payload['expires_at'] ?? null,
                'issuer' => $payload['issuer'] ?? 'N/A',
            ];
        })->values();

        // Process Domains
        $domains = $data->where('data_type', 'domain')->map(function ($item) {
            $payload = $item->data_payload;
            return [
                'name' => $payload['name'] ?? 'Unknown Domain',
                'expires_at' => $payload['expires_at'] ?? null,
                'registrar' => $payload['registrar'] ?? 'N/A',
            ];
        })->values();

        // Calculate Stats
        $expiringSoon = 0;
        
        // Check Assets
        $expiringSoon += $assets->filter(function ($a) {
            return !empty($a['warranty_expires_at']) && Carbon::parse($a['warranty_expires_at'])->isFuture() && Carbon::parse($a['warranty_expires_at'])->diffInDays(now()) <= 90;
        })->count();

        // Check SSL
        $expiringSoon += $sslCerts->filter(function ($c) {
            return !empty($c['expires_at']) && Carbon::parse($c['expires_at'])->isFuture() && Carbon::parse($c['expires_at'])->diffInDays(now()) <= 90;
        })->count();

        // Check Domains
        $expiringSoon += $domains->filter(function ($d) {
            return !empty($d['expires_at']) && Carbon::parse($d['expires_at'])->isFuture() && Carbon::parse($d['expires_at'])->diffInDays(now()) <= 90;
        })->count();

        return [
            'generated_at' => now()->toDateTimeString(),
            'period' => [
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
            ],
            'assets' => $assets->toArray(),
            'licenses' => $licenses->toArray(),
            'ssl_certs' => $sslCerts->toArray(),
            'domains' => $domains->toArray(),
            'stats' => [
                'total' => $assets->count() + $licenses->count() + $sslCerts->count() + $domains->count(),
                'expiring_soon' => $expiringSoon,
            ],
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/AssetSummary/template.blade.php');
    }
}
