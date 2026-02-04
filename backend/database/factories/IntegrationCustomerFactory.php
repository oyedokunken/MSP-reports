<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\IntegrationCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationCustomerFactory extends Factory
{
    protected $model = IntegrationCustomer::class;

    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'external_id' => fake()->uuid(),
            'name' => fake()->company(),
            'metadata' => [],
            'active' => true,
            'last_seen_at' => now(),
        ];
    }
}
