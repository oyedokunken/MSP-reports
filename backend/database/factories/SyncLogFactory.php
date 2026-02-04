<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'sync_type' => 'current_state',
            'started_at' => now(),
            'completed_at' => null,
            'status' => 'running',
            'records_synced' => 0,
            'records_failed' => 0,
            'rate_limit_hits' => 0,
            'error_messages' => [],
            'customers_mapped' => [],
        ];
    }
}
