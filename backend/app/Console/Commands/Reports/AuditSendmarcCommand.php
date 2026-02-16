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
    protected $signature = 'reports:audit-sendmarc
                            {customer_id : The ID of the customer}';

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
        $mapping = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'Sendmarc')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->first();

        if (!$mapping) {
            $this->error("No Sendmarc integration found for this customer.");
            return 1;
        }

        $icId = (int) $mapping->integration_customer_id;
        $externalId = $mapping->external_id; // This is the domain name usually? Or ID? Sendmarc uses domain name as external_id usually.
        // Wait, Sendmarc uses 'external_id' which is likely the domain ID from the mapper?
        // Let's verify. Mapper uses $domain['external_id'] which IS the ID.
        // But for API calls we need account UUID and domain UUID.
        // The integration_customer metadata should have these.
        
        $ic = IntegrationCustomer::find($icId);
        $metadata = $ic->metadata ?? [];
        $domainUuid = $metadata['uuid'] ?? null;
        $accountUuid = $metadata['account_uuid'] ?? null;

        if (!$domainUuid || !$accountUuid) {
            $this->error("Missing UUIDs in integration customer metadata (domain_uuid: $domainUuid, account_uuid: $accountUuid).");
            return 1;
        }

        $integration = $ic->integration;
        
        // Determine timezone
        $smSchedule = $customer->reportSchedules()->where('report_type', 'sendmarc_summary')->first();
        $timezone = $smSchedule ? ($smSchedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $this->line("    Using Timezone: {$timezone}");
        
        // Window: Last 30 Days (Standard Audit Window)
        $endDate = now()->setTimezone($timezone)->endOfDay();
        $startDate = $endDate->copy()->subDays(30)->startOfDay();
        
        $this->info("    Audit Window: {$startDate->toDateString()} to {$endDate->toDateString()}");

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from Sendmarc API...");
        
        $client = new \App\Integrations\Sendmarc\SendmarcClient(
            $integration->credentials,
            $rateLimiter,
            $integration
        );

        $liveVolume = [
            'total' => 0,
            'passing' => 0
        ];
        
        try {
            // Fetch Volume Totals for the exact window (Aggregate)
            $totals = $client->getDomainVolumeTotals($accountUuid, $domainUuid, $startDate, $endDate);
            $liveVolume['total'] = $totals['total_incidents'] ?? 0;
            $liveVolume['passing'] = $totals['total_complete_passes'] ?? 0;
            
            // Fetch Volume Timeline sum for apples-to-apples comparison with DB
            $timeline = $client->getDomainVolumeTimeline($accountUuid, $domainUuid, $startDate, $endDate);
            $liveTimelineTotal = collect($timeline)->sum('total_incidents');
            
        } catch (\Exception $e) {
             $this->warn("    ⚠️ API Fetch Failed: " . $e->getMessage());
             $liveVolume['total'] = 'N/A';
             $liveTimelineTotal = 'N/A';
        }

        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        
        // Gatherer fetches ALL rows for this customer
        $dbVolumeRows = $gatherer->gatherVolumeTimeline($customer, [$icId]);
        
        // Filter rows by date range (using timestamp in payload or row)
        $dbVolumeFiltered = $dbVolumeRows->filter(function($row) use ($startDate, $endDate) {
            $ts = Carbon::parse($row['timestamp']); 
            return $ts->between($startDate, $endDate);
        });

        $dbTotal = $dbVolumeFiltered->sum(fn($r) => $r['payload']['total_incidents'] ?? 0);
        $dbPassing = $dbVolumeFiltered->sum(fn($r) => $r['payload']['total_complete_passes'] ?? 0);

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->total_emails') // Check unique field
            ->when($smSchedule, fn($q) => $q->where('report_schedule_id', $smSchedule->report_schedule_id))
            ->orderBy('generated_report_id', 'desc')
            ->first();

        $reportTotal = $lastReport ? ($lastReport->report_data['executive_summary']['total_emails'] ?? 'N/A') : 'N/A';
        $reportCompliance = $lastReport ? ($lastReport->report_data['executive_summary']['compliance_rate'] ?? 'N/A') : 'N/A';

        // Calculations
        $liveComplianceRate = is_numeric($liveTimelineTotal) && $liveTimelineTotal > 0 
            ? round(($liveVolume['passing'] / $liveTimelineTotal) * 100, 2) 
            : 0;
            
        $dbComplianceRate = $dbTotal > 0 
            ? round(($dbPassing / $dbTotal) * 100, 2) 
            : 0;

        // 5. Build Results Tables
        $this->newLine();
        $this->info("Email Volume Comparison (Last 30 Days):");
        
        // Use Live Timeline Sum for comparison if available, otherwise Totals
        $apiValue = is_numeric($liveTimelineTotal) ? $liveTimelineTotal : $liveVolume['total'];
        
        $this->table(
            ['Source', 'Total Emails', 'Compliance Rate', 'Status'],
            [
                ['Live API', $apiValue, $liveComplianceRate . '%', 'Verified Source'],
                ['Local Database', $dbTotal, $dbComplianceRate . '%', ($dbTotal == $apiValue ? '✅ MATCH' : '⚠️ DIFFERENT')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportTotal, $reportCompliance . '%', ($reportTotal == $dbTotal ? '✅ MATCH (DB)' : '⚠️ DIFFERENT')],
            ]
        );

        $this->newLine();
        
        if ($dbTotal == $apiValue) {
             $this->info("✅ Volume counts match perfectly.");
        } else {
             $this->warn("⚠️ Volume Mismatch: API {$apiValue} vs DB {$dbTotal}");
             $this->line("   Run 'php artisan integrations:sync {$integration->integration_id}' to update.");
        }

        return 0;
    }
}
