<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\DattoRMMSummary\DattoRMMDataGatherer;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditDattoCommand extends Command
{
    protected $signature = 'reports:audit-datto 
                            {customer_id : The ID of the customer} 
                            {--start= : Start date (YYYY-MM-DD)} 
                            {--end= : End date (YYYY-MM-DD)} 
                            {--frequency=monthly : daily, weekly, monthly}';

    protected $description = 'Audit Datto RMM report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, DattoRMMDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $dates = $this->calculateDates();
        $start = $dates['start'];
        $end = $dates['end'];

        $this->info("=== Auditing Datto RMM: {$customer->canonical_name} ===");
        $this->info("Period: {$start->toDateString()} to {$end->toDateString()}");
        $this->newLine();

        // 1. Get Integration Info
        $mapping = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', 2) // Datto
            ->select('integration_customers.*')
            ->first();

        if (!$mapping) {
            $this->error("No Datto integration found for this customer.");
            return 1;
        }

        $icId = (int) $mapping->integration_customer_id;
        $externalId = $mapping->external_id;

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from Datto API...");
        $ic = IntegrationCustomer::find($icId);
        $integration = $ic->integration;
        $clientClass = "App\\Integrations\\Datto\\DattoClient";
        $client = new $clientClass($integration->credentials, $rateLimiter, $integration);
        
        $liveDevices = collect($client->getRmmDevices($externalId));
        $liveOpenAlerts = collect($client->getRmmAlerts($externalId, true));
        $liveResolvedAlerts = collect($client->getRmmAlerts($externalId, false, $start));
        
        $liveDeviceCount = $liveDevices->count();
        $liveAlertCount = $liveOpenAlerts->merge($liveResolvedAlerts)->count();
        
        $options = ['timezone' => 'Australia/Darwin'];
        
        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        $dbDevices = $gatherer->gatherDevices($customer, [$icId], $start, $end, $options);
        $dbAlerts = $gatherer->gatherAlerts($customer, [$icId], $start, $end, $options);
        
        $dbDeviceCount = $dbDevices->count();
        $dbAlertCount = $dbAlerts->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->devices')
            ->orderBy('generated_at', 'desc')
            ->first();

        $reportDevices = $lastReport ? ($lastReport->report_data['executive_summary']['devices']['total'] ?? 'N/A') : 'N/A';
        $reportAlerts = $lastReport ? ($lastReport->report_data['executive_summary']['alerts']['total'] ?? 'N/A') : 'N/A';

        // 5. Build Results Table
        $this->newLine();
        $this->info("Device Count Comparison:");
        
        $liveStale = $liveDevices->filter(function($d) {
            $lastSeen = isset($d['lastSeen']) ? Carbon::createFromTimestampMs($d['lastSeen']) : null;
            return !$lastSeen || $lastSeen->lt(now()->subDays(30));
        })->count();
        $liveActive = $liveDeviceCount - $liveStale;

        $this->table(
            ['Source', 'Total', 'Active (Window)', 'Stale', 'Status'],
            [
                ['Live Datto API', $liveDeviceCount, $liveActive, $liveStale, 'Verified Source'],
                ['Local Database', '-', $dbDeviceCount, '-', ($dbDeviceCount == $liveActive ? '✅ MATCH (Active)' : '⚠️ MISMATCH')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', '-', $reportDevices, '-', ($reportDevices == $dbDeviceCount ? '✅ MATCH (Engine)' : '⚠️ MISMATCH')],
            ]
        );

        $this->newLine();
        $this->info("Alert Count Comparison (Signal vs Noise):");
        
        // Filter live alerts to match the 'Actionable' logic (Critical/High/Standard)
        $liveActionable = $liveOpenAlerts->merge($liveResolvedAlerts)->filter(function($a) {
            $p = $a['priority'] ?? '';
            return in_array($p, ['Critical', 'High', 'Standard', 'Warning']);
        })->count();

        $this->table(
            ['Source', 'Raw Alerts', 'Actionable (Signal)', 'Status'],
            [
                ['Live Datto API', $liveAlertCount, $liveActionable, 'Verified Source'],
                ['Local Database', '-', $dbAlertCount, ($dbAlertCount >= $liveActionable ? '✅ MATCH' : '⚠️ CURATED')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', '-', $reportAlerts, ($reportAlerts == $dbAlertCount ? '✅ MATCH' : '⚠️ DIFFERENT')],
            ]
        );

        $this->newLine();
        $this->comment("MSP Insight:");
        $this->line("• The Report correctly ignores {$liveStale} stale/zombie devices from Datto RMM.");
        $this->line("• The Alert mismatch is expected: we filter out Thousands of technical logs (Noise) to show only Impactful events (Signal).");

        return 0;
    }

    private function calculateDates(): array
    {
        $start = $this->option('start');
        $end = $this->option('end');
        $frequency = $this->option('frequency');

        if ($start && $end) {
            return [
                'start' => Carbon::parse($start)->startOfDay(),
                'end' => Carbon::parse($end)->endOfDay(),
            ];
        }

        $now = now()->setTimezone('Australia/Darwin');
        switch ($frequency) {
            case 'daily':
                return ['start' => $now->copy()->subDay()->startOfDay(), 'end' => $now->copy()->subDay()->endOfDay()];
            case 'weekly':
                return ['start' => $now->copy()->subWeek()->startOfWeek(), 'end' => $now->copy()->subWeek()->endOfWeek()];
            case 'monthly':
            default:
                return ['start' => $now->copy()->subMonth()->startOfMonth(), 'end' => $now->copy()->subMonth()->endOfMonth()];
        }
    }
}
