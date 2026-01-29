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
     * Seed users for each default role.
     */
    public function run(): void
    {
        $company = Company::where('name', 'Territory Technology Solutions')->first();

        if (!$company) {
            $this->command->error('Company not found. Run CompanySeeder first.');
            return;
        }

        $roles = Role::all();
        $password = 'password123';

        // 1. Admin User
        $adminRole = $roles->where('name', 'Admin')->first();
        if ($adminRole) {
            User::firstOrCreate(
                ['email' => 'admin@tts.com.au'],
                [
                    'company_id' => $company->company_id,
                    'role_id' => $adminRole->id,
                    'name' => 'TTS Administrator',
                    'password' => Hash::make('ChangeMe123!'),
                    'status' => 'active',
                    'auth_method' => 'local',
                    'permissions' => RolePermissionService::getDefaultPermissions('Admin'),
                    'settings' => [
                        'theme' => 'light',
                        'notifications' => ['email' => true, 'browser' => true],
                    ],
                    'email_verified_at' => now(),
                ]
            );

            // Kristian Admin
            User::firstOrCreate(
                ['email' => 'kristianbas14@gmail.com'],
                [
                    'company_id' => $company->company_id,
                    'role_id' => $adminRole->id,
                    'name' => 'Kristian',
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'auth_method' => 'local',
                    'permissions' => RolePermissionService::getDefaultPermissions('Admin'),
                    'settings' => ['theme' => 'dark'],
                    'email_verified_at' => now(),
                ]
            );
        }

        // 2. Manager User
        $managerRole = $roles->where('name', 'Manager')->first();
        if ($managerRole) {
            User::firstOrCreate(
                ['email' => 'manager@tts.com.au'],
                [
                    'company_id' => $company->company_id,
                    'role_id' => $managerRole->id,
                    'name' => 'Territory Manager',
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'auth_method' => 'local',
                    'permissions' => RolePermissionService::getDefaultPermissions('Manager'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // 3. Technician User
        $techRole = $roles->where('name', 'Technician')->first();
        if ($techRole) {
            User::firstOrCreate(
                ['email' => 'technician@tts.com.au'],
                [
                    'company_id' => $company->company_id,
                    'role_id' => $techRole->id,
                    'name' => 'Field Technician',
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'auth_method' => 'local',
                    'permissions' => RolePermissionService::getDefaultPermissions('Technician'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // 4. Viewer User
        $viewerRole = $roles->where('name', 'Viewer')->first();
        if ($viewerRole) {
            User::firstOrCreate(
                ['email' => 'viewer@tts.com.au'],
                [
                    'company_id' => $company->company_id,
                    'role_id' => $viewerRole->id,
                    'name' => 'Internal Viewer',
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'auth_method' => 'local',
                    'permissions' => RolePermissionService::getDefaultPermissions('Viewer'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // 5. Customer User
        $customerRole = $roles->where('name', 'Customer')->first();
        if ($customerRole) {
            User::firstOrCreate(
                ['email' => 'customer@tts.com.au'],
                [
                    'company_id' => $company->company_id,
                    'role_id' => $customerRole->id,
                    'name' => 'TTS Client User',
                    'password' => Hash::make($password),
                    'status' => 'active',
                    'auth_method' => 'local',
                    'scoped_customers' => [1], // Scoped to first customer example
                    'permissions' => RolePermissionService::getDefaultPermissions('Customer'),
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command->info('All 5 standard users seeded successfully.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@tts.com.au', 'ChangeMe123!'],
                ['Manager', 'manager@tts.com.au', 'password123'],
                ['Technician', 'technician@tts.com.au', 'password123'],
                ['Viewer', 'viewer@tts.com.au', 'password123'],
                ['Customer', 'customer@tts.com.au', 'password123'],
            ]
        );
    }
}
