<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\GeneratedReport;
use App\Models\SyncLog;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

final class DashboardController extends Controller
{
    /**
     * Get aggregated dashboard summary data
     * Combines multiple API calls into a single optimized endpoint
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSummary(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $companyId = $authUser->company_id;

        // Cache for 30 seconds to reduce database load
        $cacheKey = "dashboard_summary_{$companyId}";

        $data = Cache::remember($cacheKey, 30, function () use ($companyId) {
            return [
                'stats' => $this->getStats($companyId),
                'integrations' => $this->getIntegrations($companyId),
                'upcomingSchedules' => $this->getUpcomingSchedules($companyId),
                'recentActivity' => $this->getRecentActivity($companyId),
                'charts' => [
                    'monthlyReports' => $this->getMonthlyReports($companyId, 6),
                    'activityTrends' => $this->getActivityTrends($companyId, 7),
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ])->header('Cache-Control', 'max-age=30, stale-while-revalidate=60');
    }

    /**
     * Get dashboard statistics
     * 
     * @param int $companyId
     * @return array{totalCustomers: int, unmappedCustomers: int, templates: array{total: int, customized: int}, users: array{total: int, active: int}}
     */
    private function getStats(int $companyId): array
    {
        // Optimized: Single query for customer counts
        $customerStats = DB::table('customers')
            ->where('company_id', $companyId)
            ->selectRaw('COUNT(*) as total_customers')
            ->first();

        $unmappedCount = IntegrationCustomer::whereNull('customer_id')
            ->whereHas('integration', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->count();

        // Total integration customers (imported from all integrations)
        $importedCustomers = IntegrationCustomer::whereHas('integration', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->count();

        // Optimized: Single query for template counts
        $templateStats = ReportTemplate::where('company_id', $companyId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN customer_id IS NOT NULL THEN 1 END) as customized')
            ->first();

        // Optimized: Single query for user counts
        $userStats = User::where('company_id', $companyId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN status = 'active' THEN 1 END) as active")
            ->first();

        return [
            'totalCustomers' => (int) ($customerStats->total_customers ?? 0),
            'unmappedCustomers' => $unmappedCount,
            'importedCustomers' => $importedCustomers,
            'templates' => [
                'total' => (int) ($templateStats->total ?? 0),
                'customized' => (int) ($templateStats->customized ?? 0),
            ],
            'users' => [
                'total' => (int) ($userStats->total ?? 0),
                'active' => (int) ($userStats->active ?? 0),
            ],
        ];
    }

    /**
     * Get top integrations with their status
     * 
     * @param int $companyId
     * @return array<int, array{id: int, name: string, type: string, status: string, lastSync: string, syncStatus: string, customers: int, isSourceOfTruth: bool}>
     */
    private function getIntegrations(int $companyId): array
    {
        $integrations = Integration::where('company_id', $companyId)
            ->with([
                'syncLogs' => function ($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->withCount(['integrationCustomers as customers_count'])
            ->orderByRaw("CASE WHEN is_source_of_truth THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function (Integration $integration) {
                $lastSync = $integration->syncLogs->first();

                return [
                    'id' => $integration->integration_id,
                    'name' => $integration->display_name,
                    'type' => $integration->integration_type,
                    'status' => $integration->status,
                    'lastSync' => $integration->last_sync_at
                        ? $integration->last_sync_at->diffForHumans()
                        : 'Never',
                    'syncStatus' => $integration->last_sync_status ?? 'pending',
                    'customers' => $integration->customers_count ?? 0,
                    'isSourceOfTruth' => (bool) $integration->is_source_of_truth,
                ];
            });

        return $integrations->toArray();
    }

    /**
     * Get upcoming scheduled reports
     * 
     * @param int $companyId
     * @return array<int, array{id: int, name: string, customer: string, nextRun: string, status: string}>
     */
    private function getUpcomingSchedules(int $companyId): array
    {
        $schedules = ReportSchedule::where('company_id', $companyId)
            ->where('active', true)
            ->whereNotNull('next_scheduled_at')
            ->with('customer:customer_id,canonical_name')
            ->orderBy('next_scheduled_at', 'asc')
            ->limit(5)
            ->get()
            ->map(function (ReportSchedule $schedule) {
                return [
                    'id' => $schedule->report_schedule_id,
                    'name' => $schedule->report_type,
                    'customer' => $schedule->customer->canonical_name ?? 'Unknown Customer',
                    'nextRun' => $schedule->next_scheduled_at
                        ? $schedule->next_scheduled_at->format('Y-m-d H:i:s')
                        : 'Not scheduled',
                    'status' => 'scheduled',
                ];
            });

        return $schedules->toArray();
    }

    /**
     * Get recent activity from audit logs and notifications
     * 
     * @param int $companyId
     * @return array<int, array{id: int, type: string, title: string, time: string, severity: string}>
     */
    private function getRecentActivity(int $companyId): array
    {
        // Get recent sync activities
        $recentSyncs = SyncLog::whereHas('integration', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
            ->with('integration:integration_id,display_name')
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(function (SyncLog $log) {
                $severity = match ($log->status) {
                    'success' => 'green',
                    'failed' => 'red',
                    default => 'yellow',
                };

                return [
                    'id' => $log->sync_id,
                    'type' => 'sync',
                    'title' => "{$log->integration->display_name} sync {$log->status}",
                    'time' => $log->started_at->diffForHumans(),
                    'severity' => $severity,
                ];
            });

        // Get recent audit log activities
        $recentAudits = AuditLog::where('company_id', $companyId)
            ->whereIn('event_type', ['user_login', 'integration_added', 'report_generated'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (AuditLog $log) {
                $severity = match ($log->event_type) {
                    'user_login' => 'green',
                    'integration_added' => 'green',
                    'report_generated' => 'green',
                    default => 'yellow',
                };

                return [
                    'id' => $log->audit_log_id,
                    'type' => 'alert',
                    'title' => $log->description ?? $log->event_type,
                    'time' => $log->created_at->diffForHumans(),
                    'severity' => $severity,
                ];
            });

        // Merge and sort by most recent
        $activities = $recentSyncs->concat($recentAudits)
            ->sortByDesc(function ($activity) {
                // Convert time string back to comparable format
                return $activity['id'];
            })
            ->take(10)
            ->values();

        return $activities->toArray();
    }

    /**
     * Get monthly report analytics
     * 
     * @param int $companyId
     * @param int $months
     * @return array<int, array{date: string, count: int}>
     */
    private function getMonthlyReports(int $companyId, int $months = 6): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        // Get reports grouped by month (PostgreSQL compatible)
        $reports = GeneratedReport::where('company_id', $companyId)
            ->where('generated_at', '>=', $startDate)
            ->select(
                DB::raw("TO_CHAR(generated_at, 'YYYY-MM') as month"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Create array with all months in range (fill gaps with 0)
        $monthlyData = [];
        $currentDate = $startDate->copy();
        $endDate = now();

        while ($currentDate <= $endDate) {
            $monthKey = $currentDate->format('Y-m');
            $monthLabel = $currentDate->format('M Y');

            $reportCount = $reports->firstWhere('month', $monthKey);

            $monthlyData[] = [
                'date' => $monthLabel,
                'count' => $reportCount ? $reportCount->count : 0,
            ];

            $currentDate->addMonth();
        }

        return $monthlyData;
    }

    /**
     * Get activity trends for the last N days
     * 
     * @param int $companyId
     * @param int $days
     * @return array<int, array{date: string, syncs: int, failed_syncs: int, rate_limits: int, reports: int, failed_reports: int, logins: int}>
     */
    private function getActivityTrends(int $companyId, int $days = 7): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Fetch sync activity data
        $syncCounts = SyncLog::whereHas('integration', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
            ->whereBetween('started_at', [$startDate, $endDate])
            ->select(
                DB::raw("started_at::date as date"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('SUM(rate_limit_hits) as rate_limits')
            )
            ->groupBy(DB::raw("started_at::date"))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fetch report activity data
        $reportCounts = GeneratedReport::where('company_id', $companyId)
            ->whereBetween('generated_at', [$startDate, $endDate])
            ->select(
                DB::raw("generated_at::date as date"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            )
            ->groupBy(DB::raw("generated_at::date"))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Fetch login activity data
        $userLogins = AuditLog::where('company_id', $companyId)
            ->where('event_type', 'user_login')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("created_at::date as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw("created_at::date"))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Build activity data array with all dates (fill gaps with 0)
        $activityData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $syncData = $syncCounts->get($dateKey);
            $reportData = $reportCounts->get($dateKey);
            $loginData = $userLogins->get($dateKey);

            $activityData[] = [
                'date' => $currentDate->format('M j'),
                'syncs' => (int) ($syncData->total ?? 0),
                'failed_syncs' => (int) ($syncData->failed ?? 0),
                'rate_limits' => (int) ($syncData->rate_limits ?? 0),
                'reports' => (int) ($reportData->total ?? 0),
                'failed_reports' => (int) ($reportData->failed ?? 0),
                'logins' => (int) ($loginData->count ?? 0),
            ];

            $currentDate->addDay();
        }

        return $activityData;
    }
}
