<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

class CustomerMapping extends Model
{
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function integrationCustomer()
    {
        return $this->belongsTo(IntegrationCustomer::class, 'integration_customer_id', 'integration_customer_id');
    }

    public function mappedByUser()
    {
        return $this->belongsTo(User::class, 'mapped_by_user_id', 'user_id');
    }
}
