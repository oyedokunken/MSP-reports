<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Scopes\CompanyScope;

/**
 * @property int $api_key_id
 * @property int $company_id
 * @property int|null $customer_id
 * @property int $created_by_user_id
 * @property string $display_name
 * @property string $key_hash
 * @property string|null $plain_text_key
 * @property \Carbon\Carbon|null $last_used_at
 * @property array<string, mixed>|null $permissions
 * @property array<string, mixed>|null $ip_whitelist
 * @property array<string, mixed>|null $allowed_endpoints
 * @property bool $revoked
 * @property \Carbon\Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\User $createdBy
 * @property-read \App\Models\User|null $revokedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs
 */
class ApiKey extends Model
{
    /** @use HasFactory<\Database\Factories\ApiKeyFactory> */
    use HasFactory;

    protected $primaryKey = 'api_key_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'customer_id',
        'created_by_user_id',
        'display_name',
        'key_preview',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function revokedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id', 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<AuditLog, $this>
     */
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AuditLog::class, 'actor');
    }
}
