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
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the integration that synced this membership
     * 
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get the customer this membership belongs to
     * 
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user for this membership
     * 
     * @return BelongsTo<ActiveDirectoryUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ActiveDirectoryUser::class, 'user_sam_account_name', 'sam_account_name')
            ->where('active_directory_users.company_id', $this->company_id);
    }

    /**
     * Get the group for this membership
     * 
     * @return BelongsTo<ActiveDirectoryGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ActiveDirectoryGroup::class, 'group_dn', 'distinguished_name')
            ->where('active_directory_groups.company_id', $this->company_id);
    }

    /**
     * Scope to direct memberships
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeDirect($query)
    {
        return $query->where('membership_type', 'Direct');
    }

    /**
     * Scope to nested memberships
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeNested($query)
    {
        return $query->where('membership_type', 'Nested');
    }

    /**
     * Scope by group name
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param string $groupName
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeForGroup($query, string $groupName)
    {
        return $query->where('group_name', $groupName);
    }

    /**
     * Scope by user
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param string $samAccountName
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeForUser($query, string $samAccountName)
    {
        return $query->where('user_sam_account_name', $samAccountName);
    }
}
