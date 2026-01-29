<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Integrations\BitDefender\BitDefenderClient;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use Illuminate\Support\Facades\Log;

class BitdefenderVerifyData extends Command
{
    protected $signature = 'bitdefender:verify-data {integrationId?} {--sample=5}';
    protected $description = 'Compare DB data vs live Bitdefender API for accuracy';

    public function handle(): int
    {
        $integrationId = $this->argument('integrationId')
            ?? $this->ask('Enter the integration ID to verify');

        $sampleSize = (int) $this->option('sample');

        $integration = Integration::findOrFail($integrationId);

        $rateLimiter = app(RateLimiter::class);
        $client = new BitDefenderClient(
            $integration->credentials ?? [],
            $rateLimiter,
            $integration
        );

        $this->info("Starting verification for integration #{$integration->integration_id}");
        $this->info("Name: " . ($integration->display_name ?? 'Unnamed'));
        $this->newLine();

        // Companies verification (unchanged)
        $dbCustomers = IntegrationCustomer::where('integration_id', $integrationId)
            ->whereNotNull('external_id')
            ->get()
            ->keyBy('external_id');

        try {
            $apiCompanies = collect($client->getCompaniesList());
        } catch (\Exception $e) {
            $this->error("Failed to fetch companies from API: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Companies in database: {$dbCustomers->count()}");
        $this->info("Companies from API:     {$apiCompanies->count()}");

        $mismatches = [];

        foreach ($apiCompanies as $apiCompany) {
            $extId = $apiCompany['id'] ?? null;
            if (!$extId) continue;

            $db = $dbCustomers->get($extId);

            if (!$db) {
                $mismatches[] = "Company missing in DB: {$apiCompany['name']} ({$extId})";
                continue;
            }

            if (trim($db->name ?? '') !== trim($apiCompany['name'] ?? '')) {
                $mismatches[] = "Name mismatch for company {$extId}: DB='{$db->name}' vs API='{$apiCompany['name']}'";
            }
        }

        // Endpoints sample check – SAFE VERSION
        if ($dbCustomers->isEmpty()) {
            $this->warn("No customers found in database → skipping endpoint check.");
        } else {
            $sampleCustomers = $dbCustomers->random(min($sampleSize, $dbCustomers->count()));

            $this->info("\nChecking endpoints for {$sampleCustomers->count()} random companies...");

            foreach ($sampleCustomers as $dbCustomer) {
                $this->comment("→ Company: {$dbCustomer->name} ({$dbCustomer->external_id})");

                try {
                    $apiEndpoints = $client->getEndpointsForCompany($dbCustomer->external_id);
                } catch (\Exception $e) {
                    $this->error("  API error: " . $e->getMessage());
                    continue;
                }

                $apiIds = collect($apiEndpoints)->pluck('id')->filter()->unique()->values()->toArray();

                // Safe DB ID extraction
                $dbRecords = IntegrationData::where('integration_id', $integrationId)
                    ->where('integration_customer_id', $dbCustomer->integration_customer_id)
                    ->where('data_type', 'bitdefender_endpoint')
                    ->get(['data_payload']);

                $dbEndpointIds = $dbRecords->map(fn($r) => 
                    $r->data_payload['endpoint_id'] 
                    ?? $r->data_payload['id'] 
                    ?? ($r->data_payload['raw_data']['id'] ?? null)
                )->filter()->unique()->values()->toArray();

                $missingInDb = array_diff($apiIds, $dbEndpointIds);
                $extraInDb   = array_diff($dbEndpointIds, $apiIds);

                if (!empty($missingInDb)) {
                    $mismatches[] = "Company {$dbCustomer->name}: Missing in DB → " . implode(', ', $missingInDb);
                }
                if (!empty($extraInDb)) {
                    $mismatches[] = "Company {$dbCustomer->name}: Extra in DB → " . implode(', ', $extraInDb);
                }

                $this->line("  API endpoints:   " . count($apiIds));
                $this->line("  DB endpoints:    " . count($dbEndpointIds));
                $this->newLine();
            }
        }

        $this->newLine();

        if (empty($mismatches)) {
            $this->info('✓ No mismatches detected in the checked sample!');
        } else {
            $this->error('Issues found:');
            foreach ($mismatches as $line) {
                $this->line("  " . $line);
            }
            $this->newLine();
            $this->warn('Consider running a full re-sync if differences are significant.');
        }

        return self::SUCCESS;
    }
}