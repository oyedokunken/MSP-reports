<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

/**
 * @property int $customer_mapping_id
 * @property int $customer_id
 * @property int $integration_customer_id
 * @property string $mapping_type
 * @property int|null $confidence_score
 * @property int|null $mapped_by_user_id
 * @property \Carbon\Carbon|null $mapped_at
 * @property string|null $notes
 * @property-read \App\Models\Customer $customer
 * @property-read \App\Models\IntegrationCustomer $integrationCustomer
 * @property-read \App\Models\User|null $mappedByUser
 */
class CustomerMapping extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerMappingFactory> */
    use HasFactory;

    protected $primaryKey = 'customer_mapping_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    protected $fillable = [
        'customer_id',
        'integration_customer_id',
        'mapping_type',
        'confidence_score',
        'mapped_by_user_id',
        'mapped_at',
        'notes',
    ];

    protected $casts = [
        'mapped_at' => 'datetime',
        'confidence_score' => 'integer',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Customer, $this>
     */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<IntegrationCustomer, $this>
     */
    public function integrationCustomer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IntegrationCustomer::class, 'integration_customer_id', 'integration_customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function mappedByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by_user_id', 'user_id');
    }
}
