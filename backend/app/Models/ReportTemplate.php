<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property array<string, mixed>|null $schedule_config
 * @property string|null $time_of_day
 * @property string|null $timezone
 * @property array<int, string> $included_sections
 * @property array<string, mixed> $delivery_settings
 * @property array<string, mixed> $report_options
 * @property bool $active
 * @property bool $is_global
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReportSchedule> $schedules
 */
class ReportTemplate extends Model
{

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
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Customer, $this>
     */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ReportSchedule, $this>
     */
    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReportSchedule::class, 'template_id', 'template_id');
    }
}
