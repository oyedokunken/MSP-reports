<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\Contracts\IntegrationDataType;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use App\Integrations\Datto\DattoClient;
use Illuminate\Console\Command;

class VerifyDattoSync extends Command
{
    protected $signature = 'verify:datto-sync {--sites=3 : Number of sites to sample}';
    protected $description = 'Verify Datto sync accuracy by comparing live API data against the database.';

    public function handle(): int
    {
        $limit = (int) $this->option('sites');
        $integration = Integration::where('integration_type', 'Datto')->firstOrFail();

        $this->info("=== Datto Sync Verification ===");
        $this->info("Sampling {$limit} sites against live API...\n");

        $rateLimiter = app(RateLimiter::class);
        $client = new DattoClient(
            $integration->credentials ?? [],
            $rateLimiter,
            $integration
        );

        // Use sites API to get device counts (it has totalCount in devicesStatus)
        $this->line("Fetching site list from API...");
        $allSites = $client->getRmmSites();
        $siteMap = collect($allSites)->keyBy('uid');

        // Pick random sampled sites that exist in DB
        $customers = IntegrationCustomer::withoutGlobalScopes()
            ->where('integration_id', $integration->integration_id)
            ->whereNotNull('metadata->last_device_sync_at')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        if ($customers->isEmpty()) {
            $this->error('No synced customers found. Run a full sync first.');
            return 1;
        }

        $rows = [];
        $totals = ['api_dev' => 0, 'db_dev' => 0, 'api_open' => 0, 'db_open' => 0, 'db_all' => 0];

        foreach ($customers as $customer) {
            $siteUid = $customer->external_id;
            $siteName = $customer->name;
            $metadata = $customer->metadata ?? [];
            $siteData = $siteMap[$siteUid] ?? null;

            $this->line("Checking: <info>" . substr($siteName, 0, 40) . "</info>");

            // ── API device count (from sites endpoint) ───────────────
            $apiDeviceCount = 0;
            if ($siteData) {
                $apiDeviceCount = $siteData['devicesStatus']['numberOfDevices'] ?? 0;
            }

            // ── API open alert count (paginate through to count) ─────
            $apiOpenCount = 0;
            try {
                $page = 0;
                $hasMore = true;
                while ($hasMore && $page <= 200) {
                    $resp = $client->makeRmmRequest("/site/{$siteUid}/alerts/open", ['max' => 250, 'page' => $page]);
                    $alerts = $resp['alerts'] ?? [];
                    $apiOpenCount += count($alerts);
                    $hasMore = !empty($resp['pageDetails']['nextPageUrl']);
                    $page++;
                }
            } catch (\Exception $e) {
                $this->warn("  API error (open alerts): {$e->getMessage()}");
            }

            // ── DB device count ──────────────────────────────────────
            $dbDeviceCount = IntegrationData::withoutGlobalScopes()
                ->where('integration_id', $integration->integration_id)
                ->where('integration_customer_id', $customer->integration_customer_id)
                ->where('data_type', IntegrationDataType::DEVICE_STATUS)
                ->count();

            // ── DB alert counts ──────────────────────────────────────
            $dbAlertCount = IntegrationData::withoutGlobalScopes()
                ->where('integration_id', $integration->integration_id)
                ->where('integration_customer_id', $customer->integration_customer_id)
                ->where('data_type', IntegrationDataType::ALERT)
                ->count();

            $dbOpenAlertCount = IntegrationData::withoutGlobalScopes()
                ->where('integration_id', $integration->integration_id)
                ->where('integration_customer_id', $customer->integration_customer_id)
                ->where('data_type', IntegrationDataType::ALERT)
                ->whereRaw("data_payload::jsonb->>'resolved' = 'false'")
                ->count();

            // ── Matching ─────────────────────────────────────────────
            $devDiff = abs($apiDeviceCount - $dbDeviceCount);
            $devMatch = $devDiff === 0 ? '✅ exact' : ($devDiff <= 3 ? "≈ ±{$devDiff}" : "❌ diff {$devDiff}");

            $openDiff = abs($apiOpenCount - $dbOpenAlertCount);
            $openMatch = $openDiff === 0 ? '✅ exact' : ($openDiff <= 5 ? "≈ ±{$openDiff}" : "❌ diff {$openDiff}");

            $rows[] = [
                substr($siteName, 0, 28),
                $apiDeviceCount,
                $dbDeviceCount,
                $devMatch,
                $apiOpenCount,
                $dbOpenAlertCount,
                $openMatch,
                $dbAlertCount,
            ];

            $totals['api_dev'] += $apiDeviceCount;
            $totals['db_dev'] += $dbDeviceCount;
            $totals['api_open'] += $apiOpenCount;
            $totals['db_open'] += $dbOpenAlertCount;
            $totals['db_all'] += $dbAlertCount;
        }

        $this->newLine();
        $this->table(
            ['Site', 'API Dev', 'DB Dev', 'Dev Match', 'API Open', 'DB Open', 'Open Match', 'DB All Alerts'],
            $rows
        );

        // ── Summary ──────────────────────────────────────────────────
        $this->newLine();
        $this->info("=== Summary ({$limit} sites) ===");

        $devTotal = abs($totals['api_dev'] - $totals['db_dev']);
        $openTotal = abs($totals['api_open'] - $totals['db_open']);

        $this->line("  Devices:     API={$totals['api_dev']}  DB={$totals['db_dev']}  " . ($devTotal === 0 ? '✅' : "⚠️ diff {$devTotal}"));
        $this->line("  Open Alerts: API={$totals['api_open']}  DB={$totals['db_open']}  " . ($openTotal === 0 ? '✅' : "⚠️ diff {$openTotal}"));
        $this->line("  Total Alerts in DB: {$totals['db_all']}");

        if ($devTotal <= $limit && $openTotal <= $limit * 2) {
            $this->info("\n✅ Data integrity VERIFIED — counts match within acceptable tolerance.");
        } else {
            $this->warn("\n⚠️ Some differences detected — may be due to alerts changing between sync and verification.");
        }

        return 0;
    }
}
