<?php

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use App\Models\User;
// use App\Models\Company;
// use App\Models\Role;
// use App\Models\Integration;
// use Illuminate\Support\Facades\Hash;

// class DatabaseSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $adminRole = Role::firstOrCreate(['name' => 'admin']);
//         $userRole = Role::firstOrCreate(['name' => 'user']);

//         $company = Company::create([
//             'name' => 'Test Company',
//             'contact_email' => 'contact@test.com',
//         ]);

//         User::create([
//             'company_id' => $company->company_id,
//             'role_id' => $adminRole->id,
//             'name' => 'Admin User',
//             'email' => 'admin@test.com',
//             'password' => Hash::make('password123'),
//             'auth_method' => 'local',
//         ]);

//         User::create([
//             'company_id' => $company->company_id,
//             'role_id' => $userRole->id,
//             'name' => 'Normal User',
//             'email' => 'user@test.com',
//             'password' => Hash::make('password123'),
//             'auth_method' => 'local',
//         ]);

//         $defaultIntegrations = [
//             ['integration_type' => 'Datto', 'display_name' => 'Datto Backup', 'status' => 'active'],
//             ['integration_type' => 'Autotask', 'display_name' => 'Autotask Tickets', 'status' => 'disconnected'],
//             ['integration_type' => 'ConnectWise', 'display_name' => 'ConnectWise PSA', 'status' => 'disconnected'],
//             ['integration_type' => 'Microsoft365', 'display_name' => 'Microsoft 365', 'status' => 'disconnected'],
//             ['integration_type' => 'Zendesk', 'display_name' => 'Zendesk Support', 'status' => 'disconnected'],
//         ];

//         foreach ($defaultIntegrations as $integration) {
//             Integration::create(array_merge($integration, ['company_id' => $company->company_id]));
//         }
//     }
// }

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Models\License;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles if not existing
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create or get a test company
        $company = Company::firstOrCreate(
            ['contact_email' => 'contact@test.com'],
            ['name' => 'Test Company']
        );

        // Create admin user (skip if exists)
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'company_id' => $company->company_id,
                'role_id' => $adminRole->id,
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'auth_method' => 'local',
            ]
        );

        // Create normal user (skip if exists)
        User::firstOrCreate(
            ['email' => 'user@test.com'],
            [
                'company_id' => $company->company_id,
                'role_id' => $userRole->id,
                'name' => 'Normal User',
                'password' => Hash::make('password123'),
                'auth_method' => 'local',
            ]
        );

        // Create perpetual Datto integration license
        License::firstOrCreate(
            [
                'company_id' => $company->company_id,
                'product_sku' => 'integration_datto',
            ],
            [
                'license_type' => 'integration',
                'status' => 'active',
                'expiry_date' => null,
                'quantity' => null,
                'metadata' => ['note' => 'Perpetual license for testing'],
            ]
        );

        // ✅ Removed default integrations to allow testing via API
    }
}
