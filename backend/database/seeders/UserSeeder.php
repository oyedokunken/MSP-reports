<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed admin user for first customer.
     */
    public function run(): void
    {
        $company = Company::where('name', 'Territory Technology Solutions')->first();

        if (!$company) {
            $this->command->error('Company not found. Run CompanySeeder first.');
            return;
        }

        // Get or create admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Full system administrator']
        );

        // Create admin user (if not exists)
        User::firstOrCreate(
            ['email' => 'admin@tts.com.au'],
            [
            'company_id' => $company->company_id,
            'role_id' => $adminRole->id,
            'name' => 'TTS Administrator',
            'email' => 'admin@tts.com.au',
            'password' => Hash::make('ChangeMe123!'),
            'status' => 'active',
            'auth_method' => 'local',
            'permissions' => [
                'integrations' => [
                    'view' => true,
                    'manage' => true,
                    'sync' => true,
                ],
                'customers' => [
                    'view' => true,
                    'edit' => true,
                    'map' => true,
                ],
                'reports' => [
                    'view' => true,
                    'configure' => true,
                    'generate' => true,
                    'download' => true,
                ],
                'users' => [
                    'view' => true,
                    'manage' => true,
                ],
                'company_settings' => [
                    'view' => true,
                    'edit' => true,
                ],
            ],
            'email_verified_at' => now(),
            ]
        );

        // Create test user (Kristian) - if not exists
        User::firstOrCreate(
            ['email' => 'kristianbas14@gmail.com'],
            [
            'company_id' => $company->company_id,
            'role_id' => $adminRole->id,
            'name' => 'Kristian Bas',
            'email' => 'kristianbas14@gmail.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'auth_method' => 'local',
            'permissions' => [
                'integrations' => [
                    'view' => true,
                    'manage' => true,
                    'sync' => true,
                ],
                'customers' => [
                    'view' => true,
                    'edit' => true,
                    'map' => true,
                ],
                'reports' => [
                    'view' => true,
                    'configure' => true,
                    'generate' => true,
                    'download' => true,
                ],
                'users' => [
                    'view' => true,
                    'manage' => true,
                ],
                'company_settings' => [
                    'view' => true,
                    'edit' => true,
                ],
            ],
            'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created: admin@tts.com.au (password: ChangeMe123!)');
        $this->command->info('Test user created: kristianbas14@gmail.com (password: password123)');
        $this->command->warn('IMPORTANT: Change the default password after first login!');
    }
}
