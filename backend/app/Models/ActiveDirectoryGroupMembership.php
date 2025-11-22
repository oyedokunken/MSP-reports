<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\CompanyScope;

class ActiveDirectoryGroupMembership extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'group_name',
        'group_dn',
        'user_sam_account_name',
        'user_dn',
        'membership_type',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * Get the company that owns the membership
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the integration that synced this membership
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get the customer this membership belongs to
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user for this membership
     */
    public function user()
    {
        return $this->belongsTo(ActiveDirectoryUser::class, 'user_sam_account_name', 'sam_account_name')
            ->where('active_directory_users.company_id', $this->company_id);
    }

    /**
     * Get the group for this membership
     */
    public function group()
    {
        return $this->belongsTo(ActiveDirectoryGroup::class, 'group_dn', 'distinguished_name')
            ->where('active_directory_groups.company_id', $this->company_id);
    }

    /**
     * Scope to direct memberships
     */
    public function scopeDirect($query)
    {
        return $query->where('membership_type', 'Direct');
    }

    /**
     * Scope to nested memberships
     */
    public function scopeNested($query)
    {
        return $query->where('membership_type', 'Nested');
    }

    /**
     * Scope by group name
     */
    public function scopeForGroup($query, string $groupName)
    {
        return $query->where('group_name', $groupName);
    }

    /**
     * Scope by user
     */
    public function scopeForUser($query, string $samAccountName)
    {
        return $query->where('user_sam_account_name', $samAccountName);
    }
}
