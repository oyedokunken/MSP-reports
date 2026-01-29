<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;

/**
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Model|null $actor
 * @property-read \Illuminate\Database\Eloquent\Model|null $target
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'audit_log_id';
    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'event_type',
        'actor_type',
        'actor_id',
        'target_type',
        'target_id',
        'event_data',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
    ];

    protected $appends = ['actor_name', 'target_name'];

    public function getActorNameAttribute()
    {
        // Skip relationship resolution for non-model types like 'System'
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        if (!isset($morphMap[$this->actor_type]) && !class_exists($this->actor_type)) {
            return $this->event_data['actor_name'] ?? ($this->actor_type === 'System' ? 'System' : $this->actor_type);
        }

        if ($this->actor) {
            return $this->actor->name ?? $this->actor->display_name ?? 'Unknown';
        }

        return $this->event_data['actor_name'] ?? 'Unknown';
    }

    public function getTargetNameAttribute()
    {
        // Skip relationship resolution for unknown types
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        if ($this->target_type && !isset($morphMap[$this->target_type]) && !class_exists($this->target_type)) {
            return $this->event_data['target_name'] ?? '-';
        }

        if ($this->target) {
            return $this->target->name ?? $this->target->display_name ?? $this->target->canonical_name ?? '-';
        }

        return $this->event_data['target_name'] ?? '-';
    }

    
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function actor()
    {
        return $this->morphTo();
    }

    public function target()
    {
        return $this->morphTo();
    }
}
