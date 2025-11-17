<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\CompanyScope;

class ActiveDirectoryUser extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'user_principal_name',
        'sam_account_name',
        'display_name',
        'given_name',
        'surname',
        'email',
        'department',
        'title',
        'manager',
        'manager_dn',
        'enabled',
        'last_logon',
        'password_last_set',
        'password_never_expires',
        'account_expiration',
        'locked_out',
        'created_at_ad',
        'modified_at_ad',
        'distinguished_name',
        'description',
        'office',
        'office_phone',
        'mobile_phone',
        'company',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'password_never_expires' => 'boolean',
        'locked_out' => 'boolean',
        'last_logon' => 'datetime',
        'password_last_set' => 'datetime',
        'account_expiration' => 'datetime',
        'created_at_ad' => 'datetime',
        'modified_at_ad' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * Get the company that owns the user
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the integration that synced this user
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get group memberships for this user
     */
    public function groupMemberships()
    {
        return $this->hasMany(ActiveDirectoryGroupMembership::class, 'user_sam_account_name', 'sam_account_name')
            ->where('active_directory_group_memberships.company_id', $this->company_id);
    }

    /**
     * Scope to only enabled users
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to only disabled users
     */
    public function scopeDisabled($query)
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to users in a specific department
     */
    public function scopeInDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Check if user is active (enabled and not locked out)
     */
    public function isActive(): bool
    {
        return $this->enabled && !$this->locked_out;
    }

    /**
     * Check if password is expired (based on last set and common 90-day policy)
     */
    public function isPasswordExpired(int $daysUntilExpiry = 90): bool
    {
        if (!$this->password_last_set || $this->password_never_expires) {
            return false;
        }

        return $this->password_last_set->addDays($daysUntilExpiry)->isPast();
    }
}
