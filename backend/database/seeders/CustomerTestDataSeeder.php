<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\User;

class CustomerTestDataSeeder extends Seeder
{
    /**
     * Seed test data for customer mapping endpoints testing.
     * 
     * This creates:
     * - 2 integrations (Datto and Autotask)
     * - 10 integration customers (some mapped, some unmapped)
     * - 5 unified customers
     * - Various mapping scenarios for testing
     */
    public function run(): void
    {
        $company = Company::where('name', 'Territory Technology Solutions')->first();
        
        if (!$company) {
            $this->command->error('Company not found. Run main seeders first.');
            return;
        }

        $admin = User::where('company_id', $company->company_id)->first();

        // === Create Integrations ===
        $dattoIntegration = Integration::firstOrCreate(
            [
                'company_id' => $company->company_id,
                'integration_type' => 'Datto',
            ],
            [
                'display_name' => 'Datto RMM Production',
                'status' => 'active',
                'credentials' => [
                    'api_key' => 'test_datto_key_123',
                    'api_secret' => 'test_datto_secret_456',
                ],
                'configuration' => [
                    'sync_interval' => 3600,
                    'enable_auto_sync' => true,
                ],
                'is_source_of_truth' => true,
                'last_sync_at' => now()->subHours(2),
                'last_sync_status' => 'success',
            ]
        );

        $autotaskIntegration = Integration::firstOrCreate(
            [
                'company_id' => $company->company_id,
                'integration_type' => 'Autotask',
            ],
            [
                'display_name' => 'Autotask PSA',
                'status' => 'active',
                'credentials' => [
                    'username' => 'test_autotask_user',
                    'api_key' => 'test_autotask_key_789',
                ],
                'configuration' => [
                    'sync_interval' => 7200,
                ],
                'is_source_of_truth' => false,
                'last_sync_at' => now()->subHours(1),
                'last_sync_status' => 'success',
            ]
        );

        // === Create Unified Customers (Already Mapped) ===
        $customers = [
            [
                'canonical_name' => 'ACME Corporation',
                'status' => 'active',
                'metadata' => [
                    'industry' => 'Technology',
                    'employees' => 150,
                    'location' => 'Sydney, NSW',
                ],
            ],
            [
                'canonical_name' => 'Beta Solutions Pty Ltd',
                'status' => 'active',
                'metadata' => [
                    'industry' => 'Finance',
                    'employees' => 75,
                    'location' => 'Melbourne, VIC',
                ],
            ],
            [
                'canonical_name' => 'Gamma Tech Group',
                'status' => 'active',
                'metadata' => [
                    'industry' => 'Healthcare',
                    'employees' => 200,
                    'location' => 'Brisbane, QLD',
                ],
            ],
            [
                'canonical_name' => 'Delta Enterprises',
                'status' => 'active',
                'metadata' => [
                    'industry' => 'Retail',
                    'employees' => 50,
                    'location' => 'Perth, WA',
                ],
            ],
            [
                'canonical_name' => 'Epsilon Industries',
                'status' => 'inactive',
                'metadata' => [
                    'industry' => 'Manufacturing',
                    'employees' => 300,
                    'location' => 'Adelaide, SA',
                    'note' => 'Contract ended',
                ],
            ],
        ];

        $createdCustomers = [];
        foreach ($customers as $customerData) {
            $createdCustomers[] = Customer::firstOrCreate(
                [
                    'company_id' => $company->company_id,
                    'canonical_name' => $customerData['canonical_name'],
                ],
                [
                    'status' => $customerData['status'],
                    'metadata' => $customerData['metadata'],
                ]
            );
        }

        // === Create Integration Customers from Datto ===
        $dattoCustomers = [
            // Mapped customers (exact or close matches)
            ['external_id' => 'DATTO-001', 'name' => 'ACME Corp', 'active' => true],
            ['external_id' => 'DATTO-002', 'name' => 'Beta Solutions', 'active' => true],
            ['external_id' => 'DATTO-003', 'name' => 'Gamma Tech', 'active' => true],
            
            // Unmapped customers (need manual mapping)
            ['external_id' => 'DATTO-004', 'name' => 'Zeta Systems Ltd', 'active' => true],
            ['external_id' => 'DATTO-005', 'name' => 'Theta Networks', 'active' => true],
            ['external_id' => 'DATTO-006', 'name' => 'Iota Consulting', 'active' => true],
            
            // Inactive customer
            ['external_id' => 'DATTO-007', 'name' => 'Kappa Corp (OLD)', 'active' => false],
        ];

        $dattoIntegrationCustomers = [];
        foreach ($dattoCustomers as $data) {
            $dattoIntegrationCustomers[] = IntegrationCustomer::firstOrCreate(
                [
                    'integration_id' => $dattoIntegration->integration_id,
                    'external_id' => $data['external_id'],
                ],
                [
                    'name' => $data['name'],
                    'active' => $data['active'],
                    'metadata' => [
                        'region' => 'AU',
                        'sync_source' => 'Datto RMM',
                    ],
                    'last_seen_at' => $data['active'] ? now() : now()->subDays(30),
                ]
            );
        }

        // === Create Integration Customers from Autotask ===
        $autotaskCustomers = [
            // Same customers but with different naming
            ['external_id' => 'AT-1001', 'name' => 'ACME Corporation Pty Ltd', 'active' => true],
            ['external_id' => 'AT-1002', 'name' => 'Beta Solutions P/L', 'active' => true],
            ['external_id' => 'AT-1003', 'name' => 'Delta Enterprises Ltd', 'active' => true],
            
            // Unmapped
            ['external_id' => 'AT-1004', 'name' => 'Lambda Services', 'active' => true],
            ['external_id' => 'AT-1005', 'name' => 'Mu Technologies', 'active' => true],
        ];

        $autotaskIntegrationCustomers = [];
        foreach ($autotaskCustomers as $data) {
            $autotaskIntegrationCustomers[] = IntegrationCustomer::firstOrCreate(
                [
                    'integration_id' => $autotaskIntegration->integration_id,
                    'external_id' => $data['external_id'],
                ],
                [
                    'name' => $data['name'],
                    'active' => $data['active'],
                    'metadata' => [
                        'region' => 'AU',
                        'sync_source' => 'Autotask PSA',
                    ],
                    'last_seen_at' => now(),
                ]
            );
        }

        // === Create Customer Mappings ===
        
        // Map ACME Corp from Datto (auto-matched, high confidence)
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[0]->customer_id, // ACME Corporation
                'integration_customer_id' => $dattoIntegrationCustomers[0]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 95,
                'mapped_by_user_id' => $admin->user_id ?? null,
                'mapped_at' => now()->subDays(5),
                'notes' => 'Auto-matched based on name similarity (95% confidence)',
            ]
        );

        // Map ACME Corp from Autotask (manual mapping)
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[0]->customer_id, // ACME Corporation
                'integration_customer_id' => $autotaskIntegrationCustomers[0]->integration_customer_id,
            ],
            [
                'mapping_type' => 'manual',
                'confidence_score' => 100,
                'mapped_by_user_id' => $admin->user_id ?? null,
                'mapped_at' => now()->subDays(3),
                'notes' => 'Manually confirmed - same customer across both systems',
            ]
        );

        // Map Beta Solutions from Datto (auto-matched, medium confidence)
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[1]->customer_id, // Beta Solutions
                'integration_customer_id' => $dattoIntegrationCustomers[1]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 88,
                'mapped_by_user_id' => $admin->user_id ?? null,
                'mapped_at' => now()->subDays(4),
                'notes' => 'Auto-matched with good confidence',
            ]
        );

        // Map Beta Solutions from Autotask (manual)
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[1]->customer_id, // Beta Solutions
                'integration_customer_id' => $autotaskIntegrationCustomers[1]->integration_customer_id,
            ],
            [
                'mapping_type' => 'manual',
                'confidence_score' => 100,
                'mapped_by_user_id' => $admin->user_id ?? null,
                'mapped_at' => now()->subDays(2),
                'notes' => 'Manually mapped after reviewing customer records',
            ]
        );

        // Map Gamma Tech from Datto (auto-matched, lower confidence)
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[2]->customer_id, // Gamma Tech Group
                'integration_customer_id' => $dattoIntegrationCustomers[2]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 82,
                'mapped_by_user_id' => $admin->user_id ?? null,
                'mapped_at' => now()->subDays(6),
                'notes' => 'Auto-matched but may need review (shortened name)',
            ]
        );

        // Map Delta from Autotask
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[3]->customer_id, // Delta Enterprises
                'integration_customer_id' => $autotaskIntegrationCustomers[2]->integration_customer_id,
            ],
            [
                'mapping_type' => 'manual',
                'confidence_score' => 100,
                'mapped_by_user_id' => $admin->user_id ?? null,
                'mapped_at' => now()->subDays(1),
                'notes' => 'New customer - manually mapped',
            ]
        );

        $this->command->info('✅ Customer test data seeded successfully!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('  - 2 Integrations (Datto, Autotask)');
        $this->command->info('  - 5 Unified Customers');
        $this->command->info('  - 7 Datto Integration Customers (3 mapped, 4 unmapped)');
        $this->command->info('  - 5 Autotask Integration Customers (3 mapped, 2 unmapped)');
        $this->command->info('  - 6 Customer Mappings');
        $this->command->info('');
        $this->command->info('🧪 Test Scenarios Available:');
        $this->command->info('  - GET /customers → View all unified customers');
        $this->command->info('  - GET /customers/unmapped → See 6 unmapped integration customers');
        $this->command->info('  - POST /customers/map → Map unmapped customers');
        $this->command->info('  - GET /customers/{id}/integrations → See customer across integrations');
        $this->command->info('  - POST /customers/{id}/merge → Merge duplicate customers');
        $this->command->info('  - POST /customers/bulk-map → Bulk map multiple customers');
    }
}
