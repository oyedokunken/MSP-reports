<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\CompanyScope;

class HistoricalImportJob extends Model
{
    use HasFactory;

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

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function integration()
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
