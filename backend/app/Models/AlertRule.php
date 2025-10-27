<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class AlertRule extends Model
{
    use HasFactory;

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

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function alertLogs()
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
        return now()->diffInMinutes($cooldownEnds);
    }
}
