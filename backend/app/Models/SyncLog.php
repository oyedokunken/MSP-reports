<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Integration;

class SyncLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'sync_id';

    protected $fillable = [
        'integration_id',
        'sync_type',
        'started_at',
        'completed_at',
        'status',
        'records_synced',
        'records_failed',
        'error_messages',
        'rate_limit_hits',
        'customers_mapped',
    ];

    protected $casts = [
        'error_messages' => 'array',
        'customers_mapped' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }
}
