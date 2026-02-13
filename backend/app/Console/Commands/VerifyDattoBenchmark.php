<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\Contracts\IntegrationDataType;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Models\SyncLog;
use App\Services\RateLimiter;
use App\Integrations\Datto\DattoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class VerifyDattoBenchmark extends Command
{
    protected $signature = 'verify:datto-benchmark {--sites=5 : Number of sites to benchmark}';
    protected $description = 'Run full + recurring Datto benchmarks and verify data integrity between them.';

    public function handle(): int
    {
        $limit = (int) $this->option('sites');
        $integration = Integration::where('integration_type', 'Datto')->firstOrFail();

        $this->info("╔══════════════════════════════════════════════╗");
        $this->info("║   Datto Benchmark + Verification Suite       ║");
        $this->info("║   Sites: {$limit}                                  ║");
        $this->info("╚══════════════════════════════════════════════╝\n");

        // ══════════════════════════════════════════════════════════
        // PHASE 1: Full sync benchmark
        // ══════════════════════════════════════════════════════════
        $this->info("━━━ PHASE 1: Full Sync Benchmark ━━━");
        $this->call('benchmark:datto-sync', ['--sites' => $limit]);

        $sync1 = SyncLog::where('integration_id', $integration->integration_id)
            ->orderBy('started_at', 'desc')->first();

        $phase1Records = $sync1->records_synced ?? 0;
        $phase1Duration = $sync1->started_at && $sync1->completed_at
            ? (int) $sync1->started_at->diffInSeconds($sync1->completed_at)
            : 0;

        // Snapshot DB counts after full sync
        $this->line("\nCapturing DB snapshot after full sync...");
        $snapshot1 = $this->captureDbSnapshot($integration, $limit);

        $this->info("Phase 1 Results:");
        $this->line("  Records synced: {$phase1Records}");
        $this->line("  Duration: {$phase1Duration}s");
        $this->line("  DB Devices: {$snapshot1['total_devices']}");
        $this->line("  DB Alerts:  {$snapshot1['total_alerts']}");
        $this->line("  DB Open:    {$snapshot1['open_alerts']}");

        // ══════════════════════════════════════════════════════════
        // PHASE 2: Recurring sync benchmark (no reset)
        // ══════════════════════════════════════════════════════════
        $this->newLine();
        $this->info("━━━ PHASE 2: Recurring Sync Benchmark (no reset) ━━━");
        $this->call('benchmark:datto-sync', ['--sites' => $limit, '--no-reset' => true]);

        $sync2 = SyncLog::where('integration_id', $integration->integration_id)
            ->orderBy('started_at', 'desc')->first();

        $phase2Records = $sync2->records_synced ?? 0;
        $phase2Duration = $sync2->started_at && $sync2->completed_at
            ? (int) $sync2->started_at->diffInSeconds($sync2->completed_at)
            : 0;

        // Snapshot DB counts after recurring sync
        $this->line("\nCapturing DB snapshot after recurring sync...");
        $snapshot2 = $this->captureDbSnapshot($integration, $limit);

        $this->info("Phase 2 Results:");
        $this->line("  Records synced: {$phase2Records}");
        $this->line("  Duration: {$phase2Duration}s");
        $this->line("  DB Devices: {$snapshot2['total_devices']}");
        $this->line("  DB Alerts:  {$snapshot2['total_alerts']}");
        $this->line("  DB Open:    {$snapshot2['open_alerts']}");

        // ══════════════════════════════════════════════════════════
        // PHASE 3: API verification
        // ══════════════════════════════════════════════════════════
        $this->newLine();
        $this->info("━━━ PHASE 3: Live API Verification ━━━");
        $this->call('verify:datto-sync', ['--sites' => $limit]);

        // ══════════════════════════════════════════════════════════
        // FINAL REPORT
        // ══════════════════════════════════════════════════════════
        $this->newLine();
        $this->info("╔══════════════════════════════════════════════╗");
        $this->info("║           FINAL COMPARISON REPORT            ║");
        $this->info("╚══════════════════════════════════════════════╝");
        $this->newLine();

        $this->table(
            ['Metric', 'Full Sync (Phase 1)', 'Recurring Sync (Phase 2)', 'Change'],
            [
                ['Records Synced', $phase1Records, $phase2Records, $this->pctChange($phase1Records, $phase2Records)],
                ['Duration (sec)', $phase1Duration, $phase2Duration, $this->pctChange($phase1Duration, $phase2Duration)],
                ['DB Devices After', $snapshot1['total_devices'], $snapshot2['total_devices'], $this->diffLabel($snapshot1['total_devices'], $snapshot2['total_devices'])],
                ['DB Alerts After', $snapshot1['total_alerts'], $snapshot2['total_alerts'], $this->diffLabel($snapshot1['total_alerts'], $snapshot2['total_alerts'])],
                ['DB Open Alerts', $snapshot1['open_alerts'], $snapshot2['open_alerts'], $this->diffLabel($snapshot1['open_alerts'], $snapshot2['open_alerts'])],
            ]
        );

        // ── Integrity checks ─────────────────────────────────────
        $this->newLine();
        $checks = [];

        // Check 1: No data loss
        $devicesLost = $snapshot2['total_devices'] < $snapshot1['total_devices'];
        $alertsLost = $snapshot2['total_alerts'] < $snapshot1['total_alerts'];
        $checks[] = !$devicesLost && !$alertsLost;
        $this->line((!$devicesLost && !$alertsLost ? '✅' : '❌') . " No data loss: devices and alerts did not decrease after recurring sync");

        // Check 2: Recurring sync was significantly smaller
        $reduction = $phase1Records > 0 ? round((1 - $phase2Records / $phase1Records) * 100, 1) : 0;
        $isSmaller = $phase2Records < $phase1Records * 0.5;
        $checks[] = $isSmaller;
        $this->line(($isSmaller ? '✅' : '❌') . " Record reduction: {$reduction}% fewer records in recurring sync");

        // Check 3: Recurring sync was faster
        $speedup = $phase1Duration > 0 ? round((1 - $phase2Duration / $phase1Duration) * 100, 1) : 0;
        $isFaster = $phase2Duration < $phase1Duration;
        $checks[] = $isFaster;
        $this->line(($isFaster ? '✅' : '❌') . " Speed improvement: {$speedup}% faster");

        // Check 4: Open alert count is stable
        $openStable = abs($snapshot1['open_alerts'] - $snapshot2['open_alerts']) <= 5;
        $checks[] = $openStable;
        $this->line(($openStable ? '✅' : '⚠️') . " Open alert stability: " . abs($snapshot1['open_alerts'] - $snapshot2['open_alerts']) . " difference (alerts may change between syncs)");

        $allPassed = !in_array(false, $checks, true);
        $this->newLine();
        if ($allPassed) {
            $this->info("🎉 ALL CHECKS PASSED — Tiered sync is working correctly!");
        } else {
            $this->warn("⚠️  Some checks did not pass — review the results above.");
        }

        return $allPassed ? 0 : 1;
    }

    /**
     * @return array{total_devices: int, total_alerts: int, open_alerts: int}
     */
    private function captureDbSnapshot(Integration $integration, int $limit): array
    {
        $customerIds = IntegrationCustomer::withoutGlobalScopes()
            ->where('integration_id', $integration->integration_id)
            ->whereNotNull('metadata->last_device_sync_at')
            ->limit($limit)
            ->pluck('integration_customer_id');

        $totalDevices = IntegrationData::withoutGlobalScopes()
            ->where('integration_id', $integration->integration_id)
            ->whereIn('integration_customer_id', $customerIds)
            ->where('data_type', IntegrationDataType::DEVICE_STATUS)
            ->count();

        $totalAlerts = IntegrationData::withoutGlobalScopes()
            ->where('integration_id', $integration->integration_id)
            ->whereIn('integration_customer_id', $customerIds)
            ->where('data_type', IntegrationDataType::ALERT)
            ->count();

        $openAlerts = IntegrationData::withoutGlobalScopes()
            ->where('integration_id', $integration->integration_id)
            ->whereIn('integration_customer_id', $customerIds)
            ->where('data_type', IntegrationDataType::ALERT)
            ->whereRaw("data_payload::jsonb->>'resolved' = 'false'")
            ->count();

        return [
            'total_devices' => $totalDevices,
            'total_alerts' => $totalAlerts,
            'open_alerts' => $openAlerts,
        ];
    }

    private function pctChange(int $before, int $after): string
    {
        if ($before === 0) return 'N/A';
        $pct = round((($after - $before) / $before) * 100, 1);
        $sign = $pct >= 0 ? '+' : '';
        return "{$sign}{$pct}%";
    }

    private function diffLabel(int $before, int $after): string
    {
        $diff = $after - $before;
        if ($diff === 0) return '✅ unchanged';
        $sign = $diff > 0 ? '+' : '';
        return "{$sign}{$diff}";
    }
}
