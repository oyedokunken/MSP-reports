<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IntegrationData;
use App\Models\IntegrationCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IntegrationData>
 */
class IntegrationDataFactory extends Factory
{
    protected $model = IntegrationData::class;

    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'integration_customer_id' => IntegrationCustomer::factory(),
            'data_type' => 'ticket',
            'external_id' => $this->faker->unique()->uuid(),
            'data_timestamp' => now(),
            'data_payload' => [
                'status' => 1,
                'created_date' => now()->subDay()->toDateTimeString(),
                'title' => 'Test Ticket',
            ],
        ];
    }
}
