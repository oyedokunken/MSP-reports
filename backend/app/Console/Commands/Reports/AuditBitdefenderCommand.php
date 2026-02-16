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
                            {customer_id : The ID of the customer}';

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
        $mapping = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'BitDefender')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->first();

        if (!$mapping) {
            $this->error("No BitDefender integration found for this customer.");
            return 1;
        }

        $icId = (int) $mapping->integration_customer_id;
        $externalId = $mapping->external_id;

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from BitDefender API...");
        $ic = IntegrationCustomer::find($icId);
        $integration = $ic->integration;
        $client = new \App\Integrations\BitDefender\BitDefenderClient(
            $integration->credentials,
            $rateLimiter,
            $integration
        );

        // Live Endpoints
        $liveEndpoints = collect($client->getEndpointsForCompany($externalId));
        $liveEndpointCount = $liveEndpoints->count();
        $liveManagedCount = $liveEndpoints->filter(fn($e) => ($e['isManaged'] ?? false) === true)->count();
        
        // Live Threats (Quarantine)
        // Note: API fetches ALL threats visible to the key. We must filter by company ID.
        // We limit lookup to last 30 days to avoid fetching excessive history if global list is huge,
        // matching the report's typical monthly scope.
        $this->comment("    Fetching threats (last 30 days)...");
        $liveThreatCount = 0;
        try {
            $startDate = now()->subDays(31);
            $allThreats = collect($client->getThreatDetections($startDate));
            
            // Filter by company ID (Bitdefender returns companyId as string)
            $liveThreats = $allThreats->filter(fn($t) => ($t['companyId'] ?? '') == $externalId);
            $liveThreatCount = $liveThreats->count();
        } catch (\Exception $e) {
            $this->warn("    ⚠️ Threat/Quarantine API Access Denied or Failed: " . $e->getMessage());
            $liveThreatCount = 'N/A';
        }

        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        
        // Gatherer returns latest snapshot for endpoints
        $dbEndpoints = $gatherer->gatherEndpoints($customer, [$icId]);
        $dbEndpointCount = $dbEndpoints->count();
        $dbManagedCount = $dbEndpoints->filter(fn($e) => ($e['payload']['is_managed'] ?? false) === true)->count();
        
        // Gatherer returns unique threats
        $dbThreats = $gatherer->gatherThreatDetections($customer, [$icId]);
        // Filter threats to match the 30-day window of the live fetch for fairer comparison,
        // OR compare against total if we want to check retention. 
        // Report logic filters by report range. Let's align with the report logic later.
        // For DB check, we usually want to see what matches the Live API for "now".
        // But threats are historical. Let's just count total in DB for now and note the difference.
        $dbThreatCount = $dbThreats->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $bdSchedule = $customer->reportSchedules()->where('report_type', 'bitdefender_summary')->first();
        
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->total_endpoints')
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
