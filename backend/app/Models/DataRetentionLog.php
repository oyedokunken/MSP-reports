<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class DataRetentionLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'data_retention_log_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'record_type',
        'records_purged',
        'retention_policy',
        'purged_at',
    ];

    protected $casts = [
        'retention_policy' => 'array',
        'purged_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }
}
