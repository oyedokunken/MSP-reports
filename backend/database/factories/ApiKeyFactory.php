<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'created_by_user_id' => User::factory(),
            'display_name' => $this->faker->word() . ' API Key',
            'key_preview' => 'rep_test...',
            'key_hash' => hash('sha256', Str::random(40)),
            'permissions' => [
                'reports' => ['view' => true],
            ],
            'revoked' => false,
        ];
    }
}
