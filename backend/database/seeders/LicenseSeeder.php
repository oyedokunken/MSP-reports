<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\License;
use App\Models\Company;

class LicenseSeeder extends Seeder
{
    /**
     * Seed unlimited license for first customer.
     */
    public function run(): void
    {
        $company = Company::where('name', 'Territory Technology Solutions')->first();

        if (!$company) {
            $this->command->error('Company not found. Run CompanySeeder first.');
            return;
        }

        // Create unlimited license
        License::create([
            'company_id' => $company->company_id,
            'license_type' => 'unlimited',
            'product_sku' => 'unlimited',
            'status' => 'active',
            'expiry_date' => null, // Perpetual
            'quantity' => null, // Unlimited
            'metadata' => [
                'description' => 'Unlimited access to all features',
                'includes' => [
                    'all_integrations',
                    'unlimited_retention',
                    'unlimited_users',
                    'unlimited_reports',
                ],
                'deployment_type' => 'on_premise',
                'customer' => 'Territory Technology Solutions',
            ],
        ]);

        $this->command->info('Unlimited license created for Territory Technology Solutions');
    }
}
