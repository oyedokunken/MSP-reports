<?php

namespace App\Console\Commands\Integrations;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VerifyLiveSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:verify-live 
                            {customer_id : The ID of the customer} 
                            {--frequency=monthly : Frequency to check (daily, weekly, monthly)}
                            {--type= : Specific data type to check (ticket, alert, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify local database records against the live integration API';

    /**
     * Execute the console command.
     */
    public function handle(RateLimiter $rateLimiter): int
    {
        $customerId = $this->argument('customer_id');
        $frequency = $this->option('frequency');
        $dataType = $this->option('type');

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("Live Verification for: {$customer->canonical_name}");
        
        $mappings = DB::table('customer_mappings')
            ->where('customer_id', $customerId)
            ->get();

        if ($mappings->isEmpty()) {
            $this->warn("No integrations mapped for this customer.");
            return 0;
        }

        $frequencyDays = [
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'yearly' => 365,
        ];
        $days = $frequencyDays[$frequency] ?? 30;
        $startDate = now()->subDays($days);

        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            if (!$ic) continue;

            $integration = $ic->integration;
            $this->newLine();
            $this->info("Checking Integration: {$integration->display_name} ({$integration->integration_type})");
            $this->comment("Source Range: {$startDate->toDateTimeString()} to Now ({$frequency})");

            try {
                $clientClass = "App\\Integrations\\{$integration->integration_type}\\{$integration->integration_type}Client";
                if (!class_exists($clientClass)) {
                    $this->error("Client class not found for {$integration->integration_type}");
                    continue;
                }

                $client = new $clientClass($integration->credentials, $rateLimiter, $integration);
                
                $remoteCount = 0;
                $dataTypeLabel = $dataType ?: 'Primary Data';

                // Fetch data based on integration type
                if ($integration->integration_type === 'Autotask') {
                    if (!$dataType || $dataType === 'ticket') {
                        $this->output->write("Fetching tickets from Autotask for Company #{$ic->external_id}... ");
                        $tickets = $client->getTickets($startDate, now(), (int) $ic->external_id);
                        
                        $remoteCount = count($tickets);
                        
                        $this->info($remoteCount . " found.");
                        $this->compare('ticket', $ic->integration_customer_id, $remoteCount, $startDate);
                    }
                } elseif ($integration->integration_type === 'Datto') {
                    if (!$dataType || $dataType === 'alert') {
                        $this->output->write("Fetching alerts from Datto RMM... ");
                        // In DattoClient, we need to handle open vs resolved
                        $openAlerts = $client->getRmmAlerts($ic->external_id, true);
                        $resolvedAlerts = $client->getRmmAlerts($ic->external_id, false, $startDate);
                        
                        // Filter open alerts by timestamp since the API might return all open ones
                        $recentOpen = collect($openAlerts)->filter(function($a) use ($startDate) {
                            $ts = isset($a['timestamp']) ? Carbon::parse($a['timestamp'] / 1000) : null;
                            return $ts && $ts->gte($startDate);
                        })->count();

                        $remoteCount = $recentOpen + count($resolvedAlerts);
                        $this->info($remoteCount . " found ({$recentOpen} open, " . count($resolvedAlerts) . " resolved).");
                        $this->compare('alert', $ic->integration_customer_id, $remoteCount, $startDate);
                    }
                } else {
                    $this->warn("Live verification not yet implemented for {$integration->integration_type}");
                }

            } catch (\Exception $e) {
                $this->error("Failed to connect: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function compare(string $type, int $icId, int $remoteCount, Carbon $startDate): void
    {
        $localCount = IntegrationData::where('integration_customer_id', $icId)
            ->where('data_type', $type)
            ->where('data_timestamp', '>=', $startDate)
            ->count();

        $status = $localCount === $remoteCount ? '<info>MATCH</info>' : '<error>MISMATCH</error>';
        
        $this->line("Local Database: {$localCount} records");
        $this->line("Status: {$status}");
        
        if ($localCount !== $remoteCount) {
            $this->comment("Difference: " . abs($remoteCount - $localCount) . " records. This might be due to pending syncs or deleted records at source.");
        }
    }
}
