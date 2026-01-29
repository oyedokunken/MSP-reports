<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

/**
 * @property int $integration_data_id
 * @property int $company_id
 * @property int $integration_id
 * @property int|null $integration_customer_id
 * @property string|null $external_id
 * @property string $data_type
 * @property \Carbon\Carbon $data_timestamp
 * @property array<string, mixed> $data_payload
 * @property \Carbon\Carbon $imported_at
 * @property int $data_version
 * @property int|null $resource_id
 * @property int|null $ticket_count
 * @property int|null $count
 * @property string|null $month
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Integration $integration
 * @property-read \App\Models\IntegrationCustomer|null $integrationCustomer
 */
class IntegrationData extends Model
{

    protected $primaryKey = 'integration_data_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    protected $fillable = [
        'company_id',
        'integration_id',
        'integration_customer_id',
        'external_id',
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Integration, $this>
     */
    public function integration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<IntegrationCustomer, $this>
     */
    public function integrationCustomer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IntegrationCustomer::class, 'integration_customer_id', 'integration_customer_id');
    }
}
