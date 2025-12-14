<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationCustomer extends Model
{
    use HasFactory;

    protected $primaryKey = 'integration_customer_id';

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

    public function integration()
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }
    
    public function customers()
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_mappings',
            'integration_customer_id',
            'customer_id'
        );
    }

    public function customerMappings()
    {
        return $this->hasMany(CustomerMapping::class, 'integration_customer_id', 'integration_customer_id');
    }

    
    public function integrationData()
    {
        return $this->hasMany(IntegrationData::class, 'integration_customer_id', 'integration_customer_id');
    }
}
