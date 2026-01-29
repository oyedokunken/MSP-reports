<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

/**
 * @property int $alert_rule_id
 * @property int $company_id
 * @property string $name
 * @property string|null $description
 * @property string $severity
 * @property string $metric_type
 * @property array<string, mixed> $condition
 * @property array<string, mixed> $notification_channels
 * @property array<string, mixed>|null $notification_config
 * @property int $cooldown_minutes
 * @property \Carbon\Carbon|null $last_triggered_at
 * @property bool $is_active
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AlertLog> $alertLogs
 */
class AlertRule extends Model
{

    protected $primaryKey = 'alert_rule_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'severity',
        'metric_type',
        'condition',
        'notification_channels',
        'notification_config',
        'cooldown_minutes',
        'last_triggered_at',
        'is_active',
    ];

    protected $casts = [
        'condition' => 'array',
        'notification_channels' => 'array',
        'notification_config' => 'array',
        'last_triggered_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<AlertLog, $this>
     */
    public function alertLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AlertLog::class, 'alert_rule_id', 'alert_rule_id');
    }

    public function isInCooldown(): bool
    {
        if (!$this->last_triggered_at) {
            return false;
        }

        $cooldownEnds = $this->last_triggered_at->addMinutes($this->cooldown_minutes);
        return now()->lt($cooldownEnds);
    }

    public function cooldownRemainingMinutes(): int
    {
        if (!$this->isInCooldown()) {
            return 0;
        }

        $cooldownEnds = $this->last_triggered_at->addMinutes($this->cooldown_minutes);
        return (int) now()->diffInMinutes($cooldownEnds);
    }
}
