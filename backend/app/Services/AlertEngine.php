<?php

namespace App\Services;

use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\GeneratedReport;
use App\Models\Integration;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

// Backend Alert Engine
class AlertEngine
{
    protected AlertNotificationService $notificationService;

    public function __construct(AlertNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function evaluateAllRules(): array
    {
        $results = [
            'evaluated' => 0,
            'triggered' => 0,
            'skipped_cooldown' => 0,
            'errors' => 0,
        ];

        $rules = AlertRule::where('is_active', true)->get();

        foreach ($rules as $rule) {
            $results['evaluated']++;

            try {
                if ($rule->isInCooldown()) {
                    $results['skipped_cooldown']++;
                    continue;
                }

                if ($this->evaluateRule($rule)) {
                    $this->triggerAlert($rule);
                    $results['triggered']++;
                }
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error('Alert rule evaluation failed', [
                    'alert_rule_id' => $rule->alert_rule_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function evaluateRule(AlertRule $rule): bool
    {
        $metricValue = $this->getMetricValue($rule->metric_type, $rule->company_id, $rule->condition);

        if ($metricValue === null) {
            return false;
        }

        return $this->checkCondition($metricValue, $rule->condition);
    }

    protected function getMetricValue(string $metricType, int $companyId, array $condition): ?float
    {
        $period = $condition['period'] ?? '1h';
        $since = $this->parsePeriod($period);

        return match ($metricType) {
            'database_connectivity' => $this->checkDatabaseConnectivity(),
            'disk_space' => $this->checkDiskSpace(),
            'integration_sync_failure' => $this->getIntegrationSyncFailureRate($companyId, $since),
            'report_generation_failure' => $this->getReportGenerationFailureRate($companyId, $since),
            'queue_depth' => $this->getQueueDepth(),
            'rate_limit_hit' => $this->getRateLimitHitCount($companyId, $since),
            'security_event' => $this->getSecurityEventCount($companyId, $since),
            'slow_query' => $this->getSlowQueryCount($since),
            default => null,
        };
    }

    protected function checkCondition(float $value, array $condition): bool
    {
        $operator = $condition['operator'] ?? '>';
        $threshold = $condition['threshold'] ?? 0;

        return match ($operator) {
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '==' => $value == $threshold,
            '!=' => $value != $threshold,
            default => false,
        };
    }

    protected function triggerAlert(AlertRule $rule): void
    {
        $alertLog = AlertLog::create([
            'company_id' => $rule->company_id,
            'alert_rule_id' => $rule->alert_rule_id,
            'severity' => $rule->severity,
            'alert_type' => $rule->metric_type,
            'title' => $rule->name,
            'message' => $this->generateAlertMessage($rule),
            'context' => $this->getAlertContext($rule),
            'notification_status' => [],
            'triggered_at' => now(),
        ]);

        $notificationStatus = $this->notificationService->sendAlert($alertLog, $rule);

        $alertLog->update(['notification_status' => $notificationStatus]);

        $rule->update(['last_triggered_at' => now()]);

        Log::info('Alert triggered', [
            'alert_rule_id' => $rule->alert_rule_id,
            'alert_log_id' => $alertLog->alert_log_id,
            'severity' => $rule->severity,
        ]);
    }

    protected function generateAlertMessage(AlertRule $rule): string
    {
        $metricValue = $this->getMetricValue($rule->metric_type, $rule->company_id, $rule->condition);
        $threshold = $rule->condition['threshold'] ?? 0;
        $operator = $rule->condition['operator'] ?? '>';

        return sprintf(
            '%s: Current value is %s (threshold: %s %s)',
            $rule->name,
            $metricValue,
            $operator,
            $threshold
        );
    }

    protected function getAlertContext(AlertRule $rule): array
    {
        return [
            'metric_type' => $rule->metric_type,
            'metric_value' => $this->getMetricValue($rule->metric_type, $rule->company_id, $rule->condition),
            'condition' => $rule->condition,
            'company_id' => $rule->company_id,
        ];
    }

    protected function parsePeriod(string $period): Carbon
    {
        preg_match('/(\d+)([mhd])/', $period, $matches);

        if (count($matches) !== 3) {
            return now()->subHour();
        }

        [$_, $value, $unit] = $matches;

        return match ($unit) {
            'm' => now()->subMinutes((int) $value),
            'h' => now()->subHours((int) $value),
            'd' => now()->subDays((int) $value),
        };
    }

    // ========== Metric Calculation Methods ==========

    protected function checkDatabaseConnectivity(): float
    {
        try {
            DB::connection()->getPdo();
            return 1;  // Connected
        } catch (\Exception $e) {
            return 0;  // Disconnected
        }
    }

    protected function checkDiskSpace(): float
    {
        $path = Storage::path('');
        $freeSpace = disk_free_space($path);
        $totalSpace = disk_total_space($path);

        return ($freeSpace / $totalSpace) * 100;
    }

    protected function getIntegrationSyncFailureRate(int $companyId, Carbon $since): float
    {
        $integrationIds = Integration::where('company_id', $companyId)->pluck('integration_id');

        if ($integrationIds->isEmpty()) {
            return 0;
        }

        $total = SyncLog::whereIn('integration_id', $integrationIds)
            ->where('created_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return 0;
        }

        $failed = SyncLog::whereIn('integration_id', $integrationIds)
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();

        return ($failed / $total) * 100;
    }

    protected function getReportGenerationFailureRate(int $companyId, Carbon $since): float
    {
        $total = GeneratedReport::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return 0;
        }

        $failed = GeneratedReport::where('company_id', $companyId)
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();

        return ($failed / $total) * 100;
    }

    protected function getQueueDepth(): float
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getRateLimitHitCount(int $companyId, Carbon $since): float
    {
        return 0;
    }

    protected function getSecurityEventCount(int $companyId, Carbon $since): float
    {
        return 0;
    }

    protected function getSlowQueryCount(Carbon $since): float
    {
        return 0;
    }
}
