<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;
use App\Traits\AuditTrail;

/**
 * @property int $integration_id
 * @property int $company_id
 * @property string $integration_type
 * @property string $display_name
 * @property string $status
 * @property \Carbon\Carbon|null $last_sync_at
 * @property string $last_sync_status
 * @property string|null $error_message
 * @property array|null $credentials
 * @property array|null $configuration
 * @property bool $is_source_of_truth
 * @property string $historical_import_status
 * @property int|null $historical_import_progress
 * @property \Carbon\Carbon|null $last_historical_sync_at
 * @property string|null $sync_schedule
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\IntegrationCustomer> $integrationCustomers
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\SyncLog> $syncLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\HistoricalImportJob> $historicalImportJobs
 * @property-read \Illuminate\Database\Eloquent\Collection<\App\Models\IntegrationData> $integrationData
 */
class Integration extends Model
{
    use HasFactory, AuditTrail;

    protected $primaryKey = 'integration_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::deleting(function (Integration $integration) {
            try {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $prefix = config('database.redis.options.prefix');
                $queues = ['high', 'default', 'low'];
                $jobsRemoved = 0;
                
                foreach ($queues as $queueName) {
                    $queueKey = "{$prefix}queues:{$queueName}";
                    
                    $jobs = $redis->lrange($queueKey, 0, -1);
                    
                    foreach ($jobs as $job) {
                        $payload = json_decode($job, true);
                        
                        if (!isset($payload['displayName']) || $payload['displayName'] !== 'App\\Jobs\\SyncIntegrationJob') {
                            continue;
                        }
                        
         
                        $command = $payload['data']['command'] ?? '';
                        $pattern = '/s:2:"id";i:' . $integration->integration_id . ';/';
                        
                        if (preg_match($pattern, $command)) {
                            $removed = $redis->lrem($queueKey, 0, $job);
                            
                            if ($removed > 0) {
                                $jobsRemoved++;
                                \Illuminate\Support\Facades\Log::info('Removed queued job for deleted integration', [
                                    'integration_id' => $integration->integration_id,
                                    'queue' => $queueName,
                                    'job_id' => $payload['uuid'] ?? 'unknown',
                                ]);
                            }
                        }
                    }
                }
                
                if ($jobsRemoved > 0) {
                    \Illuminate\Support\Facades\Log::info('Queue cleanup completed', [
                        'integration_id' => $integration->integration_id,
                        'jobs_removed' => $jobsRemoved,
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to clean up queued jobs for deleted integration', [
                    'integration_id' => $integration->integration_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
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
        'sync_schedule',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_historical_sync_at' => 'datetime',
        'credentials' => 'encrypted:array',
        'configuration' => 'array',
        'is_source_of_truth' => 'boolean',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function integrationCustomers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IntegrationCustomer::class, 'integration_id', 'integration_id');
    }

    public function syncLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SyncLog::class, 'integration_id', 'integration_id');
    }

    public function historicalImportJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HistoricalImportJob::class, 'integration_id', 'integration_id');
    }

    public function integrationData(): \Illuminate\Database\Eloquent\Relations\HasMany
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
