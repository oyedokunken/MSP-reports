<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerMappingFactory extends Factory
{
    protected $model = CustomerMapping::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'integration_customer_id' => IntegrationCustomer::factory(),
            'mapping_type' => 'manual',
            'confidence_score' => 100,
            'mapped_by_user_id' => null,
            'mapped_at' => now(),
            'notes' => null,
        ];
    }
}
