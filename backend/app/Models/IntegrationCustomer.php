<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

/**
 * @property int $integration_customer_id
 * @property int $integration_id
 * @property int|null $customer_id
 * @property string $external_id
 * @property string $name
 * @property array|null $metadata
 * @property bool $active
 * @property \Carbon\Carbon|null $last_seen_at
 * @property-read \App\Models\Integration $integration
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\Customer> $customers
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\CustomerMapping> $customerMappings
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\IntegrationData> $integrationData
 */
class IntegrationCustomer extends Model
{
    use HasFactory;

    protected $primaryKey = 'integration_customer_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    protected $fillable = [
        'integration_id',
        'customer_id',
        'external_id',
        'name',
        'metadata',
        'active',
        'last_seen_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function integration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }
    
    public function customers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_mappings',
            'integration_customer_id',
            'customer_id'
        );
    }

    public function customerMappings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerMapping::class, 'integration_customer_id', 'integration_customer_id');
    }

    
    public function integrationData(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IntegrationData::class, 'integration_customer_id', 'integration_customer_id');
    }
}
