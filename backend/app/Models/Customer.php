<?php

declare(strict_types=1);

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
 * @property array<string, mixed> $report_configuration
 * @property string|null $report_destination
 * @property array<string, mixed>|null $metadata
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\IntegrationCustomer> $integrationCustomers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerMapping> $customerMappings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReportSchedule> $reportSchedules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GeneratedReport> $generatedReports
 */
class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Decode HTML entities in canonical_name when retrieving
     * 
     * @param string|null $value
     * @return string|null
     */
    public function getCanonicalNameAttribute(?string $value): ?string
    {
        return $value ? html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value;
    }

    /**
     * Encode HTML entities in canonical_name when setting
     * 
     * @param string|null $value
     * @return void
     */
    public function setCanonicalNameAttribute(?string $value): void
    {
        $this->attributes['canonical_name'] = $value;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<IntegrationCustomer, $this>
     */
    public function integrationCustomers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            IntegrationCustomer::class,
            'customer_mappings',
            'customer_id',
            'integration_customer_id'
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CustomerMapping, $this>
     */
    public function customerMappings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerMapping::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ReportSchedule, $this>
     */
    public function reportSchedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReportSchedule::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<GeneratedReport, $this>
     */
    public function generatedReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'customer_id', 'customer_id');
    }

    /**
     * Get all integration data for this customer through their mappings
     * 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    /**
     * Get all integration data for this customer through their mappings
     * 
     * @return \Illuminate\Database\Eloquent\Builder<IntegrationData>
     */
    public function integrationData(): \Illuminate\Database\Eloquent\Builder
    {
        $integrationCustomerIds = $this->integrationCustomers()->pluck('integration_customers.integration_customer_id')->toArray();
        return IntegrationData::whereIn('integration_customer_id', $integrationCustomerIds);
    }
}
