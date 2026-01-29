<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;

class License extends Model
{
    protected $primaryKey = 'license_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'license_type',
        'product_sku',
        'status',
        'expiry_date',
        'quantity',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expiry_date' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
