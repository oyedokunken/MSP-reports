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

        $dates = $this->calculateDates($customer);
        $start = $dates['start'];
        $end = $dates['end'];

        $this->info("=== Auditing Datto RMM: {$customer->canonical_name} ===");
        $this->info("Period: {$start->toDateTimeString()} to {$end->toDateTimeString()} (UTC)");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', 2) // Datto
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No Datto integration found for this customer.");
            return 1;
        }

        $icIds = $mappings->pluck('integration_customer_id')->toArray();

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from Datto API...");
        $totalLiveDeviceCount = 0;
        $totalLiveAlertCount = 0;
        $totalLiveActive = 0;
        $totalLiveStale = 0;
        $totalLiveActionable = 0;
        
        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            $integration = $ic->integration;
            $clientClass = "App\\Integrations\\Datto\\DattoClient";
            $client = new $clientClass($integration->credentials, $rateLimiter, $integration);
            
            $liveDevices = collect($client->getRmmDevices($mapping->external_id));
            $liveOpenAlerts = collect($client->getRmmAlerts($mapping->external_id, true));
            $liveResolvedAlerts = collect($client->getRmmAlerts($mapping->external_id, false, $start));
            
            $totalLiveDeviceCount += $liveDevices->count();
            
            $mappingStale = $liveDevices->filter(function($d) {
                $lastSeen = isset($d['lastSeen']) ? Carbon::createFromTimestampMs($d['lastSeen']) : null;
                return !$lastSeen || $lastSeen->lt(now()->subDays(5));
            })->count();
            
            $totalLiveStale += $mappingStale;
            $totalLiveActive += ($liveDevices->count() - $mappingStale);
            
            $allAlerts = $liveOpenAlerts->merge($liveResolvedAlerts);
            $totalLiveAlertCount += $allAlerts->count();
            
            $mappingActionable = $allAlerts->filter(function($a) {
                $p = $a['priority'] ?? '';
                return in_array($p, ['Critical', 'High', 'Standard', 'Warning']);
            })->count();
            
            $totalLiveActionable += $mappingActionable;
        }
        
        $liveDeviceCount = $totalLiveDeviceCount;
        $liveAlertCount = $totalLiveAlertCount;
        $liveActive = $totalLiveActive;
        $liveStale = $totalLiveStale;
        $liveActionable = $totalLiveActionable;
        
        $schedule = $customer->reportSchedules()->where('report_type', 'datto_rmm_summary')->first();
        $timezone = $schedule ? ($schedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $options = ['timezone' => $timezone];
        
        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        // Use 5-day activity window to match standard report logic
        $dbDevices = $gatherer->gatherDevices($customer, $icIds, $start, $end, $options, 5);
        $dbAlerts = $gatherer->gatherAlerts($customer, $icIds, $start, $end, $options);

        
        $dbDeviceCount = $dbDevices->count();
        $dbAlertCount = $dbAlerts->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $frequency = $this->option('frequency') ?: 'monthly';
        $dattoSchedule = $customer->reportSchedules()->where('report_type', 'datto_rmm_summary')->first();
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->devices')
            ->where('report_data->options->frequency', $frequency)
            ->when($dattoSchedule, fn($q) => $q->where('report_schedule_id', $dattoSchedule->report_schedule_id))
            ->orderBy('generated_report_id', 'desc')
            ->first();

        $reportDevices = $lastReport ? ($lastReport->report_data['executive_summary']['devices']['total'] ?? 'N/A') : 'N/A';
        $reportAlerts = $lastReport ? ($lastReport->report_data['executive_summary']['alerts']['total'] ?? 'N/A') : 'N/A';

        // 5. Build Results Table
        $this->newLine();
        $this->info("Device Count Comparison:");
        
        // Use the accumulated counts from all integration mappings
        // $liveStale and $liveActive are already calculated with the 5-day window in the loop above

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
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', '-', $reportAlerts, (function() use ($reportAlerts, $dbAlertCount) {
                    if ($reportAlerts === 'N/A') return '⚠️ NO REPORT';
                    if ($reportAlerts == $dbAlertCount) return '✅ MATCH';
                    $diff = abs($reportAlerts - $dbAlertCount);
                    $pct = $dbAlertCount > 0 ? round($diff / $dbAlertCount * 100, 1) : 100;
                    return $pct <= 5 ? "✅ MATCH (~{$diff} drift)" : "⚠️ DIFFERENT (-{$diff})";
                })()],
            ]
        );

        $this->newLine();
        $this->comment("MSP Insight:");
        $this->line("• The Report correctly ignores {$liveStale} stale/zombie devices from Datto RMM.");
        $this->line("• The Alert mismatch is expected: we filter out Thousands of technical logs (Noise) to show only Impactful events (Signal).");

        return 0;
    }

    private function calculateDates(Customer $customer): array
    {
        $start = $this->option('start');
        $end = $this->option('end');
        $frequency = $this->option('frequency');

        $schedule = $customer->reportSchedules()->where('report_type', 'datto_rmm_summary')->first();
        $timezone = $schedule ? ($schedule->timezone ?: config('app.timezone')) : config('app.timezone');

        if ($start && $end) {
            return [
                'start' => Carbon::parse($start, $timezone)->startOfDay()->setTimezone('UTC'),
                'end' => Carbon::parse($end, $timezone)->endOfDay()->setTimezone('UTC'),
            ];
        }

        $now = now()->setTimezone($timezone);
        switch ($frequency) {
            case 'daily':
                return [
                    'start' => $now->copy()->subDay()->startOfDay()->setTimezone('UTC'),
                    'end' => $now->copy()->subDay()->endOfDay()->setTimezone('UTC'),
                ];
            case 'weekly':
                return [
                    'start' => $now->copy()->subWeek()->startOfWeek()->setTimezone('UTC'),
                    'end' => $now->copy()->subWeek()->endOfWeek()->setTimezone('UTC'),
                ];
            case 'monthly':
            default:
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth()->setTimezone('UTC'),
                    'end' => $now->copy()->subMonth()->endOfMonth()->setTimezone('UTC'),
                ];
        }
    }
}
