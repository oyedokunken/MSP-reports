<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\CompanyScope;

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
    }

    /**
     * Get the company that owns the group
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the integration that synced this group
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get the customer this group belongs to
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get memberships for this group
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ActiveDirectoryGroupMembership::class, 'group_dn', 'distinguished_name')
            ->where('active_directory_group_memberships.company_id', $this->company_id);
    }

    /**
     * Scope to security groups only
     */
    public function scopeSecurity($query)
    {
        return $query->where('group_category', 'Security');
    }

    /**
     * Scope to distribution groups only
     */
    public function scopeDistribution($query)
    {
        return $query->where('group_category', 'Distribution');
    }

    /**
     * Scope to global scope groups
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
