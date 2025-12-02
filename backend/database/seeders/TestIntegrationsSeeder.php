<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Integration;
use Illuminate\Support\Facades\DB;

class TestIntegrationsSeeder extends Seeder
{
    /**
     * Seed test integrations for development
     */
    public function run(): void
    {
        $companyId = 1; // Assuming company ID 1 exists

        $integrations = [
            [
                'company_id' => $companyId,
                'integration_type' => 'Datto',
                'display_name' => 'Datto RMM & Backup',
                'status' => 'disconnected', // Set to disconnected until real credentials added
                'credentials' => [
                    'api_key' => 'YOUR_DATTO_API_KEY',
                    'api_secret' => 'YOUR_DATTO_API_SECRET',
                    'base_url' => 'https://api.datto.com',
                ],
                'configuration' => [
                    'sync_enabled' => false,
                ],
                'is_source_of_truth' => false,
            ],
            [
                'company_id' => $companyId,
                'integration_type' => 'Autotask',
                'display_name' => 'Autotask PSA',
                'status' => 'disconnected',
                'credentials' => [
                    'username' => 'YOUR_AUTOTASK_USERNAME',
                    'api_key' => 'YOUR_AUTOTASK_API_KEY',
                    'integration_code' => 'YOUR_INTEGRATION_CODE',
                    'base_url' => 'https://webservices.autotask.net',
                ],
                'configuration' => [
                    'sync_enabled' => false,
                ],
                'is_source_of_truth' => false,
            ],
            [
                'company_id' => $companyId,
                'integration_type' => 'Sendmarc',
                'display_name' => 'Sendmarc Email Security',
                'status' => 'disconnected',
                'credentials' => [
                    'api_token' => 'YOUR_SENDMARC_API_TOKEN',
                    'base_url' => 'https://portal.sendmarc.com/api/v1',
                ],
                'configuration' => [
                    'sync_enabled' => false,
                ],
                'is_source_of_truth' => false,
            ],
            [
                'company_id' => $companyId,
                'integration_type' => 'M365',
                'display_name' => 'Microsoft 365',
                'status' => 'disconnected',
                'credentials' => [
                    'tenant_id' => 'YOUR_TENANT_ID',
                    'client_id' => 'YOUR_CLIENT_ID',
                    'client_secret' => 'YOUR_CLIENT_SECRET',
                ],
                'configuration' => [
                    'sync_enabled' => false,
                ],
                'is_source_of_truth' => false,
            ],
            [
                'company_id' => $companyId,
                'integration_type' => 'Kaseya',
                'display_name' => 'Kaseya VSA',
                'status' => 'disconnected',
                'credentials' => [
                    'api_key' => 'YOUR_KASEYA_API_KEY',
                    'base_url' => 'https://your-instance.kaseya.net',
                ],
                'configuration' => [
                    'sync_enabled' => false,
                ],
                'is_source_of_truth' => false,
            ],
        ];

        foreach ($integrations as $integration) {
            // Check if integration already exists
            $exists = Integration::where('company_id', $integration['company_id'])
                ->where('integration_type', $integration['integration_type'])
                ->exists();

            if (!$exists) {
                Integration::create($integration);
                $this->command->info("✅ Created {$integration['integration_type']} integration");
            } else {
                $this->command->warn("⚠️  {$integration['integration_type']} integration already exists");
            }
        }

        $this->command->info("\n🎉 Test integrations seeded successfully!");
        $this->command->info("📝 Update credentials via UI or database to activate them.");
    }
}
