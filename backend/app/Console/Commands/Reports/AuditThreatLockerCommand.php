<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\ThreatLockerSummary\ThreatLockerDataGatherer;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditThreatLockerCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reports:audit-threatlocker 
                            {customer_id : The ID of the customer} 
                            {--start= : Start date (YYYY-MM-DD)} 
                            {--end= : End date (YYYY-MM-DD)} 
                            {--days=30 : Number of days (default 30)}
                            {--frequency=monthly : daily, weekly, monthly}';

    /**
     * @var string
     */
    protected $description = 'Audit ThreatLocker report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, ThreatLockerDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        // Determine timezone
        $tlSchedule = $customer->reportSchedules()->where('report_type', 'threatlocker_summary')->first();
        $timezone = $tlSchedule ? ($tlSchedule->timezone ?: config('app.timezone')) : config('app.timezone');

        // Window
        if ($this->option('start') && $this->option('end')) {
            $start = Carbon::parse($this->option('start'), $timezone)->startOfDay()->setTimezone('UTC');
            $end = Carbon::parse($this->option('end'), $timezone)->endOfDay()->setTimezone('UTC');
        } else {
            $days = (int) $this->option('days');
            $end = now()->setTimezone($timezone)->endOfDay()->setTimezone('UTC');
            $start = $end->copy()->subDays($days)->startOfDay();
        }

        $this->info("=== Auditing ThreatLocker: {$customer->canonical_name} ===");
        $this->info("Period: {$start->toDateTimeString()} to {$end->toDateTimeString()} (UTC)");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'ThreatLocker')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No ThreatLocker integration found for this customer.");
            return 1;
        }

        $integration = \App\Models\Integration::find($mappings->first()->integration_id);

        if (!$integration) {
            $this->error("Integration not found.");
            return 1;
        }

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from ThreatLocker API...");
        
        $client = new \App\Integrations\ThreatLocker\ThreatLockerClient(
            $integration->credentials,
            $rateLimiter,
            $integration
        );

        $liveComputersCount = 0;
        $livePoliciesCount = 0;
        $liveApplicationsCount = 0;
        $liveAuditCount = 0;

        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            if (!$ic) continue;
            
            $externalId = $ic->external_id;

            try {
                $computers = $client->getComputers($externalId);
                $liveComputersCount += count($computers);

                $policies = $client->getPolicies($externalId);
                $livePoliciesCount += count($policies);

                $apps = $client->getApplications($externalId);
                $liveApplicationsCount += count($apps);

                $audits = $client->getAuditLogs($externalId, $start, $end);
                $liveAuditCount += count($audits);

                $this->line("      - Org: {$ic->name} | Computers: " . count($computers) . " | Policies: " . count($policies) . " | Audits: " . count($audits));
            } catch (\Exception $e) {
                $this->warn("      ⚠️ API Fetch Failed for {$ic->name}: " . $e->getMessage());
            }
        }

        // 3. Fetch Database Data
        $this->comment("2/3 Querying Local Database Cache...");
        $icIds = $mappings->pluck('integration_customer_id')->toArray();
        $dbComputers = $gatherer->gatherComputers($customer, $icIds);
        $dbPolicies = $gatherer->gatherPolicies($customer, $icIds);
        $dbApplications = $gatherer->gatherApplications($customer, $icIds);
        $dbAuditLogs = $gatherer->gatherAuditLogs($customer, $icIds, $start, $end);

        $dbComputersCount = $dbComputers->count();
        $dbPoliciesCount = $dbPolicies->count();
        $dbApplicationsCount = $dbApplications->count();
        $dbAuditCount = $dbAuditLogs->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $frequency = $this->option('frequency') ?: 'monthly';
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->total_endpoints')
            ->orderBy('generated_report_id', 'desc')
            ->first();

        $reportComputers = $lastReport ? ($lastReport->report_data['executive_summary']['total_endpoints'] ?? 'N/A') : 'N/A';
        $reportPolicies = $lastReport ? ($lastReport->report_data['executive_summary']['total_policies'] ?? 'N/A') : 'N/A';
        $reportAudit = $lastReport ? ($lastReport->report_data['executive_summary']['total_audit_events'] ?? 'N/A') : 'N/A';

        // 5. Build Results Table
        $this->newLine();
        $this->info("ThreatLocker Data Comparison:");

        $this->table(
            ['Metric', 'Live API', 'Local DB', 'Last Report', 'Status'],
            [
                [
                    'Endpoints', 
                    $liveComputersCount, 
                    $dbComputersCount, 
                    $reportComputers, 
                    ($dbComputersCount == $liveComputersCount ? '✅' : '⚠️') . ' / ' . ($reportComputers == $dbComputersCount ? '✅' : '⚠️')
                ],
                [
                    'Policies', 
                    $livePoliciesCount, 
                    $dbPoliciesCount, 
                    $reportPolicies, 
                    ($dbPoliciesCount == $livePoliciesCount ? '✅' : '⚠️') . ' / ' . ($reportPolicies == $dbPoliciesCount ? '✅' : '⚠️')
                ],
                [
                    'Audit Events', 
                    $liveAuditCount, 
                    $dbAuditCount, 
                    $reportAudit, 
                    ($dbAuditCount == $liveAuditCount ? '✅' : '⚠️') . ' / ' . ($reportAudit == $dbAuditCount ? '✅' : '⚠️')
                ],
            ]
        );

        $this->newLine();
        if ($dbComputersCount == $liveComputersCount && $dbPoliciesCount == $livePoliciesCount) {
            $this->info("✅ Core security data matches perfectly between API and DB.");
        } else {
            $this->warn("⚠️ Mismatch detected. Consider running a fresh sync.");
        }

        return 0;
    }
}
