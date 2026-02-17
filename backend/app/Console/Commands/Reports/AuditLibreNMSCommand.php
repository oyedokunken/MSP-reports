<?php

declare(strict_types=1);

namespace App\Console\Commands\Reports;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AuditLibreNMSCommand extends Command
{
    protected $signature = 'reports:audit-librenms
                            {customer_id : The ID of the customer}
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--frequency=monthly : daily, weekly, monthly}
                            {--report-type=librenms_summary : librenms_summary or librenms_device_report}
                            {--skip-generate : Skip report generation and use last existing report}';

    protected $description = 'Audit LibreNMS report data by generating a fresh report then comparing Live API vs Database vs Report';

    public function handle(): int
    {
        $customerId = (int) $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer #{$customerId} not found.");
            return 1;
        }

        $this->info("=== Auditing LibreNMS: {$customer->canonical_name} ===");
        $this->newLine();

        // Find LibreNMS integration
        $integration = Integration::where('company_id', $customer->company_id)
            ->where('integration_type', 'LibreNMS')
            ->first();

        if (!$integration) {
            $this->error("No LibreNMS integration found for this company.");
            return 1;
        }

        // Get customer's mapped LibreNMS groups
        $integrationCustomers = IntegrationCustomer::query()
            ->where('integration_id', $integration->integration_id)
            ->whereIn('integration_customer_id', function ($query) use ($customerId) {
                $query->select('integration_customer_id')
                    ->from('customer_mappings')
                    ->where('customer_id', $customerId);
            })
            ->get();

        $customerGroupIds = $integrationCustomers->map(function ($ic) {
            return str_replace('group_', '', (string) $ic->external_id);
        })->toArray();

        $this->info("    Integration: #{$integration->integration_id} ({$integration->display_name})");
        $this->info("    Mapped Groups: " . ($integrationCustomers->isEmpty() ? 'None (will show all devices)' : $integrationCustomers->count()));
        foreach ($integrationCustomers as $ic) {
            $this->line("      - {$ic->name} (ext: {$ic->external_id})");
        }

        // Determine timezone and date range
        $reportType = $this->option('report-type');
        $schedule = $customer->reportSchedules()->where('report_type', $reportType)->first();
        $timezone = $schedule ? ($schedule->timezone ?: config('app.timezone')) : config('app.timezone');
        $frequency = $this->option('frequency') ?: ($schedule?->frequency ?: 'monthly');
        $this->line("    Using Timezone: {$timezone}");

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
        $this->newLine();

        // ── Step 1: Generate a fresh report ──
        $generatedReport = null;
        if (!$this->option('skip-generate')) {
            $this->comment("0/3 Generating fresh LibreNMS report via reports:generate-manual...");
            try {
                $exitCode = Artisan::call('reports:generate-manual', [
                    'customer_id' => $customerId,
                    'report_type' => $reportType,
                    '--start' => $startDate->format('Y-m-d'),
                    '--end' => $endDate->format('Y-m-d'),
                    '--frequency' => $frequency,
                    '--no-deliver' => true,
                ]);
                $this->line(Artisan::output());

                if ($exitCode === 0) {
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

        if (!$generatedReport) {
            $this->comment("    Looking for last existing LibreNMS report...");
            $generatedReport = GeneratedReport::withoutGlobalScopes()
                ->where('customer_id', $customerId)
                ->where('status', 'completed')
                ->when($schedule, fn($q) => $q->where('report_schedule_id', $schedule->report_schedule_id))
                ->orderBy('generated_report_id', 'desc')
                ->first();

            if ($generatedReport) {
                $this->info("    Using existing report: #{$generatedReport->generated_report_id}");
            } else {
                $this->warn("    No existing LibreNMS report found. Report column will show N/A.");
            }
        }

        // ── Step 2: Fetch Live API Data ──
        $this->comment("1/3 Fetching Live Data from LibreNMS API...");
        $liveDevices = 'N/A';
        $liveOnline = 'N/A';
        $liveOffline = 'N/A';
        $liveGroups = 'N/A';
        $liveAvgUptime30d = 'N/A';
        $apiFail = false;

        try {
            $rateLimiter = app(RateLimiter::class);
            $client = new \App\Integrations\LibreNMS\LibreNMSClient(
                $integration->credentials,
                $rateLimiter,
                $integration
            );

            // Get all devices
            $apiDevices = $client->getAllDevices();
            $this->line("      - Total devices from API: " . count($apiDevices));

            // Get device groups
            $apiGroups = $client->getDeviceGroups();
            $liveGroups = count($apiGroups);
            $this->line("      - Device groups: {$liveGroups}");

            // Filter devices by customer groups if mapped
            if (!empty($customerGroupIds)) {
                // Build device-to-group map
                $deviceGroupMap = [];
                foreach ($apiGroups as $group) {
                    $groupId = (string) ($group['id'] ?? '');
                    if (!in_array($groupId, $customerGroupIds)) {
                        continue;
                    }
                    try {
                        $devicesInGroup = $client->getDevicesInGroup((int) $groupId);
                        foreach ($devicesInGroup as $dev) {
                            $deviceGroupMap[$dev['device_id']] = true;
                        }
                    } catch (\Exception $e) {
                        $this->warn("      - Failed to get devices for group {$groupId}: " . $e->getMessage());
                    }
                }

                $filteredDevices = array_filter($apiDevices, function ($d) use ($deviceGroupMap) {
                    return isset($deviceGroupMap[$d['device_id']]);
                });
                $this->line("      - Devices matching customer groups: " . count($filteredDevices));
            } else {
                $filteredDevices = $apiDevices;
                $this->line("      - No group filter (showing all devices)");
            }

            $liveDevices = count($filteredDevices);
            $liveOnline = count(array_filter($filteredDevices, fn($d) => ($d['status'] ?? 0) == 1 && ($d['disabled'] ?? 0) == 0));
            $liveOffline = $liveDevices - $liveOnline;

            $this->line("      - Online: {$liveOnline}, Offline: {$liveOffline}");

            // Get availability for filtered devices
            $filteredDeviceIds = array_column($filteredDevices, 'device_id');
            if (!empty($filteredDeviceIds)) {
                $availBatch = $client->getDeviceAvailabilityBatch($filteredDeviceIds);
                $uptimes30d = [];
                foreach ($availBatch as $deviceId => $periods) {
                    foreach ($periods as $period) {
                        if (($period['duration'] ?? 0) == 2592000) { // 30 days
                            $uptimes30d[] = floatval($period['availability_perc'] ?? 0);
                            break;
                        }
                    }
                }
                $liveAvgUptime30d = !empty($uptimes30d) ? round(array_sum($uptimes30d) / count($uptimes30d), 2) : 0;
                $this->line("      - Avg 30d Uptime: {$liveAvgUptime30d}% (from " . count($uptimes30d) . " devices)");
            }
        } catch (\Exception $e) {
            $this->warn("      - API Failed: " . $e->getMessage());
            $apiFail = true;
        }

        // ── Step 3: Fetch Database Data ──
        $this->comment("2/3 Querying Local Database Cache...");

        // Use efficient query: find the latest sync batch (records within 60s of max timestamp)
        // The sync stores devices in chunks with slightly different timestamps
        $latestTimestamp = IntegrationData::where('company_id', $customer->company_id)
            ->where('integration_id', $integration->integration_id)
            ->where('data_type', 'device_details')
            ->where('data_timestamp', '>=', now()->subDays(7))
            ->max('data_timestamp');

        $dbDeviceDetails = collect();
        $dbUptimeRecords = collect();
        $dbAvailRecords = collect();

        if ($latestTimestamp) {
            $batchCutoff = Carbon::parse($latestTimestamp)->subSeconds(60);

            $dbDeviceDetails = IntegrationData::where('company_id', $customer->company_id)
                ->where('integration_id', $integration->integration_id)
                ->where('data_type', 'device_details')
                ->where('data_timestamp', '>=', $batchCutoff)
                ->get()
                ->unique(fn($r) => $r->data_payload['device_id'] ?? 0);

            $dbUptimeRecords = IntegrationData::where('company_id', $customer->company_id)
                ->where('integration_id', $integration->integration_id)
                ->where('data_type', 'device_uptime')
                ->where('data_timestamp', '>=', $batchCutoff)
                ->get()
                ->unique(fn($r) => $r->data_payload['device_id'] ?? 0)
                ->keyBy(fn($r) => $r->data_payload['device_id'] ?? 0);

            $dbAvailRecords = IntegrationData::where('company_id', $customer->company_id)
                ->where('integration_id', $integration->integration_id)
                ->where('data_type', 'device_availability')
                ->where('data_timestamp', '>=', now()->subDays(7))
                ->orderBy('data_timestamp', 'desc')
                ->get()
                ->unique(fn($r) => $r->data_payload['device_id'] ?? 0)
                ->keyBy(fn($r) => $r->data_payload['device_id'] ?? 0);

            $this->line("    Latest sync: {$latestTimestamp} ({$dbDeviceDetails->count()} unique devices)");
        } else {
            $this->warn("    No device_details found in last 7 days.");
        }

        // Filter DB devices by customer groups
        $dbDevices = [];
        foreach ($dbDeviceDetails as $record) {
            $payload = $record->data_payload;
            $deviceId = $payload['device_id'] ?? null;
            $deviceGroups = $payload['groups'] ?? [];

            if (!empty($customerGroupIds)) {
                $matchesCustomer = false;
                foreach ($deviceGroups as $group) {
                    $groupId = is_array($group) ? ($group['id'] ?? null) : $group;
                    if (in_array((string) $groupId, $customerGroupIds)) {
                        $matchesCustomer = true;
                        break;
                    }
                }
                if (!$matchesCustomer) continue;
            }

            $uptimePayload = $dbUptimeRecords[$deviceId]->data_payload ?? [];
            $availability = $dbAvailRecords[$deviceId]->data_payload['availability'] ?? [];

            $status = $payload['status'] ?? ($uptimePayload['status'] ?? 0);
            $disabled = $payload['disabled'] ?? ($uptimePayload['disabled'] ?? 0);
            $isOnline = $status == 1 && $disabled == 0;

            $dbDevices[] = [
                'device_id' => $deviceId,
                'name' => $payload['sysName'] ?? $payload['hostname'] ?? 'Unknown',
                'status' => $isOnline ? 'Online' : 'Offline',
                'uptime_30d' => $availability['30d']['uptime_percent'] ?? 0,
            ];
        }

        $dbTotalDevices = count($dbDevices);
        $dbOnline = count(array_filter($dbDevices, fn($d) => $d['status'] === 'Online'));
        $dbOffline = $dbTotalDevices - $dbOnline;
        $dbAvgUptime30d = $dbTotalDevices > 0 ? round(collect($dbDevices)->avg('uptime_30d'), 2) : 0;

        // ── Step 4: Extract Report Data ──
        $this->comment("3/3 Checking Generated Report...");
        $reportDevices = 'N/A';
        $reportOnline = 'N/A';
        $reportOffline = 'N/A';
        $reportAvgUptime30d = 'N/A';
        $reportId = 'None';

        if ($generatedReport && $generatedReport->report_data) {
            $rd = $generatedReport->report_data;
            $reportId = (string) $generatedReport->generated_report_id;
            $summary = $rd['summary'] ?? [];
            $reportDevices = $summary['total_devices'] ?? 'N/A';
            $reportOnline = $summary['online_devices'] ?? 'N/A';
            $reportOffline = $summary['offline_devices'] ?? 'N/A';
            $reportAvgUptime30d = $summary['avg_uptime_30d'] ?? 'N/A';
        }

        // ── Results Tables ──
        $this->newLine();
        $this->info("Device Count Comparison:");
        $this->table(
            ['Source', 'Total Devices', 'Online', 'Offline', 'Status'],
            [
                ['Live LibreNMS API', $liveDevices, $liveOnline, $liveOffline, 'Verified Source'],
                ['Local Database', $dbTotalDevices, $dbOnline, $dbOffline, $this->matchStatus($dbTotalDevices, $liveDevices)],
                ["Report #{$reportId}", $reportDevices, $reportOnline, $reportOffline, $this->matchStatus($reportDevices, $dbTotalDevices, 'DB')],
            ]
        );

        $this->newLine();
        $this->info("Availability Comparison (30-day avg):");
        $this->table(
            ['Source', 'Avg Uptime 30d', 'Status'],
            [
                ['Live LibreNMS API', $liveAvgUptime30d !== 'N/A' ? "{$liveAvgUptime30d}%" : 'N/A', 'Verified Source'],
                ['Local Database', "{$dbAvgUptime30d}%", $this->matchStatus($dbAvgUptime30d, $liveAvgUptime30d)],
                ["Report #{$reportId}", $reportAvgUptime30d !== 'N/A' ? "{$reportAvgUptime30d}%" : 'N/A', $this->matchStatus($reportAvgUptime30d, $dbAvgUptime30d, 'DB')],
            ]
        );

        // Device-level detail table (top 15)
        if (!empty($dbDevices)) {
            $this->newLine();
            $this->info("Device Details (from DB, top 15):");
            $deviceRows = collect($dbDevices)
                ->sortBy('uptime_30d')
                ->take(15)
                ->map(fn($d) => [
                    $d['name'],
                    $d['status'],
                    $d['uptime_30d'] . '%',
                ])
                ->values()
                ->toArray();

            $this->table(['Device', 'Status', '30d Uptime'], $deviceRows);
        }

        // ── Summary ──
        $this->newLine();
        $mismatches = [];
        if ($liveDevices !== 'N/A' && $dbTotalDevices != $liveDevices) {
            $mismatches[] = "Devices (API: {$liveDevices}, DB: {$dbTotalDevices})";
        }
        if ($liveOnline !== 'N/A' && $dbOnline != $liveOnline) {
            $mismatches[] = "Online (API: {$liveOnline}, DB: {$dbOnline})";
        }
        if ($liveAvgUptime30d !== 'N/A' && abs((float)$dbAvgUptime30d - (float)$liveAvgUptime30d) > 0.5) {
            $mismatches[] = "Avg Uptime 30d (API: {$liveAvgUptime30d}%, DB: {$dbAvgUptime30d}%)";
        }

        if (empty($mismatches)) {
            $this->info("✅ All data sources match.");
        } else {
            $this->warn("⚠️ Mismatches detected:");
            foreach ($mismatches as $m) {
                $this->line("   - {$m}");
            }
            $this->newLine();
            $this->line("Recommended actions:");
            $this->line("1. Run a sync for the LibreNMS integration to refresh database cache.");
            $this->line("2. Device status changes in real-time; small differences are expected if a sync hasn't run recently.");
            $this->line("3. Availability percentages may differ slightly due to timing of data collection.");
        }

        return 0;
    }

    private function matchStatus(mixed $value, mixed $compareTo, string $label = 'API'): string
    {
        if ($value === 'N/A' || $compareTo === 'N/A') {
            return '⚠️ N/A';
        }

        // For numeric comparisons, allow small tolerance for uptime percentages
        if (is_numeric($value) && is_numeric($compareTo)) {
            if (abs((float)$value - (float)$compareTo) <= 0.5) {
                return "✅ MATCH ({$label})";
            }
        }

        return $value == $compareTo ? "✅ MATCH ({$label})" : "❌ MISMATCH";
    }
}
