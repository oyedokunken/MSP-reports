<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Models\Integration;
use App\Models\GeneratedReport;
use App\Models\SyncLog;
use Carbon\Carbon;

class HealthCheckController extends Controller
{
    public function index()
    {
        $checks = [
            'api' => $this->checkApi(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        $allHealthy = collect($checks)
            ->filter(function ($check, $key) {
                if ($key === 'redis' && $this->isRedisRequired() === false) {
                    return false;
                }
                return true;
            })
            ->every(fn($check) => $check['status'] === 'healthy');

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    public function status(Request $request)
    {
        $authUser = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'system' => $this->getSystemStatus(),
                'integrations' => $this->getIntegrationStatus($authUser->company_id),
                'reports' => $this->getReportStatus($authUser->company_id),
                'queue' => $this->getQueueStatus(),
            ]
        ]);
    }

    public function integrationMetrics(Request $request)
    {
        $authUser = $request->user();
        $days = $request->input('days', 7);

        $startDate = now()->subDays($days);

        $integrations = Integration::where('company_id', $authUser->company_id)
            ->with(['syncLogs' => function ($query) use ($startDate) {
                $query->where('started_at', '>=', $startDate);
            }])
            ->get()
            ->map(function ($integration) {
                $logs = $integration->syncLogs;
                $totalSyncs = $logs->count();
                $successfulSyncs = $logs->where('status', 'success')->count();
                $failedSyncs = $logs->where('status', 'failed')->count();

                return [
                    'integration_id' => $integration->integration_id,
                    'integration_type' => $integration->integration_type,
                    'display_name' => $integration->display_name,
                    'status' => $integration->status,
                    'last_sync_at' => $integration->last_sync_at,
                    'last_sync_status' => $integration->last_sync_status,
                    'metrics' => [
                        'total_syncs' => $totalSyncs,
                        'successful_syncs' => $successfulSyncs,
                        'failed_syncs' => $failedSyncs,
                        'success_rate' => $totalSyncs > 0 ? round(($successfulSyncs / $totalSyncs) * 100, 2) : 0,
                        'avg_records_synced' => $logs->avg('records_synced') ?? 0,
                        'total_rate_limit_hits' => $logs->sum('rate_limit_hits'),
                    ],
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'period_days' => $days,
                'integrations' => $integrations,
            ]
        ]);
    }

    public function reportMetrics(Request $request)
    {
        $authUser = $request->user();
        $days = $request->input('days', 7);

        $startDate = now()->subDays($days);

        $reports = GeneratedReport::where('company_id', $authUser->company_id)
            ->where('generated_at', '>=', $startDate)
            ->get();

        $totalReports = $reports->count();
        $completedReports = $reports->where('status', 'completed')->count();
        $failedReports = $reports->where('status', 'failed')->count();
        $deliveredReports = $reports->where('delivered', true)->count();
        $failedDeliveries = $reports->where('delivered', false)->whereNotNull('error_message')->count();

        $avgGenerationTime = $reports
            ->filter(fn($r) => $r->status === 'completed')
            ->map(function ($report) {
                if ($report->generated_at && $report->created_at) {
                    return $report->generated_at->diffInSeconds($report->created_at);
                }
                return null;
            })
            ->filter()
            ->avg();

        return response()->json([
            'status' => 'success',
            'data' => [
                'period_days' => $days,
                'metrics' => [
                    'total_reports' => $totalReports,
                    'completed_reports' => $completedReports,
                    'failed_reports' => $failedReports,
                    'success_rate' => $totalReports > 0 ? round(($completedReports / $totalReports) * 100, 2) : 0,
                    'delivered_reports' => $deliveredReports,
                    'failed_deliveries' => $failedDeliveries,
                    'delivery_rate' => $completedReports > 0 ? round(($deliveredReports / $completedReports) * 100, 2) : 0,
                    'avg_generation_time_seconds' => round($avgGenerationTime ?? 0, 2),
                ],
                'recent_failures' => $reports
                    ->where('status', 'failed')
                    ->sortByDesc('generated_at')
                    ->take(5)
                    ->map(fn($r) => [
                        'generated_report_id' => $r->generated_report_id,
                        'customer_id' => $r->customer_id,
                        'generated_at' => $r->generated_at,
                        'error_message' => $r->error_message,
                    ])
                    ->values(),
            ]
        ]);
    }

    public function monthlyReportAnalytics(Request $request)
    {
        $authUser = $request->user();
        $months = $request->input('months', 6);

        $startDate = now()->subMonths($months)->startOfMonth();

        // Get reports grouped by month (PostgreSQL compatible)
        $reports = GeneratedReport::where('company_id', $authUser->company_id)
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

        return response()->json([
            'status' => 'success',
            'data' => [
                'period_months' => $months,
                'monthly_data' => $monthlyData,
            ]
        ]);
    }

    public function queueMetrics(Request $request)
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $pendingJobs = DB::table('jobs')->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'workers_status' => 'running',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch queue metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods

    private function checkApi(): array
    {
        return [
            'status' => 'healthy',
            'message' => 'API is responding',
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->getDatabaseName();
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        if ($this->isRedisRequired() === false) {
            return [
                'status' => 'healthy',
                'message' => 'Redis not required for current configuration',
            ];
        }

        try {
            Redis::ping();
            
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function isRedisRequired(): bool
    {
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');
        $sessionDriver = config('session.driver');

        return $cacheDriver === 'redis' || $queueDriver === 'redis' || $sessionDriver === 'redis';
    }

    private function checkStorage(): array
    {
        try {
            $disk = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $percentFree = ($disk / $total) * 100;

            $status = $percentFree < 10 ? 'critical' : ($percentFree < 20 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'message' => sprintf('%.2f%% disk space available', $percentFree),
                'free_bytes' => $disk,
                'total_bytes' => $total,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function getSystemStatus(): array
    {
        return [
            'api' => $this->checkApi(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'uptime' => $this->getUptime(),
        ];
    }

    private function getIntegrationStatus(int $companyId): array
    {
        $integrations = Integration::where('company_id', $companyId)->get();
        
        $totalIntegrations = $integrations->count();
        $activeIntegrations = $integrations->where('status', 'active')->count();
        $errorIntegrations = $integrations->where('status', 'error')->count();

        return [
            'total' => $totalIntegrations,
            'active' => $activeIntegrations,
            'error' => $errorIntegrations,
            'health' => $errorIntegrations === 0 ? 'healthy' : ($errorIntegrations === $totalIntegrations ? 'critical' : 'warning'),
        ];
    }

    private function getReportStatus(int $companyId): array
    {
        $last24Hours = now()->subHours(24);
        
        $recentReports = GeneratedReport::where('company_id', $companyId)
            ->where('generated_at', '>=', $last24Hours)
            ->get();

        $total = $recentReports->count();
        $completed = $recentReports->where('status', 'completed')->count();
        $failed = $recentReports->where('status', 'failed')->count();

        return [
            'last_24_hours' => [
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            ],
            'health' => $failed === 0 ? 'healthy' : ($failed > $completed ? 'critical' : 'warning'),
        ];
    }

    private function getQueueStatus(): array
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            return [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'health' => $failedJobs > 10 ? 'warning' : 'healthy',
            ];
        } catch (\Exception $e) {
            return [
                'health' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getUptime(): array
    {
        // This is a placeholder - would need to track actual app start time
        return [
            'message' => 'Uptime tracking not implemented',
        ];
    }
}
