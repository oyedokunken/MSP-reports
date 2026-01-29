<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class AlertLog extends Model
{

    protected $primaryKey = 'alert_log_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'alert_rule_id',
        'severity',
        'alert_type',
        'title',
        'message',
        'context',
        'notification_status',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'context' => 'array',
        'notification_status' => 'array',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<AlertRule, $this>
     */
    public function alertRule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id', 'alert_rule_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function acknowledgedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'user_id');
    }

    public function isAcknowledged(): bool
    {
        return !is_null($this->acknowledged_at);
    }

    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }
}
