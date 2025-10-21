<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Scopes\CompanyScope;

class ApiKey extends Model
{
    use HasFactory;

    protected $primaryKey = 'api_key_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

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
        'permissions' => 'array',
        'ip_whitelist' => 'array',
        'allowed_endpoints' => 'array',
        'last_used_at' => 'datetime',
        'revoked' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id', 'user_id');
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'actor');
    }
}
