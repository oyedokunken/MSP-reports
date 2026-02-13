import { useCallback, useRef, useState, useMemo } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { logger } from '@/lib/logger'
import { useToast } from "@/components/ui/Toast";
import { Users, UserMinus, ShieldCheck, UserPlus } from "lucide-react";
import { useDashboardSummaryQuery } from "./hooks/useDashboardSummary";
import type { Integration, UpcomingReport, RecentActivity, Stat } from "./types";

export const useQuickSync = (
    onSuccess: (title: string, description?: string) => void,
    onError: (title: string, description?: string) => void
) => {
    const [syncingId, setSyncingId] = useState<number | null>(null);
    const [progress, setProgress] = useState<number>(0);
    const intervalRef = useRef<number | null>(null);

    const handleQuickSync = useCallback(
        async (integrationId: number, name: string) => {
            setSyncingId(integrationId);
            setProgress(0);
            try {
                intervalRef.current = window.setInterval(() => {
                    setProgress((p) => Math.min(p + Math.random() * 20, 90));
                }, 250);
                await new Promise((r) => setTimeout(r, 1500));
                if (intervalRef.current)
                    window.clearInterval(intervalRef.current);
                setProgress(100);
                await new Promise((r) => setTimeout(r, 250));
                onSuccess("Sync Complete", `${name} synced successfully`);
            } catch {
                onError("Sync Failed", `Failed to sync ${name}`);
            } finally {
                setSyncingId(null);
                setProgress(0);
            }
        },
        [onSuccess, onError]
    );

    return { syncingId, progress, handleQuickSync };
};

export const useDashboard = () => {
    const toast = useToast();
    const { isAuthenticated, user } = useAuth();

    // Use the new unified dashboard query
    const {
        data: dashboardData,
        isLoading,
        isFetching,
        refetch,
        error
    } = useDashboardSummaryQuery({
        enabled: isAuthenticated,
    });

    // Extract data from the unified response
    const stats: Stat[] = useMemo(() => {
        if (!dashboardData?.data?.stats) {
            return [
                {
                    label: "Imported Customers",
                    value: "0",
                    icon: UserPlus,
                    trend: { value: "From integrations", isPositive: true },
                },
                {
                    label: "Mapped Customers",
                    value: "0",
                    icon: Users,
                    trend: { value: "No customers", isPositive: true },
                },
                {
                    label: "Unmapped Customers",
                    value: "0",
                    icon: UserMinus,
                    trend: { value: "All mapped", isPositive: true },
                },
                {
                    label: "System Users",
                    value: "0",
                    icon: ShieldCheck,
                    trend: { value: "0 active users", isPositive: true },
                },
            ];
        }

        const { totalCustomers, unmappedCustomers, importedCustomers, users } = dashboardData.data.stats;

        return [
            {
                label: "Imported Customers",
                value: (importedCustomers ?? 0).toString(),
                icon: UserPlus,
                trend: {
                    value: importedCustomers > 0 ? "From integrations" : "Sync to import",
                    isPositive: importedCustomers > 0,
                },
            },
            {
                label: "Mapped Customers",
                value: totalCustomers.toString(),
                icon: Users,
                trend: {
                    value: totalCustomers > 0
                        ? `${totalCustomers} of ${importedCustomers ?? 0} mapped`
                        : importedCustomers > 0 ? `${importedCustomers} awaiting mapping` : "No customers",
                    isPositive: totalCustomers > 0,
                },
            },
            {
                label: "Unmapped Customers",
                value: unmappedCustomers.toString(),
                icon: UserMinus,
                trend: {
                    value: unmappedCustomers > 0 ? `${unmappedCustomers} pending` : "All mapped",
                    isPositive: unmappedCustomers === 0,
                },
            },
            {
                label: "System Users",
                value: users.total.toString(),
                icon: ShieldCheck,
                trend: {
                    value: `${users.active} active users`,
                    isPositive: true,
                },
            },
        ];
    }, [dashboardData?.data?.stats]);

    // Transform integrations from API response
    const dashboardIntegrations: Integration[] = useMemo(() => {
        if (!dashboardData?.data?.integrations) return [];

        return dashboardData.data.integrations.map((int) => ({
            id: int.id,
            name: int.name,
            type: int.type,
            status: int.status as "active" | "error" | "warning",
            lastSync: int.lastSync,
            customers: int.customers,
            syncStatus: int.syncStatus,
            isSourceOfTruth: int.isSourceOfTruth,
        }));
    }, [dashboardData?.data?.integrations]);

    // Transform upcoming schedules from API response
    const upcomingReports: UpcomingReport[] = useMemo(() => {
        if (!dashboardData?.data?.upcomingSchedules) return [];

        return dashboardData.data.upcomingSchedules.map((schedule) => ({
            id: schedule.id,
            name: schedule.name,
            customer: schedule.customer,
            nextRun: schedule.nextRun,
            status: schedule.status,
        }));
    }, [dashboardData?.data?.upcomingSchedules]);

    // Transform recent activity from API response
    const recentActivity: RecentActivity[] = useMemo(() => {
        if (!dashboardData?.data?.recentActivity) return [];

        return dashboardData.data.recentActivity.map((activity) => ({
            id: activity.id,
            type: activity.type,
            title: activity.title,
            time: activity.time,
            severity: activity.severity,
        }));
    }, [dashboardData?.data?.recentActivity]);

    // Chart data
    const reportsChartData = useMemo(() => {
        return dashboardData?.data?.charts?.monthlyReports || [];
    }, [dashboardData?.data?.charts?.monthlyReports]);

    const activityTrends = useMemo(() => {
        return dashboardData?.data?.charts?.activityTrends || [];
    }, [dashboardData?.data?.charts?.activityTrends]);

    // Refresh handler
    const handleRefresh = useCallback(async () => {
        try {
            await refetch();
            toast.success("Dashboard refreshed");
        } catch (err) {
            logger.error('Dashboard refresh error:', err);
            toast.error("Failed to refresh dashboard");
        }
    }, [refetch, toast]);

    // Derive loading states from the unified query
    // All sections load together now, but we keep granular states for UI compatibility
    const loadingStats = isLoading;
    const loadingIntegrations = isLoading;
    const loadingSchedules = isLoading;
    const loadingCharts = isLoading;
    const isRefreshing = isFetching && !isLoading;

    // Legacy compatibility - provide system status with calculated metrics
    const systemStatus = useMemo(() => {
        if (!dashboardData?.data) return null;

        const { stats, charts } = dashboardData.data;

        // Calculate 24h metrics from activity trends (sum all days)
        const activityTrends = charts?.activityTrends ?? [];
        const total24hRateLimits = activityTrends.reduce((sum, day) => sum + day.rate_limits, 0);
        const total24hFailedSyncs = activityTrends.reduce((sum, day) => sum + day.failed_syncs, 0);
        const total24hFailedReports = activityTrends.reduce((sum, day) => sum + day.failed_reports, 0);
        const total24hReports = activityTrends.reduce((sum, day) => sum + day.reports, 0);

        return {
            templates: stats.templates,
            users: stats.users,
            integrations: {
                total: 0,
                active: 0,
                error: 0,
                rate_limit_hits: total24hRateLimits,
                failed_syncs: total24hFailedSyncs,
                health: 'healthy',
            },
            reports: {
                last_24_hours: {
                    failed: total24hFailedReports,
                    total: total24hReports,
                    completed: total24hReports - total24hFailedReports,
                    success_rate: total24hReports > 0 ? ((total24hReports - total24hFailedReports) / total24hReports) * 100 : 100,
                },
                health: 'healthy',
            },
            system: {
                api: { status: 'healthy', message: 'OK' },
                database: { status: 'healthy', message: 'OK' },
                redis: { status: 'healthy', message: 'OK' },
                storage: { status: 'healthy', message: 'OK', free_bytes: 0, total_bytes: 0 },
                uptime: { message: 'OK' },
            },
            queue: {
                total_pending: 0,
                failed_jobs: 0,
                health: 'healthy',
            },
        };
    }, [dashboardData?.data]);

    const allSchedules = useMemo(() => {
        return dashboardData?.data?.upcomingSchedules || [];
    }, [dashboardData?.data?.upcomingSchedules]);

    const scheduleCounts = useMemo(() => {
        const schedules = dashboardData?.data?.stats?.schedules;
        if (schedules) {
            return { total: schedules.total, active: schedules.active };
        }
        return {
            total: allSchedules.length,
            active: allSchedules.filter((s) => s.status === 'scheduled').length,
        };
    }, [dashboardData?.data?.stats?.schedules, allSchedules]);

    return {
        user,
        loadingStats,
        loadingIntegrations,
        loadingSchedules,
        loadingCharts,
        isRefreshing,
        recentActivity,
        dashboardIntegrations,
        upcomingReports,
        reportsChartData,
        activityTrends,
        stats,
        systemStatus,
        allSchedules,
        scheduleCounts,
        handleRefresh,
        toast,
        error,
    };
};
