<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FirstCustomerSeeder extends Seeder
{
    /**
     * Seed first customer (Territory Technology Solutions) with all required data.
     * 
     * Run this for production deployment:
     * php artisan db:seed --class=FirstCustomerSeeder
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            LicenseSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('First Customer Setup Complete!');
        $this->command->info('========================================');
        $this->command->info('Company: Territory Technology Solutions');
        $this->command->info('Admin Email: admin@tts.com.au');
        $this->command->info('Admin Password: ChangeMe123!');
        $this->command->info('');
        $this->command->warn('IMPORTANT: Change the default password immediately after first login!');
        $this->command->info('========================================');
    }
}
