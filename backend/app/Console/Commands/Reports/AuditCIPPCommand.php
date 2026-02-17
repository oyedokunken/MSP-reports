<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\CIPPSummary\CIPPDataGatherer;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AuditCIPPCommand extends Command
{
    protected $signature = 'reports:audit-cipp
                            {customer_id : The ID of the customer}
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--frequency=monthly : daily, weekly, monthly}
                            {--skip-generate : Skip report generation and use last existing report}';

    protected $description = 'Audit CIPP (M365) report data by generating a fresh report then comparing Live API vs Database vs Report';

    public function handle(RateLimiter $rateLimiter): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing CIPP (M365): {$customer->canonical_name} ===");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'CIPP')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No CIPP integration found for this customer.");
            return 1;
        }

        $icIds = $mappings->pluck('integration_customer_id')->toArray();
        $this->info("    Mappings Found: " . $mappings->count());

        // Determine timezone from schedule
        $schedule = $customer->reportSchedules()->where('report_type', 'cipp_summary')->first();
        $timezone = $schedule ? ($schedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $frequency = $this->option('frequency') ?: ($schedule?->frequency ?: 'monthly');
        $this->line("    Using Timezone: {$timezone}");

        // Calculate audit window
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
        $this->newLine();

        // ── Step 1: Generate a fresh report using reports:generate-manual ──
        $generatedReport = null;
        if (!$this->option('skip-generate')) {
            $this->comment("0/4 Generating fresh CIPP report via reports:generate-manual...");
            try {
                $exitCode = Artisan::call('reports:generate-manual', [
                    'customer_id' => $customerId,
                    'report_type' => 'cipp_summary',
                    '--start' => $startDate->format('Y-m-d'),
                    '--end' => $endDate->format('Y-m-d'),
                    '--frequency' => $frequency,
                    '--no-deliver' => true,
                ]);
                $this->line(Artisan::output());

                if ($exitCode === 0) {
                    // Grab the most recently generated report for this customer
                    $generatedReport = GeneratedReport::withoutGlobalScopes()
                        ->where('customer_id', $customerId)
                        ->where('status', 'completed')
                        ->orderBy('generated_report_id', 'desc')
                        ->first();

                    if ($generatedReport) {
                        $this->info("    Report ready: #{$generatedReport->generated_report_id}");
                    }
                } else {
                    $this->warn("    Report generation exited with code {$exitCode}.");
                }
            } catch (\Exception $e) {
                $this->warn("    Report generation failed: " . $e->getMessage());
            }
        }

        // Fall back to last existing report if generation was skipped or failed
        if (!$generatedReport) {
            $this->comment("    Looking for last existing CIPP report...");
            $generatedReport = GeneratedReport::withoutGlobalScopes()
                ->where('customer_id', $customerId)
                ->where('status', 'completed')
                ->when($schedule, fn($q) => $q->where('report_schedule_id', $schedule->report_schedule_id))
                ->orderBy('generated_report_id', 'desc')
                ->first();

            if ($generatedReport) {
                $this->info("    Using existing report: #{$generatedReport->generated_report_id}");
            } else {
                $this->warn("    No existing CIPP report found. Report column will show N/A.");
            }
        }

        // ── Step 2: Fetch Live API Data ──
        $this->comment("1/3 Fetching Live Data from CIPP API...");
        $liveUsers = 'N/A';
        $liveMfaEnabled = 'N/A';
        $liveLicenseTypes = 'N/A';
        $liveAlerts = 'N/A';
        $liveDefenderDevices = 'N/A';
        $liveCAPolicies = 'N/A';
        $liveMailboxes = 'N/A';
        $apiFail = false;

        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            $integration = $ic->integration;
            $client = new \App\Integrations\CIPP\CIPPClient(
                $integration->credentials,
                $rateLimiter,
                $integration
            );

            // The external_id is the tenant filter (customerId / defaultDomainName)
            $tenantFilter = $mapping->external_id;
            $tenantDomain = $ic->customer_metadata['default_domain'] ?? $tenantFilter;
            $this->line("    Tenant: {$tenantDomain} ({$tenantFilter})");

            // Users & MFA
            try {
                $apiUsers = $client->listMFAUsers($tenantDomain);
                $liveUsers = count($apiUsers);
                $liveMfaEnabled = collect($apiUsers)->filter(function ($u) {
                    $perUser = $u['PerUser'] ?? $u['PerUserMFA'] ?? null;
                    if (is_string($perUser) && in_array(strtolower($perUser), ['enabled', 'enforced'])) {
                        return true;
                    }
                    $coveredByCA = $u['CoveredByCA'] ?? null;
                    if (!empty($coveredByCA) && $coveredByCA !== 'None' && $coveredByCA !== false) {
                        return true;
                    }
                    $coveredBySD = $u['CoveredBySD'] ?? null;
                    if ($coveredBySD === true || $coveredBySD === 'true') {
                        return true;
                    }
                    return false;
                })->count();
                $this->line("      - Users: {$liveUsers}, MFA Enabled: {$liveMfaEnabled}");
            } catch (\Exception $e) {
                $this->warn("      - Users/MFA API Failed: " . $e->getMessage());
                $apiFail = true;
            }

            // Licenses
            try {
                $apiLicenses = $client->listLicenses($tenantDomain);
                $liveLicenseTypes = count($apiLicenses);
                $this->line("      - License Types: {$liveLicenseTypes}");
            } catch (\Exception $e) {
                $this->warn("      - Licenses API Failed: " . $e->getMessage());
                $apiFail = true;
            }

            // Security Alerts
            try {
                $apiAlerts = $client->listSecurityAlerts($tenantDomain);
                $liveAlerts = count($apiAlerts);
                $this->line("      - Security Alerts: {$liveAlerts}");
            } catch (\Exception $e) {
                $this->warn("      - Security Alerts API Failed: " . $e->getMessage());
                $apiFail = true;
            }

            // Defender State
            try {
                $apiDefender = $client->listDefenderState($tenantDomain);
                $liveDefenderDevices = count($apiDefender);
                $this->line("      - Defender Devices: {$liveDefenderDevices}");
            } catch (\Exception $e) {
                $this->warn("      - Defender API Failed: " . $e->getMessage());
                $apiFail = true;
            }

            // Conditional Access Policies
            try {
                $apiCA = $client->listConditionalAccessPolicies($tenantDomain);
                $liveCAPolicies = count($apiCA);
                $this->line("      - CA Policies: {$liveCAPolicies}");
            } catch (\Exception $e) {
                $this->warn("      - CA Policies API Failed: " . $e->getMessage());
                $apiFail = true;
            }

            // Mailbox Usage
            try {
                $apiMailboxes = $client->listMailboxUsage($tenantDomain);
                $liveMailboxes = count($apiMailboxes);
                $this->line("      - Mailboxes: {$liveMailboxes}");
            } catch (\Exception $e) {
                $this->warn("      - Mailbox API Failed: " . $e->getMessage());
                $apiFail = true;
            }
        }

        // ── Step 3: Fetch Database Data ──
        $this->comment("2/3 Querying Local Database Cache...");
        $gatherer = new CIPPDataGatherer($customer);

        $dbUserData = $gatherer->gatherUserData($startDate->copy()->utc(), $endDate->copy()->utc());
        $dbLicenseData = $gatherer->gatherLicenseData($startDate->copy()->utc(), $endDate->copy()->utc());
        $dbSecurityData = $gatherer->gatherSecurityData($startDate->copy()->utc(), $endDate->copy()->utc());
        $dbDefenderData = $gatherer->gatherDefenderData($startDate->copy()->utc(), $endDate->copy()->utc());
        $dbCAData = $gatherer->gatherConditionalAccessData($startDate->copy()->utc(), $endDate->copy()->utc());
        $dbEmailData = $gatherer->gatherEmailData($startDate->copy()->utc(), $endDate->copy()->utc());

        $dbUsers = $dbUserData['total_users'];
        $dbMfaEnabled = $dbUserData['mfa_enabled'];
        $dbLicenseTypes = $dbLicenseData['total_license_types'];
        $dbAlerts = $dbSecurityData['total_alerts'];
        $dbDefenderDevices = $dbDefenderData['total_devices'];
        $dbCAPolicies = $dbCAData['total_policies'];
        $dbMailboxes = $dbEmailData['total_mailboxes'];

        // ── Step 4: Extract Report Data ──
        $this->comment("3/3 Checking Generated Report...");
        $reportUsers = 'N/A';
        $reportMfaEnabled = 'N/A';
        $reportLicenseTypes = 'N/A';
        $reportAlerts = 'N/A';
        $reportDefenderDevices = 'N/A';
        $reportCAPolicies = 'N/A';
        $reportMailboxes = 'N/A';
        $reportId = 'None';

        if ($generatedReport && $generatedReport->report_data) {
            $rd = $generatedReport->report_data;
            $reportId = (string) $generatedReport->generated_report_id;
            $reportUsers = $rd['executive_summary']['total_users'] ?? 'N/A';
            $reportMfaEnabled = $rd['executive_summary']['mfa_enabled'] ?? 'N/A';
            $reportLicenseTypes = $rd['executive_summary']['total_license_types'] ?? 'N/A';
            $reportAlerts = $rd['executive_summary']['total_alerts'] ?? 'N/A';
            $reportDefenderDevices = $rd['executive_summary']['defender_devices'] ?? 'N/A';
            $reportCAPolicies = $rd['executive_summary']['ca_policies_total'] ?? 'N/A';
            $reportMailboxes = $rd['executive_summary']['total_mailboxes'] ?? 'N/A';
        }

        // ── Results Tables ──
        $this->newLine();
        $this->info("User & Identity Comparison:");
        $this->table(
            ['Source', 'Total Users', 'MFA Enabled', 'Status'],
            [
                ['Live CIPP API', $liveUsers, $liveMfaEnabled, 'Verified Source'],
                ['Local Database', $dbUsers, $dbMfaEnabled, $this->matchStatus($dbUsers, $liveUsers)],
                ["Report #{$reportId}", $reportUsers, $reportMfaEnabled, $this->matchStatus($reportUsers, $dbUsers, 'DB')],
            ]
        );

        $this->newLine();
        $this->info("License Comparison:");
        $this->table(
            ['Source', 'License Types', 'Status'],
            [
                ['Live CIPP API', $liveLicenseTypes, 'Verified Source'],
                ['Local Database', $dbLicenseTypes, $this->matchStatus($dbLicenseTypes, $liveLicenseTypes)],
                ["Report #{$reportId}", $reportLicenseTypes, $this->matchStatus($reportLicenseTypes, $dbLicenseTypes, 'DB')],
            ]
        );

        $this->newLine();
        $this->info("Security Alerts Comparison:");
        $this->table(
            ['Source', 'Total Alerts', 'Status'],
            [
                ['Live CIPP API', $liveAlerts, 'Verified Source'],
                ['Local Database', $dbAlerts, $this->matchStatus($dbAlerts, $liveAlerts)],
                ["Report #{$reportId}", $reportAlerts, $this->matchStatus($reportAlerts, $dbAlerts, 'DB')],
            ]
        );

        $this->newLine();
        $this->info("Defender Devices Comparison:");
        $this->table(
            ['Source', 'Devices', 'Status'],
            [
                ['Live CIPP API', $liveDefenderDevices, 'Verified Source'],
                ['Local Database', $dbDefenderDevices, $this->matchStatus($dbDefenderDevices, $liveDefenderDevices)],
                ["Report #{$reportId}", $reportDefenderDevices, $this->matchStatus($reportDefenderDevices, $dbDefenderDevices, 'DB')],
            ]
        );

        $this->newLine();
        $this->info("Conditional Access Comparison:");
        $this->table(
            ['Source', 'Policies', 'Status'],
            [
                ['Live CIPP API', $liveCAPolicies, 'Verified Source'],
                ['Local Database', $dbCAPolicies, $this->matchStatus($dbCAPolicies, $liveCAPolicies)],
                ["Report #{$reportId}", $reportCAPolicies, $this->matchStatus($reportCAPolicies, $dbCAPolicies, 'DB')],
            ]
        );

        $this->newLine();
        $this->info("Mailbox Comparison:");
        $this->table(
            ['Source', 'Mailboxes', 'Status'],
            [
                ['Live CIPP API', $liveMailboxes, 'Verified Source'],
                ['Local Database', $dbMailboxes, $this->matchStatus($dbMailboxes, $liveMailboxes)],
                ["Report #{$reportId}", $reportMailboxes, $this->matchStatus($reportMailboxes, $dbMailboxes, 'DB')],
            ]
        );

        // ── Summary ──
        $this->newLine();
        $mismatches = [];
        if ($liveUsers !== 'N/A' && $dbUsers != $liveUsers) $mismatches[] = "Users (API: {$liveUsers}, DB: {$dbUsers})";
        if ($liveLicenseTypes !== 'N/A' && $dbLicenseTypes != $liveLicenseTypes) $mismatches[] = "Licenses (API: {$liveLicenseTypes}, DB: {$dbLicenseTypes})";
        if ($liveAlerts !== 'N/A' && $dbAlerts != $liveAlerts) $mismatches[] = "Alerts (API: {$liveAlerts}, DB: {$dbAlerts})";
        if ($liveDefenderDevices !== 'N/A' && $dbDefenderDevices != $liveDefenderDevices) $mismatches[] = "Defender (API: {$liveDefenderDevices}, DB: {$dbDefenderDevices})";
        if ($liveCAPolicies !== 'N/A' && $dbCAPolicies != $liveCAPolicies) $mismatches[] = "CA Policies (API: {$liveCAPolicies}, DB: {$dbCAPolicies})";
        if ($liveMailboxes !== 'N/A' && $dbMailboxes != $liveMailboxes) $mismatches[] = "Mailboxes (API: {$liveMailboxes}, DB: {$dbMailboxes})";

        if (empty($mismatches)) {
            $this->info("✅ All data sources match.");
        } else {
            $this->warn("⚠️ Mismatches detected:");
            foreach ($mismatches as $m) {
                $this->line("   - {$m}");
            }
            $this->newLine();
            $this->line("Recommended actions:");
            $this->line("1. Run a sync for the CIPP integration to refresh database cache.");
            $this->line("2. Note: Snapshot data (users, licenses, defender, CA, mailboxes) reflects current state.");
            $this->line("   Small differences are expected if a sync hasn't run recently.");
            $this->line("3. Security alerts are time-ranged; ensure the audit window matches the sync window.");
        }

        return 0;
    }

    private function matchStatus(mixed $value, mixed $compareTo, string $label = 'API'): string
    {
        if ($value === 'N/A' || $compareTo === 'N/A') {
            return '⚠️ N/A';
        }

        return $value == $compareTo ? "✅ MATCH ({$label})" : "❌ MISMATCH";
    }
}
