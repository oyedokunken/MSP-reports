<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\IntegrationData;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\AutotaskSummary\AutotaskDataGatherer;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditAutotaskCommand extends Command
{
    protected $signature = 'reports:audit-autotask 
                            {customer_id : The ID of the customer} 
                            {--start= : Start date (YYYY-MM-DD)} 
                            {--end= : End date (YYYY-MM-DD)} 
                            {--frequency=monthly : daily, weekly, monthly}';

    protected $description = 'Audit Autotask report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, AutotaskDataGatherer $gatherer): int
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

        $this->info("=== Auditing Autotask: {$customer->canonical_name} ===");
        $this->info("Period: {$start->toDateTimeString()} to {$end->toDateTimeString()} (UTC)");
        $this->newLine();

        // 1. Get Integration Info
        $mapping = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', 9) // Autotask
            ->select('integration_customers.*')
            ->first();

        if (!$mapping) {
            $this->error("No Autotask integration found for this customer.");
            return 1;
        }

        $icId = (int) $mapping->integration_customer_id;
        $externalId = (int) $mapping->external_id;

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from Autotask API...");
        $ic = IntegrationCustomer::find($icId);
        $integration = $ic->integration;
        $clientClass = "App\\Integrations\\Autotask\\AutotaskClient";
        $client = new $clientClass($integration->credentials, $rateLimiter, $integration);
        
        $liveTickets = collect($client->getTicketsAudit($start->copy()->utc(), $end->copy()->utc(), $externalId));
        $liveCompleted = $liveTickets->count(); 
        
        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        $dbTickets = $gatherer->gatherTickets($customer, [$icId], $start, $end);
        
        $dbCompleted = $dbTickets->filter(function($t) use ($start, $end) {
            if (!$t['completed_date']) return false;
            $comp = Carbon::parse($t['completed_date']);
            return $comp->between($start, $end);
        })->count();

        $dbOpen = $dbTickets->filter(fn($t) => ($t['status'] ?? null) != 5)->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $query = GeneratedReport::where('customer_id', $customerId)
            ->where('report_data->tickets->summary', '!=', null)
            ->where('report_data->options->frequency', $this->option('frequency'));

        $startOpt = $this->option('start');
        $endOpt = $this->option('end');
        if ($startOpt && $endOpt) {
            $query->where('report_data->options->start_date', $startOpt)
                  ->where('report_data->options->end_date', $endOpt);
        }

        $lastReport = $query->orderBy('generated_at', 'desc')->first();

        $reportCompleted = $lastReport ? ($lastReport->report_data['tickets']['summary']['completed_tickets'] ?? 'N/A') : 'N/A';
        $reportOpen = $lastReport ? ($lastReport->report_data['tickets']['summary']['open_tickets'] ?? 'N/A') : 'N/A';

        // 5. Build Results Table
        $this->newLine();
        $this->table(
            ['Source', 'Completed Tickets', 'Open Tickets', 'Status'],
            [
                ['Live Autotask API', $liveCompleted, 'N/A', 'Verified Source'],
                ['Local Database', $dbCompleted, $dbOpen, ($dbCompleted == $liveCompleted ? '✅ MATCH' : '❌ MISMATCH')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportCompleted, $reportOpen, ($reportCompleted == $liveCompleted ? '✅ MATCH' : '❌ MISMATCH')],
            ]
        );

        if ($dbCompleted != $liveCompleted) {
            $this->newLine();
            $this->warn("Mismatch detected! Recommended action:");
            $this->line("1. Run a 'historical' sync for this customer.");
            $this->line("2. Check for orphaned records with company_id: {$externalId}");
        }

        return 0;
    }

    private function calculateDates(Customer $customer): array
    {
        $start = $this->option('start');
        $end = $this->option('end');
        $frequency = $this->option('frequency');

        $schedule = $customer->reportSchedules()->where('report_type', 'autotask_summary')->first();
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
                    'end' => $now->copy()->subDay()->endOfDay()->setTimezone('UTC')
                ];
            case 'weekly':
                return [
                    'start' => $now->copy()->subWeek()->startOfWeek()->setTimezone('UTC'), 
                    'end' => $now->copy()->subWeek()->endOfWeek()->setTimezone('UTC')
                ];
            case 'monthly':
            default:
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth()->setTimezone('UTC'), 
                    'end' => $now->copy()->subMonth()->endOfMonth()->setTimezone('UTC')
                ];
        }
    }
}
