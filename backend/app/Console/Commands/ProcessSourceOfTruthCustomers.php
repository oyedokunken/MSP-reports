<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\Customer;
use App\Models\CustomerMapping;

class ProcessSourceOfTruthCustomers extends Command
{
    protected $signature = 'customers:process-source-of-truth {integration_id?}';
    protected $description = 'Process unmapped customers from source of truth integration and auto-create unified customers';

    public function handle(): int
    {
        $integrationId = $this->argument('integration_id');

        if ($integrationId) {
            $integrations = Integration::where('integration_id', $integrationId)
                ->where('is_source_of_truth', true)
                ->get();
        } else {
            $integrations = Integration::where('is_source_of_truth', true)->get();
        }

        if ($integrations->isEmpty()) {
            $this->error('No source of truth integrations found!');
            return 1;
        }

        foreach ($integrations as $integration) {
            $this->info("Processing source of truth integration: {$integration->display_name}");
            
            $unmappedCustomers = IntegrationCustomer::where('integration_id', $integration->integration_id)
                ->whereNull('customer_id')
                ->get();

            if ($unmappedCustomers->isEmpty()) {
                $this->info("  No unmapped customers found.");
                continue;
            }

            $this->info("  Found {$unmappedCustomers->count()} unmapped customers");

            $created = 0;
            $failed = 0;

            foreach ($unmappedCustomers as $integrationCustomer) {
                try {
                    $unifiedCustomer = Customer::create([
                        'company_id' => $integration->company_id,
                        'canonical_name' => $integrationCustomer->name,
                        'status' => 'active',
                        'metadata' => [],
                    ]);

                    $integrationCustomer->customer_id = $unifiedCustomer->customer_id;
                    $integrationCustomer->save();

                    CustomerMapping::create([
                        'customer_id' => $unifiedCustomer->customer_id,
                        'integration_customer_id' => $integrationCustomer->integration_customer_id,
                        'mapping_type' => 'auto',
                        'confidence_score' => 100,
                        'mapped_at' => now(),
                        'notes' => 'Auto-created from source of truth integration (batch processing)',
                    ]);

                    $this->info("  ✅ Created: {$integrationCustomer->name} (Customer ID: {$unifiedCustomer->customer_id})");
                    $created++;

                } catch (\Exception $e) {
                    $this->error("  ❌ Failed: {$integrationCustomer->name} - {$e->getMessage()}");
                    $failed++;
                }
            }

            $this->info("");
            $this->info("Summary for {$integration->display_name}:");
            $this->info("  Created: {$created}");
            $this->info("  Failed: {$failed}");
            $this->info("");
        }

        $this->info('✅ Processing complete!');
        return 0;
    }
}
