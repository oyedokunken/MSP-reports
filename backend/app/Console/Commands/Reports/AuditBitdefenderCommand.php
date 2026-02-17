<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\BitDefenderSummary\BitDefenderDataGatherer;
use App\Services\RateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditBitdefenderCommand extends Command
{
    protected $signature = 'reports:audit-bitdefender
                            {customer_id : The ID of the customer}
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--frequency=monthly : daily, weekly, monthly}';

    protected $description = 'Audit BitDefender report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, BitDefenderDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing BitDefender: {$customer->canonical_name} ===");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'BitDefender')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No BitDefender integration found for this customer.");
            return 1;
        }

        $icIds = $mappings->pluck('integration_customer_id')->toArray();

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from BitDefender API...");
        $totalLiveEndpointCount = 0;
        $totalLiveManagedCount = 0;
        $totalLiveThreatCount = 0;
        
        // Determine timezone
        $bdSchedule = $customer->reportSchedules()->where('report_type', 'bitdefender_summary')->first();
        $timezone = $bdSchedule ? ($bdSchedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $this->line("    Using Timezone: {$timezone}");

        // Determine audit window
        if ($this->option('start') && $this->option('end')) {
            $startDateObj = Carbon::parse($this->option('start'), $timezone)->startOfDay();
            $endDateObj = Carbon::parse($this->option('end'), $timezone)->endOfDay();
        } else {
            $endDateObj = now()->setTimezone($timezone)->endOfDay();
            $startDateObj = $endDateObj->copy()->subDays(30)->startOfDay();
        }
        $this->info("    Audit Window: {$startDateObj->toDateTimeString()} to {$endDateObj->toDateTimeString()}");

        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            $integration = $ic->integration;
            $client = new \App\Integrations\BitDefender\BitDefenderClient(
                $integration->credentials,
                $rateLimiter,
                $integration
            );

            // Live Endpoints
            try {
                $liveEndpoints = collect($client->getEndpointsForCompany($mapping->external_id));
                $totalLiveEndpointCount += $liveEndpoints->count();
                $totalLiveManagedCount += $liveEndpoints->filter(fn($e) => ($e['isManaged'] ?? false) === true)->count();
            } catch (\Exception $e) {
                $this->warn("      - [{$mapping->external_id}] ⚠️ Endpoint API Failed: " . $e->getMessage());
            }

            // Live Threats (Filtered by Window)
            try {
                $allThreats = collect($client->getThreatDetections($startDateObj));
                $liveThreats = $allThreats->filter(fn($t) => 
                    ($t['companyId'] ?? '') == $mapping->external_id &&
                    Carbon::parse($t['detection_time'])->between($startDateObj, $endDateObj)
                );
                $totalLiveThreatCount += $liveThreats->count();
            } catch (\Exception $e) {
                $this->warn("      - [{$mapping->external_id}] ⚠️ Threat API Failed: " . $e->getMessage());
            }
        }

        $liveEndpointCount = $totalLiveEndpointCount;
        $liveManagedCount = $totalLiveManagedCount;
        $liveThreatCount = $totalLiveThreatCount;

        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        
        // Gatherer returns latest snapshot for endpoints as of end of target period
        $dbEndpoints = $gatherer->gatherEndpoints($customer, $icIds, $endDateObj, 5);
        
        // Strict Filter: Only count devices that actually recorded a snapshot WITHIN the audit period 
        // (Matches the 'inRange' logic used in BitDefenderSummary.php)
        $dbEndpoints = $dbEndpoints->filter(function($row) use ($startDateObj, $endDateObj) {
            $ts = Carbon::parse($row['timestamp']);
            return $ts->between($startDateObj, $endDateObj);
        });

        $dbEndpointCount = $dbEndpoints->count();
        $dbManagedCount = $dbEndpoints->filter(fn($e) => ($e['payload']['is_managed'] ?? false) === true)->count();
        
        // Gatherer returns unique threats
        $dbThreats = $gatherer->gatherThreatDetections($customer, $icIds)->filter(function($t) use ($startDateObj, $endDateObj) {
             $ts = Carbon::parse($t['timestamp']);
             return $ts->between($startDateObj, $endDateObj);
        });
        $dbThreatCount = $dbThreats->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $frequency = $this->option('frequency') ?: 'monthly';
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->total_endpoints')
            ->where('report_data->options->frequency', $frequency)
            ->when($bdSchedule, fn($q) => $q->where('report_schedule_id', $bdSchedule->report_schedule_id))
            ->orderBy('generated_report_id', 'desc')
            ->first();

        $reportEndpoints = $lastReport ? ($lastReport->report_data['executive_summary']['total_endpoints'] ?? 'N/A') : 'N/A';
        $reportThreats = $lastReport ? ($lastReport->report_data['executive_summary']['total_threats'] ?? 'N/A') : 'N/A';
        $reportManaged = $lastReport ? ($lastReport->report_data['executive_summary']['managed_endpoints'] ?? 'N/A') : 'N/A';
        $reportStrategies = $lastReport ? ($lastReport->report_data['policy_summary']['compliance_rate'] ?? 'N/A') : 'N/A';

        // 5. Build Results Tables
        $this->newLine();
        $this->info("Endpoint Comparison:");
        $this->table(
            ['Source', 'Total Endpoints', 'Managed', 'Status'],
            [
                ['Live API', $liveEndpointCount, $liveManagedCount, 'Verified Source'],
                ['Local Database', $dbEndpointCount, $dbManagedCount, ($dbEndpointCount == $liveEndpointCount ? '✅ MATCH' : '❌ MISMATCH')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportEndpoints, $reportManaged, ($reportEndpoints == $dbEndpointCount ? '✅ MATCH (DB)' : '⚠️ DIFFERENT')],
            ]
        );

        $this->newLine();
        $this->info("Threat Detection Comparison (Last 30 Days API vs Total DB):");
        $threatComparison = '⚠️ UNKNOWN';
        if (is_numeric($liveThreatCount)) {
            $threatComparison = ($dbThreatCount >= $liveThreatCount ? '✅ MATCH/RETAINED' : '⚠️ LIVE HAS MORE (' . ($liveThreatCount - $dbThreatCount) . ')');
        } elseif ($liveThreatCount === 'N/A') {
            $threatComparison = '⚠️ API UNAVAILABLE';
        }

        $this->table(
            ['Source', 'Threats', 'Status'],
            [
                ['Live API (30d)', $liveThreatCount, 'Windowed Source'],
                ['Local Database', $dbThreatCount, $threatComparison],
                ['Last Report', $reportThreats, 'Report Window'],
            ]
        );

        $this->newLine();
        
        if ($dbEndpointCount == $liveEndpointCount) {
             $this->info("✅ Endpoint counts match perfectly.");
        } else {
             $this->warn("⚠️ Endpoint Count Mismatch: API {$liveEndpointCount} vs DB {$dbEndpointCount}");
             $this->line("   Run 'php artisan integrations:sync {$integration->integration_id}' to update.");
        }

        return 0;
    }
}
