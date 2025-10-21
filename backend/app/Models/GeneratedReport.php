<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class GeneratedReport extends Model
{
    use HasFactory;

    protected $primaryKey = 'generated_report_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'customer_id',
        'report_schedule_id',
        'generated_at',
        'report_period',
        'status',
        'file_path',
        'file_size',
        'delivered',
        'delivered_at',
        'delivery_method',
        'error_message',
        'retention_until',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'report_period' => 'array',
        'delivered_at' => 'datetime',
        'retention_until' => 'datetime',
        'delivered' => 'boolean',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function reportSchedule()
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id', 'report_schedule_id');
    }
}
