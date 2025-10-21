<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class Integration extends Model
{
    use HasFactory;

    protected $primaryKey = 'integration_id';

    /**
     * The "booted" method of the model.
     * Apply global scope for multi-tenancy isolation.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'integration_type',
        'display_name',
        'status',
        'last_sync_at',
        'last_sync_status',
        'error_message',
        'credentials',
        'configuration',
        'is_source_of_truth',
        'historical_import_status',
        'historical_import_progress',
        'last_historical_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_historical_sync_at' => 'datetime',
        'credentials' => 'array',
        'configuration' => 'array',
        'is_source_of_truth' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function integrationCustomers()
    {
        return $this->hasMany(IntegrationCustomer::class, 'integration_id', 'integration_id');
    }

    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class, 'integration_id', 'integration_id');
    }

    public function historicalImportJobs()
    {
        return $this->hasMany(HistoricalImportJob::class, 'integration_id', 'integration_id');
    }

    public function integrationData()
    {
        return $this->hasMany(IntegrationData::class, 'integration_id', 'integration_id');
    }

    public function canActivate(): bool
    {
        return $this->company->licenses()
            ->where('integration_type', $this->integration_type)
            ->where('status', 'active')
            ->exists();
    }
}
