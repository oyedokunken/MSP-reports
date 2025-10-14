<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class License extends Model
{
    use HasFactory;

    protected $primaryKey = 'license_id';

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

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
