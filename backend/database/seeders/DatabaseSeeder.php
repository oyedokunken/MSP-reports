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
    /**
     * Seed the application's database.
     * 
     * This will call individual seeders for production setup.
     * For first customer deployment, run: php artisan db:seed --class=FirstCustomerSeeder
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            LicenseSeeder::class,
        ]);
    }

    /**
     * Original test data seeder (kept for development/testing).
     */
    public function runTestData(): void
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
                    "authentication" => [
                        "auth_methods" => ["local", "m365", "saml", "ldap"],
                        "saml_config" => new \stdClass(),
                        "m365_config" => new \stdClass(),
                        "ldap_config" => new \stdClass(),
                        "password_policy" => new \stdClass(),
                        "require_2fa" => false,
                        "session_timeout_minutes" => 60
                    ],
                    "integrations" => [
                        "source_of_truth_integration_id" => null,
                        "auto_sync_enabled" => true,
                        "sync_schedule" => "0 */4 * * *",
                        "sync_failure_notifications" => ["admin@company.com"]
                    ],
                    "customer_mapping" => [
                        "auto_match_enabled" => true,
                        "auto_match_threshold" => 85,
                        "manual_match_required" => false
                    ],
                    "reports" => [
                        "default_report_destination" => "admin@company.com",
                        "report_timezone" => "Australia/Brisbane",
                        "report_retention_days" => 1095,
                        "report_format" => [
                            "default_format" => "pdf",
                            "pdf_page_size" => "A4",
                            "include_raw_data" => false,
                            "compress_attachments" => true
                        ]
                    ],
                    "white_label" => [
                        "enabled" => true,
                        "company_name" => "Customer MSP Name",
                        "logo_url" => "https://storage/logo.png",
                        "primary_color" => "#1a56db",
                        "secondary_color" => "#7c3aed",
                        "favicon_url" => "https://storage/favicon.ico",
                        "email_from_name" => "Customer MSP",
                        "email_from_address" => "reports@customermsp.com.au",
                        "support_email" => "support@customermsp.com.au",
                        "support_phone" => "1300 XXX XXX",
                        "footer_text" => "© 2025 Customer MSP",
                        "report_header_text" => "Monthly Service Report"
                    ],
                    "data_retention" => [
                        "default_months" => 36,
                        "audit_logs_months" => 84,
                        "generated_reports_months" => 36,
                        "integration_sync_logs_months" => 12,
                        "historical_data" => [
                            "raw_events_days" => 90,
                            "aggregated_daily_days" => 365,
                            "aggregated_monthly_days" => 1095
                        ]
                    ],
                    "security" => [
                        "allowed_ip_ranges" => [],
                        "encryption_key_id" => "company_123_key"
                    ],
                    "licensing" => [
                        "deployment_type" => "on_premise",
                        "license_enforcement" => false
                    ],
                    "historical_data" => [
                        "import_on_activation" => true,
                        "default_import_range_days" => 90,
                        "aggregation_enabled" => true
                    ]
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

        $this->command->info('✅ Database seeded with mapped and unmapped customers and full company settings.');
    }
}
