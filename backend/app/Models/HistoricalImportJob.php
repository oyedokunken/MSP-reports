<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

/**
 * @property int $historical_import_job_id
 * @property int $company_id
 * @property int $integration_id
 * @property \Carbon\Carbon $import_start_date
 * @property \Carbon\Carbon $import_end_date
 * @property \Carbon\Carbon|null $current_position
 * @property string $status
 * @property int $total_records
 * @property int $records_imported
 * @property int|null $progress_percent
 * @property int $error_count
 * @property int $priority_level
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property int $jobs_completed
 * @property int $total_jobs
 * @property-read bool $is_active
 * @property-read string $progress_formatted
 * @property-read int $progress_percentage
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Integration $integration
 */
class HistoricalImportJob extends Model
{

    protected $primaryKey = 'historical_import_job_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    protected $fillable = [
        'company_id',
        'integration_id',
        'import_start_date',
        'import_end_date',
        'current_position',
        'status',
        'total_records',
        'records_imported',
        'progress_percent',
        'error_count',
        'priority_level',
        'started_at',
        'completed_at',
        'jobs_completed',
        'total_jobs',
    ];

    protected $casts = [
        'import_start_date' => 'datetime',
        'import_end_date' => 'datetime',
        'current_position' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'progress_percentage',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Integration, $this>
     */
    public function integration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['queued', 'running', 'paused']);
    }

    public function getProgressFormattedAttribute(): string
    {
        return "{$this->progress_percent}%";
    }

    public function getProgressPercentageAttribute(): int
    {
        return $this->progress_percent ?? 0;
    }
}
