<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'report_schedule_id';

    protected $fillable = [
        'company_id',
        'customer_id',
        'report_type',
        'frequency',
        'schedule_config',
        'time_of_day',
        'active',
        'last_generated_at',
        'next_scheduled_at',
        'timezone',
    ];

    protected $casts = [
        'schedule_config' => 'array',
        'last_generated_at' => 'datetime',
        'next_scheduled_at' => 'datetime',
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

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'report_schedule_id', 'report_schedule_id');
    }
}
