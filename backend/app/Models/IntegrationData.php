<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class IntegrationData extends Model
{
    use HasFactory;

    protected $primaryKey = 'integration_data_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'integration_id',
        'integration_customer_id',
        'data_type',
        'data_timestamp',
        'data_payload',
        'imported_at',
        'data_version',
    ];

    protected $casts = [
        'data_timestamp' => 'datetime',
        'imported_at' => 'datetime',
        'data_payload' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    public function integrationCustomer()
    {
        return $this->belongsTo(IntegrationCustomer::class, 'integration_customer_id', 'integration_customer_id');
    }
}
