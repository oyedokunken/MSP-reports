<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

/**
 * @property int $report_schedule_id
 * @property int $company_id
 * @property int $customer_id
 * @property int|null $template_id
 * @property string $report_type
 * @property string $frequency
 * @property array|null $schedule_config
 * @property string $time_of_day
 * @property bool $active
 * @property \Carbon\Carbon|null $last_generated_at
 * @property \Carbon\Carbon|null $next_scheduled_at
 * @property string $timezone
 * @property bool $is_customized
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Customer $customer
 * @property-read \App\Models\ReportTemplate|null $template
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\GeneratedReport> $generatedReports
 */
class ReportSchedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'report_schedule_id';

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
        'active' => 'boolean',
        'is_customized' => 'boolean',
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

    public function generatedReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'report_schedule_id', 'report_schedule_id');
    }

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id', 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->where('next_scheduled_at', '<=', now())
                    ->orWhereNull('next_scheduled_at');
            });
    }

    public function scopeOverdue($query, int $hours = 24)
    {
        return $query->where('active', true)
            ->where('next_scheduled_at', '<', now()->subHours($hours));
    }

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
        return match($this->frequency) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'on-demand' => 'On Demand',
            default => ucfirst($this->frequency),
        };
    }
}
