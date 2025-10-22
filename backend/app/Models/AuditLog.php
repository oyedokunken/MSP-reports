<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\Scopes\CompanyScope;

class AuditLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'audit_log_id';
    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'event_type',
        'actor_type',
        'actor_id',
        'target_type',
        'target_id',
        'event_data',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
    ];

    
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function actor()
    {
        return $this->morphTo();
    }

    public function target()
    {
        return $this->morphTo();
    }
}
