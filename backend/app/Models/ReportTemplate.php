<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

/**
 * @property int $template_id
 * @property int $company_id
 * @property int|null $customer_id
 * @property string $template_name
 * @property string|null $description
 * @property string $report_type
 * @property string $frequency
 * @property array|null $schedule_config
 * @property string|null $time_of_day
 * @property string|null $timezone
 * @property array $included_sections
 * @property array $delivery_settings
 * @property array $report_options
 * @property bool $active
 * @property bool $is_global
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\ReportSchedule> $schedules
 */
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
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
    
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReportSchedule::class, 'template_id', 'template_id');
    }
}
