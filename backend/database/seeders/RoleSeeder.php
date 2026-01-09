<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed default roles.
     */
    public function run(): void
    {
        $roles = [
            'Admin',
            'Manager',
            'Viewer',
            'Technician',
            'Customer',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName]
            );
        }

        $this->command->info('Default roles created');
    }
}
