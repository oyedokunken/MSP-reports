<?php

namespace App\Integrations\ITGlue;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ITGlueMapper
{
    private const EXPIRY_KEYWORDS = ['expiry', 'expiration', 'renewal'];
    private const EXCLUDED_EXPIRY_VALUES = ['processing', 'pending', 'active', 'inactive', 'expired'];

    /**
     * @param array<string, mixed> $organization
     * @return array<string, mixed>
     */
    public static function mapCustomer(array $organization): array
    {
        $attributes = $organization['attributes'] ?? [];

        return [
            'external_id' => $organization['id'],
            'name'        => $attributes['name'] ?? 'Unknown',
            'active'      => ($attributes['organization-status-name'] ?? 'Active') === 'Active',
            'metadata'    => [
                'source'                  => 'itglue',
                'itglue_id'               => $organization['id'],
                'short_name'              => $attributes['short-name'] ?? null,
                'description'             => $attributes['description'] ?? null,
                'organization_type_id'    => $attributes['organization-type-id'] ?? null,
                'organization_type_name'  => $attributes['organization-type-name'] ?? null,
                'organization_status_id'  => $attributes['organization-status-id'] ?? null,
                'organization_status_name'=> $attributes['organization-status-name'] ?? null,
                'parent_id'               => $attributes['parent-id'] ?? null,
                'ancestor_ids'            => $attributes['ancestor-ids'] ?? [],
                'is_primary'              => $attributes['primary'] ?? false,
                'phone'                   => $attributes['phone'] ?? null,
                'fax'                     => $attributes['fax'] ?? null,
                'website'                 => $attributes['website'] ?? null,
                'address'                 => $attributes['address'] ?? null,
                'city'                    => $attributes['city'] ?? null,
                'region'                  => $attributes['region'] ?? null,
                'country'                 => $attributes['country'] ?? null,
                'postal_code'             => $attributes['postal-code'] ?? null,
                'quick_notes'             => $attributes['quick-notes'] ?? null,
                'alert'                   => $attributes['alert'] ?? null,
                'logo_url'                => $attributes['logo-url'] ?? null,
                'created_at'              => $attributes['created-at'] ?? null,
                'updated_at'              => $attributes['updated-at'] ?? null,
                'group_id'                => $attributes['group-id'] ?? null,
                'psa_id'                  => $attributes['psa-id'] ?? null,
                'psa_integration_type'    => $attributes['psa-integration-type'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $certificate
     * @return array<string, mixed>
     */
    public static function mapSslCertificate(array $certificate): array
    {
        $attributes = $certificate['attributes'] ?? [];
        $expiry = $attributes['valid-until'] ?? $attributes['expires-on'] ?? null;

        return [
            'integration_customer_id' => $attributes['organization-id'] ?? null,
            'data_type'               => 'ssl_certificate',
            'timestamp'               => now(),
            'payload'                 => [
                'certificate_id'     => $certificate['id'],
                'name'               => $attributes['name'] ?? null,
                'host'               => $attributes['host'] ?? null,
                'issued_by'          => $attributes['issued-by'] ?? null,
                'issuer_organization'=> $attributes['issuer-organization'] ?? null,
                'issuer_country'     => $attributes['issuer-country'] ?? null,
                'signature_algorithm'=> $attributes['signature-algorithm'] ?? null,
                'expires_on'         => $expiry,
                'organization_id'    => $attributes['organization-id'] ?? null,
                'organization_name'  => $attributes['organization-name'] ?? null,
                'notes'              => $attributes['notes'] ?? null,
                'resource_url'       => $attributes['resource-url'] ?? null,
                'created_at'         => $attributes['created-at'] ?? null,
                'updated_at'         => $attributes['updated-at'] ?? null,
                'days_until_expiry'  => self::daysUntilExpiry($expiry),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $domain
     * @return array<string, mixed>
     */
    public static function mapDomain(array $domain): array
    {
        $attributes = $domain['attributes'] ?? [];
        $expiry = $attributes['expires-on'] ?? null;

        return [
            'integration_customer_id' => $attributes['organization-id'] ?? null,
            'data_type'               => 'domain',
            'timestamp'               => now(),
            'payload'                 => [
                'domain_id'         => $domain['id'],
                'name'              => $attributes['name'] ?? null,
                'expires_on'        => $expiry,
                'organization_id'   => $attributes['organization-id'] ?? null,
                'organization_name' => $attributes['organization-name'] ?? null,
                'registrar'         => $attributes['registrar-name'] ?? null,
                'notes'             => $attributes['notes'] ?? null,
                'resource_url'      => $attributes['resource-url'] ?? null,
                'created_at'        => $attributes['created-at'] ?? null,
                'updated_at'        => $attributes['updated-at'] ?? null,
                'days_until_expiry' => self::daysUntilExpiry($expiry),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    public static function mapFlexibleAsset(array $asset): array
    {
        $attributes = $asset['attributes'] ?? [];
        $traits     = $attributes['traits'] ?? [];
        $expiryDate = self::detectExpiryDate($traits);

        return [
            'integration_customer_id' => $attributes['organization-id'] ?? null,
            'data_type'               => 'flexible_asset',
            'timestamp'               => isset($attributes['updated-at'])
                ? Carbon::parse($attributes['updated-at'])
                : now(),
            'payload'                 => [
                'asset_id'          => $asset['id'],
                'type_id'           => $attributes['flexible-asset-type-id'] ?? null,
                'type_name'         => $attributes['flexible-asset-type-name'] ?? null,
                'name'              => $attributes['name'] ?? null,
                'organization_id'   => $attributes['organization-id'] ?? null,
                'organization_name' => $attributes['organization-name'] ?? null,
                'traits'            => $traits,
                'expiry_date'       => $expiryDate,
                'days_until_expiry' => self::daysUntilExpiry($expiryDate),
                'expiries'          => self::scanAllExpiries($traits),
                'last_updated_at'   => $attributes['updated-at'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $organization
     * @return array<string, mixed>
     */
    public static function mapOrganizationData(array $organization): array
    {
        $attributes = $organization['attributes'] ?? [];

        return [
            'integration_customer_id' => $organization['id'],
            'data_type'               => 'organization_info',
            'timestamp'               => isset($attributes['updated-at'])
                ? Carbon::parse($attributes['updated-at'])
                : now(),
            'payload'                 => [
                'organization_id'     => $organization['id'],
                'name'                => $attributes['name'] ?? null,
                'short_name'          => $attributes['short-name'] ?? null,
                'organization_type_id'=> $attributes['organization-type-id'] ?? null,
                'organization_type_name' => $attributes['organization-type-name'] ?? null,
                'organization_status_id' => $attributes['organization-status-id'] ?? null,
                'organization_status_name' => $attributes['organization-status-name'] ?? null,
                'parent_id'           => $attributes['parent-id'] ?? null,
                'ancestor_ids'        => $attributes['ancestor-ids'] ?? [],
                'is_primary'          => $attributes['primary'] ?? false,
                'created_at'          => $attributes['created-at'] ?? null,
                'updated_at'          => $attributes['updated-at'] ?? null,
            ],
        ];
    }

    /**
     * Scan all traits for any expiry-like fields and return structured expiry info.
     * 
     * @param array<string, mixed> $traits
     * @return array<string, array<string, mixed>>
     */
    private static function scanAllExpiries(array $traits): array
    {
        $expiries = [];

        foreach ($traits as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $lowerKey = strtolower($key);
            if (!self::hasExpiryKeyword($lowerKey)) {
                continue;
            }

            $expiry = self::parseExpiryValue($value);
            if (!$expiry) {
                continue;
            }

            $daysLeft = (int) $expiry->diffInDays(now(), false);
            $severity = match (true) {
                $daysLeft <= 7  => 'critical',
                $daysLeft <= 14 => 'high',
                $daysLeft <= 30 => 'warning',
                default         => 'info',
            };

            $expiries[$key] = [
                'date'      => $value,
                'days_left' => $daysLeft,
                'severity'  => $severity,
            ];
        }

        return $expiries;
    }

    private static function hasExpiryKeyword(string $key): bool
    {
        foreach (self::EXPIRY_KEYWORDS as $keyword) {
            if (str_contains($key, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private static function parseExpiryValue(string $value): ?Carbon
    {
        $lower = strtolower($value);
        foreach (self::EXCLUDED_EXPIRY_VALUES as $excluded) {
            if (str_contains($lower, $excluded)) {
                return null;
            }
        }

        if (!preg_match('/\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
            return ($date->year >= 2020 && $date->year <= 2100) ? $date : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $traits
     */
    private static function detectExpiryDate(array $traits): ?string
    {
        foreach ($traits as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            $expiry = self::parseExpiryValue($value);
            if ($expiry) {
                return $expiry->toDateString();
            }
        }

        return null;
    }

    private static function daysUntilExpiry(?string $expiryDate): ?int
    {
        if (!$expiryDate || strtolower($expiryDate) === 'processing') {
            return null;
        }

        try {
            return (int) Carbon::parse($expiryDate)->diffInDays(now(), false);
        } catch (\Exception $e) {
            Log::warning('Failed to calculate days until expiry', [
                'date'  => $expiryDate,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
