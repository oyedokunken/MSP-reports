<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;
use App\Models\AuditLog;

class ApiKey extends Model
{
    use HasFactory;

    protected $primaryKey = 'api_key_id';

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'display_name',
        'key_hash',
        'last_used_at',
        'permissions',
        'ip_whitelist',
        'allowed_endpoints',
        'revoked',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'permissions' => 'array',
        'ip_whitelist' => 'array',
        'allowed_endpoints' => 'array',
        'revoked' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    /**
     * Relationship: ApiKey belongs to Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Relationship: ApiKey created by User
     */
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    /**
     * Relationship: ApiKey revoked by User
     */
    public function revokedByUser()
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id', 'user_id');
    }

    /**
     * Relationship: ApiKey has many AuditLogs as actor
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'actor_id')
                    ->where('actor_type', 'ApiKey');
    }
}
