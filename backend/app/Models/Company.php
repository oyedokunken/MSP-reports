<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\ApiKey;
use App\Models\Integration;
use App\Models\Customer;
use App\Models\License;
use App\Models\AuditLog;
use App\Traits\AuditTrail;

class Company extends Model
{
    use HasFactory, AuditTrail;

    protected $primaryKey = 'company_id';

    protected $fillable = [
        'name',
        'abn',
        'contact_email',
        'contact_phone',
        'address',
        'status',
        'subscription_tier',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    

    public function users()
    {
        return $this->hasMany(User::class, 'company_id', 'company_id');
    }

    public function integrations()
    {
        return $this->hasMany(Integration::class, 'company_id', 'company_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'company_id', 'company_id');
    }

    public function licenses()
    {
        return $this->hasMany(License::class, 'company_id', 'company_id');
    }

    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class, 'company_id', 'company_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'company_id', 'company_id');
    }

    public function reportSchedules()
    {
        return $this->hasMany(ReportSchedule::class, 'company_id', 'company_id');
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'company_id', 'company_id');
    }

    public function historicalImportJobs()
    {
        return $this->hasMany(HistoricalImportJob::class, 'company_id', 'company_id');
    }

}
