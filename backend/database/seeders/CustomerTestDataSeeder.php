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
     * - 2 integrations (Autotask primary, Datto secondary)
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
        // Autotask (Primary First Integration - Source of Truth)
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
                    'sync_interval' => 3600,
                    'enable_auto_sync' => true,
                ],
                'is_source_of_truth' => true,  // Autotask as primary source
                'last_sync_at' => now()->subHours(1),
                'last_sync_status' => 'success',
            ]
        );

        // Datto (Secondary Integration)
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
                    'sync_interval' => 7200,
                ],
                'is_source_of_truth' => false,
                'last_sync_at' => now()->subHours(2),
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
                'canonical_name' => 'TechStart Innovations',
                'status' => 'active',
                'metadata' => [
                    'industry' => 'Software',
                    'employees' => 25,
                    'location' => 'Brisbane, QLD',
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
            // Should auto-map (high similarity with abbreviations)
            ['external_id' => 'DATTO-001', 'name' => 'ACME Corp', 'active' => true],
            ['external_id' => 'DATTO-002', 'name' => 'Beta Solutions Ltd', 'active' => true],
            ['external_id' => 'DATTO-003', 'name' => 'TechStart Inc', 'active' => true],
            
            // Unmapped customers (need manual mapping - low similarity)
            ['external_id' => 'DATTO-004', 'name' => 'Zeta Systems', 'active' => true],
            ['external_id' => 'DATTO-005', 'name' => 'Omega Consulting Group', 'active' => true],
            ['external_id' => 'DATTO-006', 'name' => 'Phoenix Digital Services', 'active' => true],
            
            // Inactive customer
            ['external_id' => 'DATTO-007', 'name' => 'Old Legacy Corp (INACTIVE)', 'active' => false],
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
            // Should auto-map (exact or very close matches)
            ['external_id' => 'AT-1001', 'name' => 'ACME Corporation', 'active' => true],
            ['external_id' => 'AT-1002', 'name' => 'Beta Solutions Pty Ltd', 'active' => true],
            ['external_id' => 'AT-1003', 'name' => 'Gamma Tech Group', 'active' => true],
            ['external_id' => 'AT-1004', 'name' => 'TechStart Innovations Pty Ltd', 'active' => true],
            
            // Unmapped (completely different names)
            ['external_id' => 'AT-1005', 'name' => 'Sigma Networks Australia', 'active' => true],
            ['external_id' => 'AT-1006', 'name' => 'Quantum IT Solutions', 'active' => true],
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

        // === Create Customer Mappings (Pre-mapped for testing) ===
        // Note: In production, these would be created by AutoMatchCustomersJob
        
        // ACME Corporation - mapped from both integrations
        $dattoIntegrationCustomers[0]->customer_id = $createdCustomers[0]->customer_id;
        $dattoIntegrationCustomers[0]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[0]->customer_id,
                'integration_customer_id' => $dattoIntegrationCustomers[0]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 86,
                'mapped_at' => now()->subDays(5),
                'notes' => 'Auto-matched: ACME Corp → ACME Corporation (86% confidence)',
            ]
        );

        $autotaskIntegrationCustomers[0]->customer_id = $createdCustomers[0]->customer_id;
        $autotaskIntegrationCustomers[0]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[0]->customer_id,
                'integration_customer_id' => $autotaskIntegrationCustomers[0]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 100,
                'mapped_at' => now()->subDays(3),
                'notes' => 'Auto-matched: Exact match (100% confidence)',
            ]
        );

        // Beta Solutions - mapped from both integrations
        $dattoIntegrationCustomers[1]->customer_id = $createdCustomers[1]->customer_id;
        $dattoIntegrationCustomers[1]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[1]->customer_id,
                'integration_customer_id' => $dattoIntegrationCustomers[1]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 95,
                'mapped_at' => now()->subDays(4),
                'notes' => 'Auto-matched: Beta Solutions Ltd → Beta Solutions Pty Ltd (95% confidence)',
            ]
        );

        $autotaskIntegrationCustomers[1]->customer_id = $createdCustomers[1]->customer_id;
        $autotaskIntegrationCustomers[1]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[1]->customer_id,
                'integration_customer_id' => $autotaskIntegrationCustomers[1]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 100,
                'mapped_at' => now()->subDays(2),
                'notes' => 'Auto-matched: Exact match (100% confidence)',
            ]
        );

        // TechStart - mapped from both integrations
        $dattoIntegrationCustomers[2]->customer_id = $createdCustomers[2]->customer_id;
        $dattoIntegrationCustomers[2]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[2]->customer_id,
                'integration_customer_id' => $dattoIntegrationCustomers[2]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 88,
                'mapped_at' => now()->subDays(3),
                'notes' => 'Auto-matched: TechStart Inc → TechStart Innovations (88% confidence)',
            ]
        );

        $autotaskIntegrationCustomers[3]->customer_id = $createdCustomers[2]->customer_id;
        $autotaskIntegrationCustomers[3]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[2]->customer_id,
                'integration_customer_id' => $autotaskIntegrationCustomers[3]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 95,
                'mapped_at' => now()->subDays(1),
                'notes' => 'Auto-matched: TechStart Innovations Pty Ltd → TechStart Innovations (95% confidence)',
            ]
        );

        // Gamma Tech - mapped from Autotask only
        $autotaskIntegrationCustomers[2]->customer_id = $createdCustomers[3]->customer_id;
        $autotaskIntegrationCustomers[2]->save();
        CustomerMapping::firstOrCreate(
            [
                'customer_id' => $createdCustomers[3]->customer_id,
                'integration_customer_id' => $autotaskIntegrationCustomers[2]->integration_customer_id,
            ],
            [
                'mapping_type' => 'auto',
                'confidence_score' => 100,
                'mapped_at' => now()->subDays(2),
                'notes' => 'Auto-matched: Exact match (100% confidence)',
            ]
        );

        $this->command->info('✅ Customer test data seeded successfully!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('  - 2 Integrations (Autotask primary, Datto secondary)');
        $this->command->info('  - 6 Unified Customers');
        $this->command->info('  - 6 Autotask Integration Customers (4 auto-mapped, 2 unmapped)');
        $this->command->info('  - 7 Datto Integration Customers (3 auto-mapped, 4 unmapped)');
        $this->command->info('  - 7 Customer Mappings (all auto-mapped)');
        $this->command->info('');
        $this->command->info('🎯 Auto-Mapping Test Scenarios:');
        $this->command->info('  ✅ ACME Corp → ACME Corporation (86% - abbreviation match)');
        $this->command->info('  ✅ Beta Solutions Ltd → Beta Solutions Pty Ltd (95% - suffix match)');
        $this->command->info('  ✅ TechStart Inc → TechStart Innovations (88% - abbreviation match)');
        $this->command->info('  ❌ Zeta Systems, Omega Consulting, Phoenix Digital (unmapped - low similarity)');
        $this->command->info('  ❌ Sigma Networks, Quantum IT (unmapped - completely different)');
        $this->command->info('');
        $this->command->info('🧪 API Test Endpoints:');
        $this->command->info('  - GET /api/customers → View all unified customers');
        $this->command->info('  - GET /api/customers/unmapped → See 6 unmapped integration customers');
        $this->command->info('  - POST /api/customers/map → Map unmapped customers manually');
        $this->command->info('  - GET /api/customers/{id}/integrations → See customer across integrations');
        $this->command->info('  - POST /api/customers/{id}/merge → Merge duplicate customers');
        $this->command->info('  - POST /api/customers/bulk-map → Bulk map multiple customers');
    }
}
