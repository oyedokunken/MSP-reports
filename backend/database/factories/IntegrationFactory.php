<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Integration;
use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'integration_type' => 'Autotask',
            'display_name' => 'Autotask Integration',
            'status' => IntegrationStatus::ACTIVE,
            'last_sync_at' => null,
            'last_sync_status' => 'pending',
            'error_message' => null,
            'credentials' => [],
            'configuration' => [],
            'is_source_of_truth' => false,
            'historical_import_status' => 'pending',
            'historical_import_progress' => 0,
            'last_historical_sync_at' => null,
            'sync_schedule' => '0 * * * *',
        ];
    }
}
