<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;
use App\Enums\ReportFrequency;

/**
 * @property int $report_schedule_id
 * @property int $company_id
 * @property int $customer_id
 * @property int|null $template_id
 * @property string $report_type
 * @property ReportFrequency|null $frequency
 * @property array<string, mixed>|null $schedule_config
 * @property string $time_of_day
 * @property bool $active
 * @property \Carbon\Carbon|null $last_generated_at
 * @property \Carbon\Carbon|null $next_scheduled_at
 * @property string $timezone
 * @property bool $is_customized
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Customer $customer
 * @property-read \App\Models\ReportTemplate|null $template
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GeneratedReport> $generatedReports
 */
class ReportSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\ReportScheduleFactory> */
    use HasFactory;

    protected $primaryKey = 'report_schedule_id';

    /**
     * Default attribute values to prevent null enum issues at runtime.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'frequency' => 'monthly',
        'active' => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    protected $fillable = [
        'company_id',
        'customer_id',
        'template_id',
        'report_type',
        'frequency',
        'schedule_config',
        'time_of_day',
        'active',
        'last_generated_at',
        'next_scheduled_at',
        'timezone',
        'is_customized',
    ];

    protected $casts = [
        'schedule_config' => 'array',
        'last_generated_at' => 'datetime',
        'next_scheduled_at' => 'datetime',
        'frequency' => ReportFrequency::class,
        'active' => 'boolean',
        'is_customized' => 'boolean',
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<GeneratedReport, $this>
     */
    public function generatedReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'report_schedule_id', 'report_schedule_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<ReportTemplate, $this>
     */
    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id', 'template_id');
    }

    // Scopes
    /**
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeDue($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->where('next_scheduled_at', '<=', now())
                    ->orWhereNull('next_scheduled_at');
            });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param int $hours
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeOverdue($query, int $hours = 24)
    {
        return $query->where('active', true)
            ->where('next_scheduled_at', '<', now()->subHours($hours));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param int $hours
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('active', true)
            ->whereBetween('next_scheduled_at', [now(), now()->addHours($hours)]);
    }

    // Helper methods
    public function isDue(): bool
    {
        if (!$this->active) {
            return false;
        }

        return $this->next_scheduled_at === null || $this->next_scheduled_at->isPast();
    }

    public function isOverdue(int $hours = 24): bool
    {
        if (!$this->active || !$this->next_scheduled_at) {
            return false;
        }

        return $this->next_scheduled_at->lt(now()->subHours($hours));
    }

    public function getFrequencyLabel(): string
    {
        return $this->frequency->value;
    }
}
