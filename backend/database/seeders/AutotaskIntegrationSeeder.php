<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Integration;

class AutotaskIntegrationSeeder extends Seeder
{
    /**
     * Seed Autotask integration for Territory Technology Solutions
     * 
     * Run this to add Autotask integration:
     * php artisan db:seed --class=AutotaskIntegrationSeeder
     */
    public function run(): void
    {
        // Find TTS company
        $company = Company::where('name', 'Territory Technology Solutions')->first();
        
        if (!$company) {
            $this->command->error('Company "Territory Technology Solutions" not found. Run FirstCustomerSeeder first.');
            return;
        }

        // Create or update Autotask integration
        $integration = Integration::updateOrCreate(
            [
                'company_id' => $company->company_id,
                'integration_type' => 'Autotask',
            ],
            [
                'display_name' => 'Autotask PSA',
                'status' => 'active',
                'credentials' => [
                    'username' => '***REMOVED_AUTOTASK_USERNAME***',
                    'secret' => '***REMOVED_AUTOTASK_SECRET***',
                    'api_integration_code' => '***REMOVED_AUTOTASK_INTEGRATION_CODE***',
                    'endpoint' => 'https://webservices26.autotask.net/atservicesrest/v1.0',
                ],
                'configuration' => [
                    'sync_interval' => 3600,
                    'sync_tickets' => true,
                    'sync_time_entries' => true,
                    'sync_contracts' => false,
                ],
                'is_source_of_truth' => true,
                'historical_import_status' => 'pending',
                'historical_import_progress' => 0,
            ]
        );

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Autotask Integration Created!');
        $this->command->info('========================================');
        $this->command->info("Integration ID: {$integration->integration_id}");
        $this->command->info("Company: {$company->name}");
        $this->command->info("Status: {$integration->status}");
        $this->command->info("Endpoint: https://webservices26.autotask.net/atservicesrest/v1.0");
        $this->command->info('');
        $this->command->info('Next steps:');
        $this->command->info('1. Test connection: php artisan integration:test ' . $integration->integration_id);
        $this->command->info('2. Run sync: php artisan integration:sync ' . $integration->integration_id);
        $this->command->info('========================================');
    }
}
