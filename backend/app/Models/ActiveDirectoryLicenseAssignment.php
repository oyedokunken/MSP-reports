<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

class ActiveDirectoryLicenseAssignment extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'user_sam_account_name',
        'user_dn',
        'license_name',
        'license_type',
        'cost_per_user',
        'assigned_via_group',
        'assigned_via_group_dn',
    ];

    protected $casts = [
        'cost_per_user' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope to specific license type (e.g., SPLA)
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('license_type', $type);
    }

    /**
     * Scope to specific license name
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param string $licenseName
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeForLicense($query, string $licenseName)
    {
        return $query->where('license_name', $licenseName);
    }
}
