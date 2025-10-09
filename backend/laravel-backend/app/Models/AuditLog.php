<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;
use App\Models\ApiKey;

class AuditLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'audit_log_id';

    public $timestamps = false; // created_at is managed manually in migration

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

    /**
     * Relationship: AuditLog belongs to Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Polymorphic relationship for actor (User or ApiKey)
     */
    public function actor()
    {
        return $this->morphTo(null, 'actor_type', 'actor_id');
    }
}
