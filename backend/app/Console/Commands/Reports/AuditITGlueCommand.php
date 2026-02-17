<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\IntegrationCustomer;
use App\Reports\ITGlueSummary\ITGlueDataGatherer;
use App\Services\RateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditITGlueCommand extends Command
{
    protected $signature = 'reports:audit-itglue
                            {customer_id : The ID of the customer}
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--frequency=monthly : daily, weekly, monthly}';

    protected $description = 'Audit ITGlue report data by comparing Live API vs Database vs Last Report';

    public function handle(RateLimiter $rateLimiter, ITGlueDataGatherer $gatherer): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing ITGlue: {$customer->canonical_name} ===");
        $this->newLine();

        // 1. Get Integration Info
        $mappings = DB::table('customer_mappings')
            ->join('integration_customers', 'customer_mappings.integration_customer_id', '=', 'integration_customers.integration_customer_id')
            ->where('customer_mappings.customer_id', $customerId)
            ->where('integration_customers.integration_id', function ($q) {
                $q->select('integration_id')
                    ->from('integrations')
                    ->where('integration_type', 'ITGlue')
                    ->limit(1);
            })
            ->select('integration_customers.*')
            ->get();

        if ($mappings->isEmpty()) {
            $this->error("No ITGlue integration found for this customer.");
            return 1;
        }

        $icIds = $mappings->pluck('integration_customer_id')->toArray();

        // 2. Fetch Live API Data
        $this->comment("1/3 Fetching Live Data from ITGlue API...");
        $totalLiveSslCount = 0;
        $totalLiveDomainCount = 0;
        $totalLiveFlexibleAssets = 0;
        
        foreach ($mappings as $mapping) {
            $ic = IntegrationCustomer::find($mapping->integration_customer_id);
            $integration = $ic->integration;
            $client = new \App\Integrations\ITGlue\ITGlueClient(
                $integration->credentials,
                $rateLimiter,
                $integration
            );

            $liveSslCerts = collect($client->getSslCertificates(['organization_id' => $mapping->external_id]));
            $liveDomains = collect($client->getDomains(['organization_id' => $mapping->external_id]));

            $totalLiveSslCount += $liveSslCerts->count();
            $totalLiveDomainCount += $liveDomains->count();

            try {
                $licenseTypeIds = $client->findLicenseAssetTypeIds();
                foreach ($licenseTypeIds as $typeId) {
                    $assets = $client->getFlexibleAssets($typeId, $mapping->external_id);
                    $totalLiveFlexibleAssets += count($assets);
                }
            } catch (\Exception $e) {
                $this->warn("  Could not fetch flexible assets for {$ic->name}: " . $e->getMessage());
            }
        }

        $liveSslCount = $totalLiveSslCount;
        $liveDomainCount = $totalLiveDomainCount;
        $liveFlexibleAssets = $totalLiveFlexibleAssets;

        // 3. Fetch Database Data (Using Gatherer logic)
        $this->comment("2/3 Querying Local Database Cache...");
        
        $customerSchedule = $customer->reportSchedules()->where('report_type', 'itglue_summary')->first();
        $timezone = $customerSchedule ? ($customerSchedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $options = ['timezone' => $timezone];
        
        $endDate = $this->option('end') 
            ? Carbon::parse($this->option('end'), $timezone)->endOfDay() 
            : now()->setTimezone($timezone)->endOfDay();

        // Use 5-day activity window to match standard report logic
        $dbCerts = $gatherer->gatherCertificates($customer, $icIds, $options, $endDate, 5);
        $dbDomains = $gatherer->gatherDomains($customer, $icIds, $options, $endDate, 5);
        $dbLicenses = $gatherer->gatherLicenses($customer, $icIds, $endDate, 5);


        $dbSslCount = $dbCerts->count();
        $dbDomainCount = $dbDomains->count();
        $dbLicenseCount = $dbLicenses->count();

        // 4. Get Latest Generated Report
        $this->comment("3/3 Checking Latest Generated Report...");
        $frequency = $this->option('frequency') ?: 'monthly';
        $itglueSchedule = $customer->reportSchedules()->where('report_type', 'itglue_summary')->first();
        $lastReport = GeneratedReport::where('customer_id', $customerId)
            ->whereNotNull('report_data->executive_summary->ssl_certificates')
            ->where('report_data->options->frequency', $frequency)
            ->when($itglueSchedule, fn($q) => $q->where('report_schedule_id', $itglueSchedule->report_schedule_id))
            ->orderBy('generated_report_id', 'desc')
            ->first();

        $reportSsl = $lastReport ? ($lastReport->report_data['executive_summary']['ssl_certificates']['total'] ?? 'N/A') : 'N/A';
        $reportDomains = $lastReport ? ($lastReport->report_data['executive_summary']['domains']['total'] ?? 'N/A') : 'N/A';
        $reportLicenses = $lastReport ? ($lastReport->report_data['executive_summary']['licenses']['total'] ?? 'N/A') : 'N/A';

        // 5. Build Results Tables
        // Note: ITGlue sync fetches certs with 'next90days' filter, so the DB retains
        // historical certs that have since expired beyond the API window.
        $dbActiveCerts = $dbCerts->where('severity', '!=', 'expired')->count();
        $dbExpiredCerts = $dbCerts->where('severity', 'expired')->count();

        $this->newLine();
        $this->info("SSL Certificate Comparison:");
        $this->table(
            ['Source', 'SSL Certificates', 'Expired', 'Active', 'Status'],
            [
                ['Live ITGlue API', $liveSslCount, '-', $liveSslCount, 'Verified (next 90 days)'],
                ['Local Database', $dbSslCount, $dbExpiredCerts, $dbActiveCerts, ($dbActiveCerts == $liveSslCount ? '✅ MATCH (Active)' : '⚠️ Active: ' . $dbActiveCerts . ' vs API: ' . $liveSslCount)],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportSsl, '-', '-', ($reportSsl == $dbSslCount ? '✅ MATCH (DB)' : '⚠️ DIFFERENT')],
            ]
        );

        $this->newLine();
        $this->info("Domain Comparison:");
        $this->table(
            ['Source', 'Domains', 'Status'],
            [
                ['Live ITGlue API', $liveDomainCount, 'Verified Source'],
                ['Local Database', $dbDomainCount, ($dbDomainCount == $liveDomainCount ? '✅ MATCH' : '❌ MISMATCH (' . ($liveDomainCount - $dbDomainCount) . ')')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportDomains, ($reportDomains == $dbDomainCount ? '✅ MATCH' : '⚠️ DIFFERENT')],
            ]
        );

        $this->newLine();
        $this->info("License Comparison:");
        $this->table(
            ['Source', 'Licenses', 'Status'],
            [
                ['Live ITGlue API', $liveFlexibleAssets, 'Verified Source'],
                ['Local Database', $dbLicenseCount, ($dbLicenseCount == $liveFlexibleAssets ? '✅ MATCH' : '⚠️ DIFFERENT')],
                ['Last Report (ID: ' . ($lastReport->generated_report_id ?? 'None') . ')', $reportLicenses, ($reportLicenses == $dbLicenseCount ? '✅ MATCH' : '⚠️ DIFFERENT')],
            ]
        );

        // Summary alerts
        $this->newLine();
        if ($dbActiveCerts == $liveSslCount && $dbDomainCount == $liveDomainCount) {
            $this->info("✅ All active asset counts match between Live API and Local Database.");
            if ($dbExpiredCerts > 0) {
                $this->comment("  ℹ️  {$dbExpiredCerts} expired SSL cert(s) retained in DB from previous syncs.");
            }
        } else {
            $this->warn("⚠️ Mismatch detected. Run a sync to update local data.");
        }

        // SSL cert health summary
        $expiredCerts = $dbCerts->where('severity', 'expired')->count();
        $criticalCerts = $dbCerts->where('severity', 'critical')->count();
        $warningCerts = $dbCerts->where('severity', 'warning')->count();
        $expiredDomains = $dbDomains->where('severity', 'expired')->count();
        $criticalDomains = $dbDomains->where('severity', 'critical')->count();

        if ($expiredCerts > 0 || $criticalCerts > 0 || $expiredDomains > 0 || $criticalDomains > 0) {
            $this->newLine();
            $this->comment("⚠️ Health Alerts:");
            if ($expiredCerts > 0) $this->line("  • {$expiredCerts} SSL certificate(s) EXPIRED");
            if ($criticalCerts > 0) $this->line("  • {$criticalCerts} SSL certificate(s) expiring within 7 days");
            if ($warningCerts > 0) $this->line("  • {$warningCerts} SSL certificate(s) expiring within 30 days");
            if ($expiredDomains > 0) $this->line("  • {$expiredDomains} domain(s) EXPIRED");
            if ($criticalDomains > 0) $this->line("  • {$criticalDomains} domain(s) expiring within 14 days");
        }

        return 0;
    }
}
