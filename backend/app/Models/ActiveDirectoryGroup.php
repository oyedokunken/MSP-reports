<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

class ActiveDirectoryGroup extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'name',
        'sam_account_name',
        'group_category',
        'group_scope',
        'description',
        'created_at_ad',
        'modified_at_ad',
        'distinguished_name',
        'managed_by',
        'managed_by_dn',
    ];

    protected $casts = [
        'created_at_ad' => 'datetime',
        'modified_at_ad' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    /**
     * Get the company that owns the group
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the integration that synced this group
     * 
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get the customer this group belongs to
     * 
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get memberships for this group
     * 
     * @return HasMany<ActiveDirectoryGroupMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ActiveDirectoryGroupMembership::class, 'group_dn', 'distinguished_name')
            ->where('active_directory_group_memberships.company_id', $this->company_id);
    }

    /**
     * Scope to security groups only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeSecurity($query)
    {
        return $query->where('group_category', 'Security');
    }

    /**
     * Scope to distribution groups only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeDistribution($query)
    {
        return $query->where('group_category', 'Distribution');
    }

    /**
     * Scope to global scope groups
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeGlobalScope($query)
    {
        return $query->where('group_scope', 'Global');
    }

    /**
     * Get member count
     */
    public function getMemberCountAttribute(): int
    {
        return $this->memberships()->count();
    }
}
