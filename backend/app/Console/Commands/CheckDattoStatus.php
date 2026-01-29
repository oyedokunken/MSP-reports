<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\SyncLog;

class CheckDattoStatus extends Command
{
    protected $signature = 'check:datto';
    protected $description = 'Check Datto integration status and data';

    public function handle(): int
    {
        $this->info('=== Datto Integration Status ===');
        $this->newLine();

        $integration = Integration::where('integration_type', 'Datto')->first();

        if (!$integration) {
            $this->error('No Datto integration found');
            return 1;
        }

        $this->info("Integration ID: {$integration->integration_id}");
        $this->info("Status: {$integration->status}");
        $this->info("Is Source of Truth: " . ($integration->is_source_of_truth ? 'Yes' : 'No'));
        $this->newLine();

        // Customer counts (Sites)
        $totalCustomers = IntegrationCustomer::where('integration_id', $integration->integration_id)->count();
        $mappedCustomers = IntegrationCustomer::where('integration_id', $integration->integration_id)
            ->whereNotNull('customer_id')->count();
        $unmappedCustomers = $totalCustomers - $mappedCustomers;

        $this->info("=== Sites (Customers) ===");
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
        /** @var \App\Models\IntegrationData $row */
        foreach ($data as $row) {
            $this->info("{$row->data_type}: {$row->count}");
            $total += $row->count;
        }
        $this->info("Total: {$total}");
        $this->newLine();

        // Latest data per type
        $this->info("=== Latest Records ===");
        $types = ['alert', 'device_status', 'uptime_metric'];
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
                
                // Extra details based on type
                if ($type === 'alert' && isset($latest->data_payload['priority'])) {
                    $this->info("  Priority: {$latest->data_payload['priority']}");
                }
                if ($type === 'device_status' && isset($latest->data_payload['hostname'])) {
                    $this->info("  Hostname: {$latest->data_payload['hostname']}");
                }
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
        $this->info('✓ Datto check complete');
        return 0;
    }
}
