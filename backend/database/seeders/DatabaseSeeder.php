<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        $company = Company::create([
            'name' => 'Test Company',
            'contact_email' => 'contact@test.com',
        ]);

        User::create([
            'company_id' => $company->company_id,
            'role_id' => $adminRole->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'auth_method' => 'local',
        ]);

        User::create([
            'company_id' => $company->company_id,
            'role_id' => $userRole->id,
            'name' => 'Normal User',
            'email' => 'user@test.com',
            'password' => Hash::make('password123'),
            'auth_method' => 'local',
        ]);
    }
}
