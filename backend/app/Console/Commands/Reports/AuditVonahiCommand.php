<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationData;
use App\Reports\VonahiSummary\VonahiDataGatherer;
use App\Services\RateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditVonahiCommand extends Command
{
    protected $signature = 'reports:audit-vonahi {customer_id} {--days=30} {--start=} {--end=}';

    protected $description = 'Audit Vonahi report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, VonahiDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing Vonahi: {$customer->canonical_name} ===");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'Vonahi')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No Vonahi integration found for this customer.");
            return 1;
        }

        $integration = \App\Models\Integration::find($mappings->first()->integration_id);
        
        // Determine timezone
        $smSchedule = $customer->reportSchedules()->where('report_type', 'vonahi_summary')->first();
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
        $this->comment("1/3 Fetching Live Data from Vonahi API...");
        
        $client = new \App\Integrations\Vonahi\VonahiClient(
            $integration->credentials,
            $rateLimiter,
            $integration
        );

        $liveAssessmentsCount = 0;
        $liveFindingsCount = 0;
        $liveActiveAgents = 0;
        
        foreach ($mappings as $mapping) {
            $ic = \App\Models\IntegrationCustomer::find($mapping->integration_customer_id);
            $externalId = $ic->external_id; // Company UUID in Vonahi

            if (!$externalId) {
                $this->warn("      ⚠️ Missing External ID for mapping: {$ic->name}");
                continue;
            }

            try {
                // Fetch Assessments
                $assessments = collect($client->getAssessments($externalId));
                $filteredAssessments = $assessments->filter(function($a) use ($startDate, $endDate) {
                    $ts = Carbon::parse($a['created_at']);
                    return $ts->between($startDate, $endDate);
                });
                if ($filteredAssessments->isNotEmpty()) {
                    $liveAssessmentsCount += $filteredAssessments->count();
                } else {
                    $liveAssessmentsCount += $assessments->take(5)->count();
                }

                // Fetch Findings
                // If there are assessments in range, fetch findings for them.
                // If the report falls back to "latest" findings when range is empty, we should track that too.
                $allFindings = collect();
                foreach ($assessments as $assessment) {
                    $findings = $client->getFindings((string)$assessment['id']);
                    $allFindings = $allFindings->concat($findings);
                }

                $rangeFindings = $allFindings->filter(function($f) use ($startDate, $endDate) {
                    $ts = Carbon::parse($f['created_at']);
                    return $ts->between($startDate, $endDate);
                });

                if ($rangeFindings->isNotEmpty()) {
                    $liveFindingsCount += $rangeFindings->count();
                } else {
                    // Fallback to latest 20 to match report logic
                    $liveFindingsCount += $allFindings->take(20)->count();
                }

                // Fetch Agents
                $agents = $client->getAgents($externalId);
                $activeAgents = collect($agents)->filter(function($a) {
                    $status = strtolower($a['activation_status'] ?? '');
                    return $status === 'active' || $status === 'online';
                })->count();
                $liveActiveAgents += $activeAgents;

                $this->line("      - Mapping: {$ic->name} | Assessments: {$filteredAssessments->count()} | Findings: {$liveFindingsCount} | Active Agents: {$activeAgents}");
            } catch (\Exception $e) {
                 $this->warn("      ⚠️ API Fetch Failed for {$ic->name}: " . $e->getMessage());
            }
        }

        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        
        $icIds = $mappings->pluck('integration_customer_id')->toArray();
        $rawAssessments = $gatherer->gatherAssessments($customer, $icIds);
        $rawFindings = $gatherer->gatherFindings($customer, $icIds);

        $dbAssessments = $rawAssessments->filter(fn($r) => Carbon::parse($r['timestamp'])->between($startDate, $endDate));
        $dbFindings = $rawFindings->filter(fn($r) => Carbon::parse($r['timestamp'])->between($startDate, $endDate));
        
        // Match report fallback behavior
        if ($dbAssessments->isEmpty() && $rawAssessments->isNotEmpty()) {
            $dbAssessments = $rawAssessments->take(5);
        }

        if ($dbFindings->isEmpty() && $rawFindings->isNotEmpty()) {
            $dbFindings = $rawFindings->take(20);
        }

        $dbAgents = $gatherer->gatherAgents($customer, $icIds);
        
        $dbAssessmentsCount = $dbAssessments->count();
        $dbFindingsCount = $dbFindings->count();
        
        $dbActiveAgents = 0;
        foreach ($dbAgents as $agent) {
            $status = strtolower($agent['payload']['activation_status'] ?? '');
            if ($status === 'active' || $status === 'online') {
                $dbActiveAgents++;
            }
        }

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereHas('reportSchedule', function($q) {
                $q->where('report_type', 'vonahi_summary');
            })
            ->whereNotNull('report_data->executive_summary->total_assessments')
            ->orderBy('generated_report_id', 'desc')
            ->first();

        $reportAssessments = $lastReport ? ($lastReport->report_data['executive_summary']['total_assessments'] ?? 'N/A') : 'N/A';
        $reportFindings = $lastReport ? ($lastReport->report_data['executive_summary']['total_findings'] ?? 'N/A') : 'N/A';
        $reportAgents = $lastReport ? ($lastReport->report_data['executive_summary']['active_agents'] ?? 'N/A') : 'N/A';

        // 5. Build Results Tables
        $this->newLine();
        $this->info("Vonahi Security Audit Summary:");
        
        $this->table(
            ['Source', 'Assessments', 'Findings', 'Active Agents', 'Status'],
            [
                ['Live API', $liveAssessmentsCount, $liveFindingsCount, $liveActiveAgents, 'Verified Source'],
                ['Local Database', $dbAssessmentsCount, $dbFindingsCount, $dbActiveAgents, ($dbFindingsCount == $liveFindingsCount ? '✅ MATCH' : '⚠️ DIFFERENT')],
                ['Last Report #' . ($lastReport->generated_report_id ?? 'N/A'), $reportAssessments, $reportFindings, $reportAgents, ($reportFindings == $dbFindingsCount ? '✅ MATCH (DB)' : '⚠️ DIFFERENT')],
            ]
        );

        return 0;
    }
}
