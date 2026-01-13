<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\SyncLog;

class CheckAutotaskStatus extends Command
{
    protected $signature = 'check:autotask';
    protected $description = 'Check Autotask integration status and data';

    public function handle()
    {
        $this->info('=== Autotask Integration Status ===');
        $this->newLine();

        $integration = Integration::where('integration_type', 'Autotask')->first();

        if (!$integration) {
            $this->error('No Autotask integration found');
            return 1;
        }

        $this->info("Integration ID: {$integration->integration_id}");
        $this->info("Status: {$integration->status}");
        $this->info("Is Source of Truth: " . ($integration->is_source_of_truth ? 'Yes' : 'No'));
        $this->newLine();

        // Customer counts
        $totalCustomers = IntegrationCustomer::where('integration_id', $integration->integration_id)->count();
        $mappedCustomers = IntegrationCustomer::where('integration_id', $integration->integration_id)
            ->whereNotNull('customer_id')->count();
        $unmappedCustomers = $totalCustomers - $mappedCustomers;

        $this->info("=== Customers ===");
        $this->info("Total: {$totalCustomers}");
        $this->info("Mapped: {$mappedCustomers}");
        $this->info("Unmapped: {$unmappedCustomers}");
        $this->newLine();

        // Data counts by type
        $data = IntegrationData::where('integration_id', $integration->integration_id)
            ->selectRaw('data_type, COUNT(*) as count')
            ->groupBy('data_type')
            ->get();

        $this->info("=== Data Records ===");
        $total = 0;
        foreach ($data as $row) {
            $this->info("{$row->data_type}: {$row->count}");
            $total += $row->count;
        }
        $this->info("Total: {$total}");
        $this->newLine();

        // Latest data per type
        $this->info("=== Latest Records ===");
        $types = ['ticket', 'time_entry'];
        foreach ($types as $type) {
            $latest = IntegrationData::where('integration_id', $integration->integration_id)
                ->where('data_type', $type)
                ->orderByDesc('data_timestamp')
                ->first();

            if ($latest) {
                $customer = IntegrationCustomer::find($latest->integration_customer_id);
                $this->info("{$type}:");
                $this->info("  Time: {$latest->data_timestamp}");
                $this->info("  Customer: " . ($customer ? $customer->name : 'N/A'));
            }
        }
        $this->newLine();

        // Latest sync
        $latestSync = SyncLog::where('integration_id', $integration->integration_id)
            ->orderByDesc('sync_id')
            ->first();

        if ($latestSync) {
            $this->info("=== Latest Sync ===");
            $this->info("Time: {$latestSync->started_at}");
            $this->info("Status: {$latestSync->status}");
            $this->info("Records Synced: " . ($latestSync->records_synced ?? 0));
        }

        $this->newLine();
        $this->info('✓ Autotask check complete');
        return 0;
    }
}
