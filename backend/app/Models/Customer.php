<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;
use App\Traits\AuditTrail;

/**
 * @property int $customer_id
 * @property int $company_id
 * @property string $canonical_name
 * @property string $status
 * @property bool $is_static
 * @property array $report_configuration
 * @property string|null $report_destination
 * @property array|null $metadata
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\IntegrationCustomer> $integrationCustomers
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\CustomerMapping> $customerMappings
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ReportSchedule> $reportSchedules
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\GeneratedReport> $generatedReports
 */
class Customer extends Model
{
    use HasFactory, AuditTrail;

    protected $primaryKey = 'customer_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    protected $fillable = [
        'company_id',
        'canonical_name',
        'status',
        'is_static',
        'report_configuration',
        'report_destination',
        'metadata',
    ];

    protected $casts = [
        'report_configuration' => 'array',
        'metadata' => 'array',
        'is_static' => 'boolean',
    ];

    /**
     * Decode HTML entities in canonical_name when retrieving
     */
    public function getCanonicalNameAttribute($value)
    {
        return $value ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value;
    }

    /**
     * Encode HTML entities in canonical_name when setting
     */
    public function setCanonicalNameAttribute($value)
    {
        $this->attributes['canonical_name'] = $value;
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    
    public function integrationCustomers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            IntegrationCustomer::class,
            'customer_mappings',
            'customer_id',
            'integration_customer_id'
        );
    }

    public function customerMappings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerMapping::class, 'customer_id', 'customer_id');
    }

    public function reportSchedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReportSchedule::class, 'customer_id', 'customer_id');
    }

    public function generatedReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'customer_id', 'customer_id');
    }

    /**
     * Get all integration data for this customer through their mappings
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function integrationData(): \Illuminate\Database\Eloquent\Builder
    {
        $integrationCustomerIds = $this->integrationCustomers()->pluck('integration_customers.integration_customer_id')->toArray();
        return IntegrationData::whereIn('integration_customer_id', $integrationCustomerIds);
    }
}
