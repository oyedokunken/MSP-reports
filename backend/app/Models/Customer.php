<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

class Customer extends Model
{
    use HasFactory;

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

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    
    public function integrationCustomers()
    {
        return $this->belongsToMany(
            IntegrationCustomer::class,
            'customer_mappings',
            'customer_id',
            'integration_customer_id'
        );
    }

    public function customerMappings()
    {
        return $this->hasMany(CustomerMapping::class, 'customer_id', 'customer_id');
    }

    public function reportSchedules()
    {
        return $this->hasMany(ReportSchedule::class, 'customer_id', 'customer_id');
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'customer_id', 'customer_id');
    }
}
