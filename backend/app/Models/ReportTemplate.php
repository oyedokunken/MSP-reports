<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $primaryKey = 'template_id';
    
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'template_id';
    }
    
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }
    
    protected $fillable = [
        'company_id',
        'customer_id',
        'template_name',
        'description',
        'report_type',
        'frequency',
        'schedule_config',
        'time_of_day',
        'timezone',
        'included_sections',
        'delivery_settings',
        'report_options',
        'active',
    ];
    
    protected $casts = [
        'schedule_config' => 'array',
        'included_sections' => 'array',
        'delivery_settings' => 'array',
        'report_options' => 'array',
        'active' => 'boolean',
    ];
    
    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
