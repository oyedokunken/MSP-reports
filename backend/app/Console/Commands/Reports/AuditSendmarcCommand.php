<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\SendmarcSummary\SendmarcDataGatherer;
use App\Services\RateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditSendmarcCommand extends Command
{
    protected $signature = 'reports:audit-sendmarc {customer_id} {--days=30} {--start=} {--end=} {--frequency=monthly}';

    protected $description = 'Audit Sendmarc report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, SendmarcDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing Sendmarc: {$customer->canonical_name} ===");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'Sendmarc')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No Sendmarc integration found for this customer.");
            return 1;
        }

        $integrationCode = DB::table('integrations')
            ->where('integration_id', $mappings->first()->integration_id)
            ->first();
        $integration = \App\Models\Integration::find($mappings->first()->integration_id);
        
        // Determine timezone
        $smSchedule = $customer->reportSchedules()->where('report_type', 'sendmarc_summary')->first();
        $timezone = $smSchedule ? ($smSchedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $this->line("    Using Timezone: {$timezone}");
        
        // Window: Start/End options or Dynamic based on --days
        if ($this->option('start') && $this->option('end')) {
            $startDate = Carbon::parse($this->option('start'), $timezone)->startOfDay();
            $endDate = Carbon::parse($this->option('end'), $timezone)->endOfDay();
        } else {
            $days = (int) ($this->option('days') ?: 30);
            $endDate = now()->setTimezone($timezone)->endOfDay();
            $startDate = $endDate->copy()->subDays($days)->startOfDay();
        }
        
        $this->info("    Audit Window: {$startDate->toDateTimeString()} to {$endDate->toDateTimeString()}");

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from Sendmarc API...");
        
        $client = new \App\Integrations\Sendmarc\SendmarcClient(
            $integration->credentials,
            $rateLimiter,
            $integration
        );

        $liveVolumeTotal = 0;
        $livePassingTotal = 0;
        
        foreach ($mappings as $mapping) {
            $ic = \App\Models\IntegrationCustomer::find($mapping->integration_customer_id);
            $metadata = $ic->metadata ?? [];
            $domainUuid = $metadata['uuid'] ?? null;
            $accountUuid = $metadata['account_uuid'] ?? null;

            if (!$domainUuid || !$accountUuid) {
                $this->warn("      ⚠️ Missing UUIDs for domain: {$ic->name}");
                continue;
            }

            try {
                $totals = $client->getDomainVolumeTotals($accountUuid, $domainUuid, $startDate, $endDate);
                $liveVolumeTotal += ($totals['total_incidents'] ?? 0);
                $livePassingTotal += ($totals['total_complete_passes'] ?? 0);
                
                $this->line("      - Domain: {$ic->name} | Volume: " . ($totals['total_incidents'] ?? 0));
            } catch (\Exception $e) {
                 $this->warn("      ⚠️ API Fetch Failed for {$ic->name}: " . $e->getMessage());
            }
        }

        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        
        $icIds = $mappings->pluck('integration_customer_id')->toArray();
        $dbVolumeRows = $gatherer->gatherVolumeTimeline($customer, $icIds);
        
        // Filter rows by date range
        $dbVolumeFiltered = $dbVolumeRows->filter(function($row) use ($startDate, $endDate) {
            $ts = Carbon::parse($row['timestamp']); 
            return $ts->between($startDate, $endDate);
        });

        $dbTotal = $dbVolumeFiltered->sum(fn($r) => $r['payload']['total_incidents'] ?? 0);
        $dbPassing = $dbVolumeFiltered->sum(fn($r) => $r['payload']['total_complete_passes'] ?? 0);

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $frequency = $this->option('frequency') ?: 'monthly';
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->total_emails')
            ->where('report_data->options->frequency', $frequency)
            ->when($smSchedule, fn($q) => $q->where('report_schedule_id', $smSchedule->report_schedule_id))
            ->orderBy('generated_report_id', 'desc')
            ->first();


        $reportTotal = $lastReport ? ($lastReport->report_data['executive_summary']['total_emails'] ?? 'N/A') : 'N/A';
        $reportCompliance = $lastReport ? ($lastReport->report_data['executive_summary']['compliance_rate'] ?? 'N/A') : 'N/A';

        // Calculations
        $liveComplianceRate = $liveVolumeTotal > 0 
            ? round(($livePassingTotal / $liveVolumeTotal) * 100, 2) 
            : 0;
            
        $dbComplianceRate = $dbTotal > 0 
            ? round(($dbPassing / $dbTotal) * 100, 2) 
            : 0;

        // 5. Build Results Tables
        $this->newLine();
        $this->info("Email Volume Comparison (Last 30 Days):");
        
        $this->table(
            ['Source', 'Total Emails', 'Compliance Rate', 'Status'],
            [
                ['Live API', $liveVolumeTotal, $liveComplianceRate . '%', 'Verified Source'],
                ['Local Database', $dbTotal, $dbComplianceRate . '%', ($dbTotal == $liveVolumeTotal ? '✅ MATCH' : '⚠️ DIFFERENT')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportTotal, $reportCompliance . '%', ($reportTotal == $dbTotal ? '✅ MATCH (DB)' : '⚠️ DIFFERENT')],
            ]
        );

        $this->newLine();
        
        if ($dbTotal == $liveVolumeTotal) {
             $this->info("✅ Volume counts match perfectly.");
        } else {
             $this->warn("⚠️ Volume Mismatch: API {$liveVolumeTotal} vs DB {$dbTotal}");
             $this->line("   Run 'php artisan integrations:sync {$integration->integration_id}' to update.");
        }

        return 0;
    }
}
