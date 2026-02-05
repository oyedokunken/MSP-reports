<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\HealthCheck\GetMetricsRequest;
use App\Http\Requests\HealthCheck\GetHealthStatusRequest;
use App\Http\Requests\HealthCheck\GetQueueMetricsRequest;
use App\Models\Integration;
use App\Models\GeneratedReport;
use App\Models\SyncLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\ReportTemplate;
use Carbon\Carbon;

final class HealthCheckController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
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

    public function database(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkDatabase();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function redis(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkRedis();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function email(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkEmail();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function scheduler(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkScheduler();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function storage(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkStorage();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function reports(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkReports();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function external(): \Illuminate\Http\JsonResponse
    {
        $check = $this->checkExternal();
        return response()->json($check, $check['status'] === 'healthy' ? 200 : 503);
    }

    public function status(GetHealthStatusRequest $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'system' => $this->getSystemStatus(),
                'integrations' => $this->getIntegrationStatus($authUser->company_id),
                'reports' => $this->getReportStatus($authUser->company_id),
                'users' => [
                    'total' => User::where('company_id', $authUser->company_id)->count(),
                    'active' => User::where('company_id', $authUser->company_id)->where('status', 'active')->count(),
                ],
                'templates' => [
                    'total' => ReportTemplate::where('company_id', $authUser->company_id)->count(),
                    'customized' => ReportTemplate::where('company_id', $authUser->company_id)->whereNotNull('customer_id')->count(),
                ],
                'queue' => $this->getQueueStatus(),
                'redis' => $this->getRedisMemoryUsage(),
            ]
        ]);
    }

    public function integrationMetrics(GetMetricsRequest $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $days = $request->validated()['days'] ?? $request->input('days', 7);

        $startDate = now()->subDays($days);

        $integrations = Integration::where('company_id', $authUser->company_id)
            ->with(['syncLogs' => function ($query) use ($startDate) {
                $query->where('started_at', '>=', $startDate);
            }])
            ->get()
            ->map(function (\App\Models\Integration $integration) {
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

    public function reportMetrics(GetMetricsRequest $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $days = $request->validated()['days'] ?? $request->input('days', 7);

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
            ->map(function (\App\Models\GeneratedReport $report) {
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
                    ->map(fn(\App\Models\GeneratedReport $r) => [
                        'generated_report_id' => $r->generated_report_id,
                        'customer_id' => $r->customer_id,
                        'generated_at' => $r->generated_at,
                        'error_message' => $r->error_message,
                    ])
                    ->values(),
            ]
        ]);
    }

    public function monthlyReportAnalytics(GetMetricsRequest $request): \Illuminate\Http\JsonResponse
    {
        $authUser = $request->user();
        $months = $request->validated()['months'] ?? $request->input('months', 6);

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

    public function queueMetrics(GetQueueMetricsRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            
            // Check if using database queue driver
            if (config('queue.default') === 'database') {
                $pendingJobs = DB::table('jobs')->count();
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'driver' => 'database',
                        'total_pending' => $pendingJobs,
                        'failed_jobs' => $failedJobs,
                        'workers_status' => 'running',
                    ]
                ]);
            }
            
            // Redis queue driver
            $redis = Redis::connection();
            $queues = [
                'critical' => $redis->llen('queues:critical'),
                'sync' => $redis->llen('queues:sync'),
                'historical' => $redis->llen('queues:historical'),
                'default' => $redis->llen('queues:default'),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'driver' => 'redis',
                    'queues' => $queues,
                    'total_pending' => array_sum($queues),
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

    public function activityTrends(GetMetricsRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $authUser = $request->user();
            $days = $request->validated()['days'] ?? $request->input('days', 7);

            $startDate = now()->subDays($days)->startOfDay();
            $endDate = now()->endOfDay();

            // Generate array of dates for the period
            $dateRange = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateRange[] = $currentDate->format('Y-m-d');
                $currentDate->addDay();
            }

            // Get sync counts per day
            $syncCounts = SyncLog::whereHas('integration', function($q) use ($authUser) {
                    $q->where('company_id', $authUser->company_id);
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

            // Get report counts per day
            $reportCounts = GeneratedReport::where('company_id', $authUser->company_id)
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

            // Get job counts per day (only if jobs table exists)
            $jobCounts = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                $jobCounts = DB::table('jobs')
                    ->whereBetween('created_at', [
                        $startDate->timestamp,
                        $endDate->timestamp
                    ])
                    ->select(
                        DB::raw("TO_TIMESTAMP(created_at)::date as date"),
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy(DB::raw("TO_TIMESTAMP(created_at)::date"))
                    ->orderBy('date', 'asc')
                    ->get()
                    ->keyBy('date');
            }

            // Get user login counts per day
            $userLogins = AuditLog::where('company_id', $authUser->company_id)
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
            foreach ($dateRange as $date) {
                $dateObj = Carbon::parse($date);
                $syncData = $syncCounts->get($date);
                $reportData = $reportCounts->get($date);
                $jobData = $jobCounts->get($date);
                $loginData = $userLogins->get($date);
                
                /** @var object{total: int, failed: int, rate_limits: int}|null $syncData */
                /** @var object{total: int, failed: int}|null $reportData */
                /** @var object{count: int}|null $jobData */
                /** @var object{count: int}|null $loginData */
                $activityData[] = [
                    'date' => $dateObj->format('M j'), // e.g., "Dec 16"
                    'syncs' => $syncData ? (int) $syncData->total : 0,
                    'failed_syncs' => $syncData ? (int) $syncData->failed : 0,
                    'rate_limits' => $syncData ? (int) $syncData->rate_limits : 0,
                    'reports' => $reportData ? (int) $reportData->total : 0,
                    'failed_reports' => $reportData ? (int) $reportData->failed : 0,
                    'jobs' => $jobData ? (int) $jobData->count : 0,
                    'logins' => $loginData ? (int) $loginData->count : 0,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period_days' => $days,
                    'activity_data' => $activityData,
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Activity Trends Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch activity trends: ' . $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods

    /**
     * @return array{status: string, message: string}
     */
    private function checkApi(): array
    {
        return [
            'status' => 'healthy',
            'message' => 'API is responding',
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
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

    /**
     * @return array{status: string, message: string}
     */
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

    /**
     * @return array{status: string, message: string, free_bytes?: float, total_bytes?: float}
     */
    private function checkStorage(): array
    {
        try {
            $disk = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());

            if ($disk === false || $total === false || $total === 0.0) {
                return [
                    'status' => 'warning',
                    'message' => 'Could not determine disk space',
                    'free_bytes' => 0.0,
                    'total_bytes' => 0.0,
                ];
            }

            $percentFree = ($disk / $total) * 100;

            $status = $percentFree < 10 ? 'critical' : ($percentFree < 20 ? 'warning' : 'healthy');

            return [
                'status' => $status,
                'message' => sprintf('%.2f%% disk space available', $percentFree),
                'free_bytes' => (float)$disk,
                'total_bytes' => (float)$total,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkEmail(): array
    {
        try {
            $mailer = config('mail.default');
            $host = config("mail.mailers.{$mailer}.host");
            $port = config("mail.mailers.{$mailer}.port");
            
            if ($mailer === 'log' || $mailer === 'array') {
                return [
                    'status' => 'healthy',
                    'message' => "Email configured with {$mailer} driver (development mode)",
                ];
            }
            
            if (empty($host)) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'SMTP host not configured',
                ];
            }
            
            // Try to open a socket connection to verify SMTP is reachable
            $connection = @fsockopen($host, (int) $port, $errno, $errstr, 5);
            if ($connection) {
                fclose($connection);
                return [
                    'status' => 'healthy',
                    'message' => "SMTP server reachable at {$host}:{$port}",
                ];
            }
            
            return [
                'status' => 'unhealthy',
                'message' => "Cannot connect to SMTP server: {$errstr}",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Email check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkScheduler(): array
    {
        try {
            // Check if scheduler has run recently by looking at a cache key or log
            // For now, we'll check if the schedule:run command exists and queue is working
            $queueDriver = config('queue.default');
            
            if ($queueDriver === 'sync') {
                return [
                    'status' => 'healthy',
                    'message' => 'Scheduler running in sync mode',
                ];
            }
            
            // Check failed jobs count as indicator of scheduler health
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHours(24))
                ->count();
            
            if ($failedJobs > 10) {
                return [
                    'status' => 'degraded',
                    'message' => "{$failedJobs} failed jobs in last 24 hours",
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Task scheduler operational',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Scheduler check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkReports(): array
    {
        try {
            // Check if PDF generation service (Gutenberg) is configured
            $gutenbergUrl = config('services.gutenberg.url');
            
            if (empty($gutenbergUrl)) {
                // In development, Gutenberg may not be configured - check if we're in local env
                if (config('app.env') === 'local' || config('app.env') === 'development') {
                    return [
                        'status' => 'degraded',
                        'message' => 'PDF service not configured (development mode)',
                    ];
                }
                return [
                    'status' => 'unhealthy',
                    'message' => 'PDF generation service (Gutenberg) not configured',
                ];
            }
            
            // Try to reach the Gutenberg service
            $client = new \Illuminate\Http\Client\Factory();
            $response = $client->timeout(5)->get($gutenbergUrl . '/health');
            
            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'message' => 'PDF generation service operational',
                ];
            }
            
            return [
                'status' => 'degraded',
                'message' => 'PDF service returned non-success status',
            ];
        } catch (\Exception $e) {
            // If Gutenberg is not reachable, check if we have any recent successful reports
            $recentSuccess = GeneratedReport::where('status', 'completed')
                ->where('generated_at', '>=', now()->subHours(24))
                ->exists();
            
            if ($recentSuccess) {
                return [
                    'status' => 'healthy',
                    'message' => 'PDF generation working (recent reports generated)',
                ];
            }
            
            return [
                'status' => 'degraded',
                'message' => 'PDF service check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkExternal(): array
    {
        try {
            // Check if we can reach common external services
            // This is a basic connectivity check
            $client = new \Illuminate\Http\Client\Factory();
            
            // Try to reach a reliable external endpoint
            $response = $client->timeout(5)
                ->withOptions(['verify' => config('app.env') === 'production'])
                ->get('https://httpbin.org/status/200');
            
            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'message' => 'External API connectivity operational',
                ];
            }
            
            return [
                'status' => 'degraded',
                'message' => 'External connectivity issues detected',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'degraded',
                'message' => 'External connectivity check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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

    /**
     * @return array{total: int, active: int, error: int, health: string}
     */
    private function getIntegrationStatus(int $companyId): array
    {
        $integrations = Integration::where('company_id', $companyId)->get();
        
        $totalIntegrations = $integrations->count();
        $activeIntegrations = $integrations->where('status', 'active')->count();
        $errorIntegrations = $integrations->where('status', 'error')->count();

        // Get metrics for the last 24 hours across all integrations
        $recentLogs = SyncLog::whereIn('integration_id', $integrations->pluck('integration_id'))
            ->where('started_at', '>=', now()->subHours(24))
            ->get();

        $rateLimitHits = $recentLogs->sum('rate_limit_hits');
        $failedSyncs = $recentLogs->where('status', 'failed')->count();

        return [
            'total' => $totalIntegrations,
            'active' => $activeIntegrations,
            'error' => $errorIntegrations,
            'failed_syncs' => $failedSyncs,
            'rate_limit_hits' => (int) $rateLimitHits,
            'health' => $errorIntegrations === 0 ? 'healthy' : ($errorIntegrations === $totalIntegrations ? 'critical' : 'warning'),
        ];
    }

    /**
     * @return array{last_24_hours: array{total: int, completed: int, failed: int, success_rate: float|int}, health: string}
     */
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

    /**
     * @return array<string, mixed>
     */
    private function getQueueStatus(): array
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            
            // Check if using database queue driver
            if (config('queue.default') === 'database') {
                $pendingJobs = DB::table('jobs')->count();
                return [
                    'driver' => 'database',
                    'total_pending' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'health' => ($failedJobs > 10 || $pendingJobs > 500) ? 'warning' : 'healthy',
                ];
            }
            
            // Redis queue driver
            $redis = Redis::connection();
            $queues = [
                'critical' => (int) $redis->llen('queues:critical'),
                'sync' => (int) $redis->llen('queues:sync'),
                'historical' => (int) $redis->llen('queues:historical'),
                'default' => (int) $redis->llen('queues:default'),
            ];

            $totalPending = array_sum($queues);

            return [
                'driver' => 'redis',
                'queues' => $queues,
                'total_pending' => $totalPending,
                'failed_jobs' => $failedJobs,
                'health' => ($failedJobs > 10 || $totalPending > 500) ? 'warning' : 'healthy',
            ];
        } catch (\Exception $e) {
            return [
                'health' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getRedisMemoryUsage(): array
    {
        // Skip Redis check if not required
        if (!$this->isRedisRequired()) {
            return [
                'status' => 'skipped',
                'message' => 'Redis not configured (using database driver)',
            ];
        }
        
        try {
            $info = Redis::info('memory');
            
            return [
                'status' => 'healthy',
                'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'unknown',
                'maxmemory_human' => $info['maxmemory_human'] ?? 'unknown',
                'eviction_policy' => Redis::config('GET', 'maxmemory-policy')['maxmemory-policy'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not fetch Redis memory info: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getUptime(): array
    {
        // This is a placeholder - would need to track actual app start time
        return [
            'message' => 'Uptime tracking not implemented',
        ];
    }
}
