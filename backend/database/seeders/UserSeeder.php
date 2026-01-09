<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Services\RolePermissionService;
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

        // Create admin user (if not exists) - Light theme
        $adminUser = User::withoutGlobalScopes()->where('email', 'admin@tts.com.au')->first();
        if (!$adminUser) {
            User::create([
                'company_id' => $company->company_id,
                'role_id' => $adminRole->id,
                'name' => 'TTS Administrator',
                'email' => 'admin@tts.com.au',
                'password' => Hash::make('ChangeMe123!'),
                'status' => 'active',
                'auth_method' => 'local',
                'permissions' => RolePermissionService::getDefaultPermissions('Admin'),
                'settings' => [
                    'theme' => 'light',
                    'notifications' => ['email' => true, 'browser' => true],
                ],
                'email_verified_at' => now(),
            ]);
        }

        // Create test user (Kristian) - Dark theme
        $kristianUser = User::withoutGlobalScopes()->where('email', 'kristianbas14@gmail.com')->first();
        if (!$kristianUser) {
            User::create([
                'company_id' => $company->company_id,
                'role_id' => $adminRole->id,
                'name' => 'Kristian',
                'email' => 'kristianbas14@gmail.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'auth_method' => 'local',
                'permissions' => RolePermissionService::getDefaultPermissions('Admin'),
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => ['email' => true, 'browser' => false],
                ],
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('Admin user created: admin@tts.com.au (password: ChangeMe123!)');
        $this->command->info('Test user created: kristianbas14@gmail.com (password: password123)');

        // Create Scoped Viewer (Test customer-level scoping)
        $viewerRole = Role::where('name', 'Viewer')->first();
        $viewerUser = User::withoutGlobalScopes()->where('email', 'viewer@tts.com.au')->first();
        if (!$viewerUser) {
            User::create([
                'company_id' => $company->company_id,
                'role_id' => $viewerRole->id,
                'name' => 'Scoped Viewer',
                'email' => 'viewer@tts.com.au',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'auth_method' => 'local',
                'scoped_customers' => [1], // Scoped to first customer only
                'permissions' => RolePermissionService::getDefaultPermissions('Viewer'),
            ]);
        }

        $this->command->info('Scoped viewer created: viewer@tts.com.au (password: password123) - Scoped to Customer ID 1');
        $this->command->warn('IMPORTANT: Change the default password after first login!');
    }
}
