<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Company;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Scopes\CompanyScope;
use App\Traits\AuditTrail;
use App\Enums\UserStatus;
use App\Enums\AuthMethod;

/**
 * @property int $user_id
 * @property int $company_id
 * @property int $role_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserStatus $status
 * @property AuthMethod $auth_method
 * @property bool $force_password_change
 * @property string|null $profile_picture
 * @property array<string, mixed>|null $permissions
 * @property array<string, mixed>|null $settings
 * @property \Carbon\Carbon|null $last_login_at
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Role $role
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $unreadNotifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, AuditTrail;

    protected $primaryKey = 'user_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'role_id',
        'name',
        'email',
        'password',
        'status',
        'auth_method',
        'force_password_change',
        'profile_picture',
        'permissions',
        'settings',
        'last_login_at',
        'email_verified_at',
        'remember_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'status' => UserStatus::class,
        'auth_method' => AuthMethod::class,
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'permissions' => 'array',
        'scoped_customers' => 'array',
        'settings' => 'array',
        'force_password_change' => 'boolean',
    ];

    protected $appends = ['last_login', 'profile_picture_url'];

    /**
     * Accessor for last_login (maps from last_login_at for frontend compatibility)
     */
    public function getLastLoginAttribute(): ?string
    {
        return $this->last_login_at?->toIso8601String();
    }

    /**
     * Accessor for created_at (ensure it's always included)
     * 
     * @param string|null $value
     */
    public function getCreatedAtAttribute(?string $value): ?string
    {
        return $value ? \Carbon\Carbon::parse($value)->toIso8601String() : null;
    }

    /**
     * Accessor for profile_picture_url
     */
    public function getProfilePictureUrlAttribute(): ?string
    {
        if (!$this->profile_picture) {
            return null;
        }
        return asset('storage/' . $this->profile_picture);
    }


    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<AuditLog, $this>
     */
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id', 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Role, $this>
     */
    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
}
