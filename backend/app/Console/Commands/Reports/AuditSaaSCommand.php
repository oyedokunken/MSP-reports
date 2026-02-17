<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\SaaSSummary\SaaSDataGatherer;
use App\Services\RateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditSaaSCommand extends Command
{
    protected $signature = 'reports:audit-saas {customer_id} {--days=30} {--start=} {--end=} {--frequency=monthly}';

    protected $description = 'Audit SaaS Alerts report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, SaaSDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing SaaS Alerts: {$customer->canonical_name} ===");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'SaaS')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No SaaS Alerts integration found for this customer.");
            return 1;
        }

        $icIds = $mappings->pluck('integration_customer_id')->toArray();
        $this->info("    Mappings Found: " . $mappings->count());

        // Determine timezone accurately
        $schedule = $customer->reportSchedules()->where('report_type', 'saas_summary')->first();
        $timezone = $schedule ? ($schedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $this->line("    Using Timezone: {$timezone}");

        // Window/Dates
        $frequency = $this->option('frequency') ?: ($schedule?->frequency ?: 'monthly');
        
        if ($this->option('start') && $this->option('end')) {
            $startDate = Carbon::parse($this->option('start'), $timezone)->startOfDay();
            $endDate = Carbon::parse($this->option('end'), $timezone)->endOfDay();
        } else {
             $now = now()->setTimezone($timezone);
             switch ($frequency) {
                 case 'daily':
                     $startDate = $now->copy()->subDay()->startOfDay();
                     $endDate = $now->copy()->subDay()->endOfDay();
                     break;
                 case 'weekly':
                     $startDate = $now->copy()->subWeek()->startOfWeek();
                     $endDate = $now->copy()->subWeek()->endOfWeek();
                     break;
                 case 'monthly':
                 default:
                     $startDate = $now->copy()->subMonth()->startOfMonth();
                     $endDate = $now->copy()->subMonth()->endOfMonth();
                     break;
             }
        }

        $this->info("    Audit Window ({$timezone}): {$startDate->toDateTimeString()} to {$endDate->toDateTimeString()}");
        $this->info("    Audit Window (UTC):        {$startDate->copy()->utc()->toDateTimeString()} to {$endDate->copy()->utc()->toDateTimeString()}");

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from SaaS Alerts API...");
        $totalLiveEventCount = 0;
        $apiFail = false;

        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            $integration = $ic->integration;
            $client = new \App\Integrations\SaaS\SaaSClient($integration->credentials, $rateLimiter, $integration);

            $params = [
                'customerId' => $mapping->external_id,
                'start' => $startDate->copy()->utc()->toIso8601String(),
                'end' => $endDate->copy()->utc()->toIso8601String(),
            ];

            try {
                $liveCount = $client->getEventCount($params);
                $totalLiveEventCount += $liveCount;
                $this->line("      - [{$mapping->external_id}] Live Count: {$liveCount}");
            } catch (\Exception $e) {
                $this->warn("      - [{$mapping->external_id}] ⚠️ API Fetch Failed: " . $e->getMessage());
                $apiFail = true;
            }
        }

        $liveEventCount = $apiFail && $totalLiveEventCount === 0 ? 'N/A' : $totalLiveEventCount;

        // 3. Fetch Database Data
        $this->comment("2/3 Querying Local Database Cache...");
        // Ensure we pass UTC dates to the gatherer.
        $dbEvents = $gatherer->gatherSecurityEvents($customer, $icIds, $startDate->copy()->utc(), $endDate->copy()->utc());
        $dbEventCount = $dbEvents->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->total_events')
            ->where('report_data->options->frequency', $frequency)
            ->orderBy('generated_at', 'desc')
            ->first();

        $reportEvents = $lastReport ? ($lastReport->report_data['executive_summary']['total_events'] ?? 'N/A') : 'N/A';

        // 5. Results Table
        $this->newLine();
        $this->table(
            ['Source', 'Total Events', 'Status'],
            [
                ['Live API (Total)', $liveEventCount, 'Verified Source'],
                ['Local Database', $dbEventCount, ($liveEventCount !== 'N/A' && $dbEventCount == $liveEventCount ? '✅ MATCH' : '⚠️ DIFFERENT')],
                ['Last Report #' . ($lastReport->generated_report_id ?? 'N/A'), $reportEvents, ($reportEvents == $dbEventCount ? '✅ MATCH (DB)' : '⚠️ DIFFERENT')],
            ]
        );

        if ($dbEventCount != $liveEventCount && $liveEventCount !== 'N/A') {
             $this->newLine();
             $this->warn("Mismatch detected! Recommended action:");
             $this->line("1. Run a 'historical' sync for the SaaS Alerts integration.");
             $this->line("2. Verify if any events were deleted or if the sync window was too small.");
        }


        return 0;
    }
}
