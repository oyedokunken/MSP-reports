<?php

declare(strict_types=1);

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

/**
 * @property int $company_id
 * @property string $name
 * @property string|null $abn
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address
 * @property string $status
 * @property string $subscription_tier
 * @property array<string, mixed>|null $settings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Integration> $integrations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Customer> $customers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\License> $licenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApiKey> $apiKeys
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReportSchedule> $reportSchedules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GeneratedReport> $generatedReports
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HistoricalImportJob> $historicalImportJobs
 */
class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<User, $this>
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Integration, $this>
     */
    public function integrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Integration::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Customer, $this>
     */
    public function customers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Customer::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<License, $this>
     */
    public function licenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(License::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ApiKey, $this>
     */
    public function apiKeys(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApiKey::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<AuditLog, $this>
     */
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditLog::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ReportSchedule, $this>
     */
    public function reportSchedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReportSchedule::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<GeneratedReport, $this>
     */
    public function generatedReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<HistoricalImportJob, $this>
     */
    public function historicalImportJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HistoricalImportJob::class, 'company_id', 'company_id');
    }

}
