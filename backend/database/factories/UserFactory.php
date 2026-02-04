<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'company_id' => \App\Models\Company::factory(),
            'role_id' => Role::where('name', 'Admin')->value('id') ?? Role::factory(),
            'status' => 'active',
            'auth_method' => 'local',
            'permissions' => [
                'integrations' => ['view' => true, 'manage' => false, 'sync' => false],
                'customers' => ['view' => true, 'edit' => false, 'map' => false],
                'reports' => ['view' => true, 'configure' => false, 'generate' => false, 'download' => true],
                'users' => ['view' => false, 'manage' => false],
                'company_settings' => ['view' => false, 'edit' => false]
            ],
            'last_login_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
