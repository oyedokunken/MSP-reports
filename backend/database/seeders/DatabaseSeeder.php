<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Models\License;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\Customer;
use App\Models\CustomerMapping;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // === Roles ===
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // === Company ===
        $company = Company::firstOrCreate(
            ['contact_email' => 'contact@test.com'],
            [
                'name' => 'Test Company',
                'status' => 'active',
                'subscription_tier' => 'enterprise',
                'settings' => [
                    'integrations' => [
                        'auto_sync_enabled' => true,
                        'sync_schedule' => '0 */4 * * *',
                    ],
                ],
            ]
        );

        // === Users ===
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'company_id' => $company->company_id,
                'role_id' => $adminRole->id,
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'auth_method' => 'local',
            ]
        );

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

        // === License for Datto ===
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

        // === License for Autotask ===
        License::firstOrCreate(
            [
                'company_id' => $company->company_id,
                'product_sku' => 'integration_autotask',
            ],
            [
                'license_type' => 'integration',
                'status' => 'active',
                'expiry_date' => null,
                'quantity' => null,
                'metadata' => ['note' => 'Perpetual license for Autotask testing'],
            ]
        );

        // === Integration ===
        $integration = Integration::firstOrCreate(
            [
                'company_id' => $company->company_id,
                'integration_type' => 'Datto',
            ],
            [
                'display_name' => 'Datto Backup',
                'status' => 'active',
                'is_source_of_truth' => false,
            ]
        );

        // === Unified Customers (for mapped) ===
        $customerA = Customer::firstOrCreate(
            [
                'company_id' => $company->company_id,
                'canonical_name' => 'Acme Corp',
            ],
            [
                'status' => 'active',
                'metadata' => ['industry' => 'IT Services'],
            ]
        );

        // === Integration Customers ===
        $integrationCustomers = [
            ['external_id' => 'D001', 'customer_name' => 'Acme Corp', 'active' => true],
            ['external_id' => 'D002', 'customer_name' => 'Beta Solutions', 'active' => true],
            ['external_id' => 'D003', 'customer_name' => 'Gamma Holdings', 'active' => false],
        ];

        foreach ($integrationCustomers as $data) {
            IntegrationCustomer::firstOrCreate(
                [
                    'integration_id' => $integration->integration_id,
                    'external_id' => $data['external_id'],
                    'name' => $data['customer_name'],
                ],
                [
                    'active' => $data['active'],
                    'metadata' => json_encode(['region' => 'AU']),
                    'last_seen_at' => now(),
                ]
            );
        }

        // === Customer Mapping (Acme Corp only) ===
        $integrationCustomer = IntegrationCustomer::where('external_id', 'D001')->first();

        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $customerA->customer_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 95,
                'mapped_by_user_id' => $admin->user_id,
                'mapped_at' => now(),
                'notes' => 'Auto-matched based on name similarity',
            ]
        );

        $this->command->info('✅ Database seeded with mapped and unmapped customers.');
    }
}
