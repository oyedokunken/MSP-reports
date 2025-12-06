<?php

namespace App\Integrations\ITGlue;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ITGlueMapper
{
    public static function mapCustomer(array $organization): array
    {
        $attributes = $organization['attributes'] ?? [];
        
        return [
            'external_id' => $organization['id'],
            'name' => $attributes['name'] ?? 'Unknown',
            'active' => ($attributes['organization-status-name'] ?? 'Active') === 'Active',
            'metadata' => [
                // Source Information
                'source' => 'itglue',
                'itglue_id' => $organization['id'],
                
                // Basic Information
                'short_name' => $attributes['short-name'] ?? null,
                'description' => $attributes['description'] ?? null,
                'organization_type_id' => $attributes['organization-type-id'] ?? null,
                'organization_type_name' => $attributes['organization-type-name'] ?? null,
                'organization_status_id' => $attributes['organization-status-id'] ?? null,
                'organization_status_name' => $attributes['organization-status-name'] ?? null,
                
                // Hierarchy
                'parent_id' => $attributes['parent-id'] ?? null,
                'ancestor_ids' => $attributes['ancestor-ids'] ?? [],
                'is_primary' => $attributes['primary'] ?? false,
                
                // Contact Information
                'phone' => $attributes['phone'] ?? null,
                'fax' => $attributes['fax'] ?? null,
                'website' => $attributes['website'] ?? null,
                
                // Address Information
                'address' => $attributes['address'] ?? null,
                'city' => $attributes['city'] ?? null,
                'region' => $attributes['region'] ?? null,
                'country' => $attributes['country'] ?? null,
                'postal_code' => $attributes['postal-code'] ?? null,
                
                // Business Details
                'quick_notes' => $attributes['quick-notes'] ?? null,
                'alert' => $attributes['alert'] ?? null,
                'logo_url' => $attributes['logo-url'] ?? null,
                
                // Timestamps
                'created_at' => $attributes['created-at'] ?? null,
                'updated_at' => $attributes['updated-at'] ?? null,
                
                // Additional metadata that might be present
                'group_id' => $attributes['group-id'] ?? null,
                'psa_id' => $attributes['psa-id'] ?? null,
                'psa_integration_type' => $attributes['psa-integration-type'] ?? null,
            ],
        ];
    }

    public static function mapSslCertificate(array $certificate): array
    {
        $attributes = $certificate['attributes'] ?? [];
        
        // ITGlue uses 'valid-until' for SSL certificates, not 'expires-on'
        $expiryDate = $attributes['valid-until'] ?? $attributes['expires-on'] ?? null;
        
        return [
            'integration_customer_id' => $attributes['organization-id'] ?? null,
            'data_type' => 'ssl_certificate',
            'timestamp' => now(),
            'payload' => [
                'certificate_id' => $certificate['id'],
                'name' => $attributes['name'] ?? null,
                'host' => $attributes['host'] ?? null,
                'issued_by' => $attributes['issued-by'] ?? null,
                'issuer_organization' => $attributes['issuer-organization'] ?? null,
                'issuer_country' => $attributes['issuer-country'] ?? null,
                'signature_algorithm' => $attributes['signature-algorithm'] ?? null,
                'expires_on' => $expiryDate,
                'organization_id' => $attributes['organization-id'] ?? null,
                'organization_name' => $attributes['organization-name'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'resource_url' => $attributes['resource-url'] ?? null,
                'created_at' => $attributes['created-at'] ?? null,
                'updated_at' => $attributes['updated-at'] ?? null,
                'days_until_expiry' => self::calculateDaysUntilExpiry($expiryDate),
            ],
        ];
    }

    public static function mapDomain(array $domain): array
    {
        $attributes = $domain['attributes'] ?? [];
        
        return [
            'integration_customer_id' => $attributes['organization-id'] ?? null,
            'data_type' => 'domain',
            'timestamp' => now(),
            'payload' => [
                'domain_id' => $domain['id'],
                'name' => $attributes['name'] ?? null,
                'expires_on' => $attributes['expires-on'] ?? null,
                'organization_id' => $attributes['organization-id'] ?? null,
                'organization_name' => $attributes['organization-name'] ?? null,
                'registrar' => $attributes['registrar-name'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'resource_url' => $attributes['resource-url'] ?? null,
                'created_at' => $attributes['created-at'] ?? null,
                'updated_at' => $attributes['updated-at'] ?? null,
                'days_until_expiry' => self::calculateDaysUntilExpiry($attributes['expires-on'] ?? null),
            ],
        ];
    }

    public static function mapFlexibleAsset(array $asset): array
    {
        $attributes = $asset['attributes'] ?? [];
        $traits = $attributes['traits'] ?? [];
        
        $expiryDate = self::detectExpiryDate($traits);
        
        return [
            'integration_customer_id' => $attributes['organization-id'] ?? null,
            'data_type' => 'flexible_asset',
            'timestamp' => isset($attributes['updated-at']) 
                ? Carbon::parse($attributes['updated-at']) 
                : now(),
            'payload' => [
                'asset_id' => $asset['id'],
                'type_id' => $attributes['flexible-asset-type-id'] ?? null,
                'type_name' => $attributes['flexible-asset-type-name'] ?? null,
                'name' => $attributes['name'] ?? null,
                'organization_id' => $attributes['organization-id'] ?? null,
                'organization_name' => $attributes['organization-name'] ?? null,
                'traits' => $traits,
                'expiry_date' => $expiryDate,
                'days_until_expiry' => self::calculateDaysUntilExpiry($expiryDate),
                'last_updated_at' => $attributes['updated-at'] ?? null,
            ],
        ];
    }
    
    // Detect expiry date from flexible asset traits
    // Scans for fields containing: expiry, expiration, or renewal
    // Excludes status values like: processing, pending, active, inactive, expired
    protected static function detectExpiryDate(array $traits): ?string
    {
        $expiryKeywords = ['expiry', 'expiration', 'renewal'];
        $excludeValues = ['processing', 'pending', 'active', 'inactive', 'expired'];
        
        foreach ($traits as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            
            $keyLower = strtolower($key);
            $valueLower = strtolower($value);
            
            $hasExpiryKeyword = false;
            foreach ($expiryKeywords as $keyword) {
                if (str_contains($keyLower, $keyword)) {
                    $hasExpiryKeyword = true;
                    break;
                }
            }
            
            if (!$hasExpiryKeyword) {
                continue;
            }
            
            $isExcluded = false;
            foreach ($excludeValues as $excluded) {
                if (str_contains($valueLower, $excluded)) {
                    $isExcluded = true;
                    break;
                }
            }
            
            if ($isExcluded) {
                continue;
            }
            
            try {
                $date = Carbon::parse($value);
                if ($date->year >= 2020 && $date->year <= 2100) {
                    return $date->toDateString();
                }
            } catch (\Exception $e) {
            }
        }
        
        return null;
    }

    protected static function calculateDaysUntilExpiry(?string $expiryDate): ?int
    {
        if (!$expiryDate) {
            return null;
        }
        
        // ITGlue returns "Processing" for certificates/domains still being processed
        if (strtolower($expiryDate) === 'processing') {
            return null;
        }
        
        try {
            $expiry = Carbon::parse($expiryDate);
            $now = Carbon::now();
            
            return (int) $now->diffInDays($expiry, false);
        } catch (\Exception $e) {
            Log::warning('ITGlue Mapper: Failed to calculate days until expiry', [
                'expiry_date' => $expiryDate,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public static function mapOrganizationData(array $organization): array
    {
        $attributes = $organization['attributes'] ?? [];
        
        return [
            'integration_customer_id' => $organization['id'],
            'data_type' => 'organization_info',
            'timestamp' => isset($attributes['updated-at']) 
                ? Carbon::parse($attributes['updated-at']) 
                : now(),
            'payload' => [
                'organization_id' => $organization['id'],
                'name' => $attributes['name'] ?? null,
                'short_name' => $attributes['short-name'] ?? null,
                'organization_type_id' => $attributes['organization-type-id'] ?? null,
                'organization_type_name' => $attributes['organization-type-name'] ?? null,
                'organization_status_id' => $attributes['organization-status-id'] ?? null,
                'organization_status_name' => $attributes['organization-status-name'] ?? null,
                'parent_id' => $attributes['parent-id'] ?? null,
                'ancestor_ids' => $attributes['ancestor-ids'] ?? [],
                'is_primary' => $attributes['primary'] ?? false,
                'created_at' => $attributes['created-at'] ?? null,
                'updated_at' => $attributes['updated-at'] ?? null,
            ],
        ];
    }
}
