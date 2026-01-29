<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Integration;

/**
 * @property int $sync_id
 * @property int $integration_id
 * @property string $sync_type
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property string $status
 * @property int $records_synced
 * @property int $records_failed
 * @property array|null $error_messages
 * @property int $rate_limit_hits
 * @property array|null $customers_mapped
 * @property-read \App\Models\Integration $integration
 */
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

    public function integration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }
}
