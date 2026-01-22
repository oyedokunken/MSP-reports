import { useCallback, useRef, useState, useEffect, useMemo } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/components/ui/Toast";
import { FileText, Cable, Users, Activity } from "lucide-react";
import { useCustomersStore } from "@/stores/useCustomersStore";
import { useMonitoringStore } from "@/stores/useMonitoringStore";
import { useSchedulesStore } from "@/stores/useSchedulesStore";
import { monitoringApi } from "@/services/api/monitoring";
import { useNotificationStore } from "@/stores/useNotificationStore";
import { useIntegrationsQuery } from "../integrations/hooks/useIntegrations";
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
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);

    // Replace useIntegrationStore with React Query
    const { data, refetch: refetchIntegrations } = useIntegrationsQuery();
    const integrations = data?.integrations || [];

    // Other stores still valid
    const totalCustomers = useCustomersStore((state) => state.totalCustomersCount);
    const systemStatus = useMonitoringStore((state) => state.systemStatus);
    const reportMetrics = useMonitoringStore((state) => state.reportMetrics);
    const schedules = useSchedulesStore((state) => state.schedules);

    const fetchCustomers = useCustomersStore((state) => state.fetchTotalCustomersCount);
    const fetchSystemStatus = useMonitoringStore((state) => state.fetchSystemStatus);
    const fetchReportMetrics = useMonitoringStore((state) => state.fetchReportMetrics);
    const fetchSchedules = useSchedulesStore((state) => state.fetchSchedules);
    const notifications = useNotificationStore((state) => state.notifications);
    const recentActivity: RecentActivity[] = useMemo(() => {
        const activities: RecentActivity[] = [];

        integrations.slice(0, 5).forEach((int, index) => {
            if (int.lastSync && int.lastSync !== "Never") {
                activities.push({
                    id: index + 1,
                    type: "sync",
                    title: `${int.name} sync ${int.syncStatus === "success" ? "completed" : "failed"}`,
                    time: int.lastSync,
                    severity: int.syncStatus === "success" ? "green" : "red",
                });
            } else if (int.status === "active" || int.status === "warning") {
                activities.push({
                    id: index + 1,
                    type: "alert",
                    title: `${int.name} integration added`,
                    time: "Recently",
                    severity: int.status === "warning" ? "yellow" : "green",
                });
            }
        });

        // Blend real notifications with legacy metrics
        const realActivities: RecentActivity[] = notifications.map((n, idx) => ({
            id: typeof n.id === 'number' ? n.id : (1000 + idx),
            type: n.type,
            title: n.title,
            time: n.time,
            severity: n.severity === 'blue' ? 'green' : (n.severity as 'green' | 'yellow' | 'red')
        }));

        if (realActivities.length > 0) {
            return realActivities.slice(0, 5);
        }

        return activities.slice(0, 5);
    }, [notifications, integrations]);

    useEffect(() => {
        if (isAuthenticated) {
            loadDashboardData();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isAuthenticated]);

    const loadDashboardData = async () => {
        if (!isAuthenticated) {
            return;
        }

        setIsLoading(true);
        try {
            await Promise.all([
                refetchIntegrations(),
                fetchCustomers(),
                fetchSystemStatus(),
                fetchReportMetrics(),
                fetchSchedules(),
            ]);
        } catch {
            if (isAuthenticated) {
                toast.error("Failed to load dashboard data");
            }
        } finally {
            setIsLoading(false);
        }
    };

    const handleRefresh = async () => {
        setIsRefreshing(true);
        try {
            await loadDashboardData();
            toast.success("Dashboard refreshed");
        } catch {
            toast.error("Failed to refresh dashboard");
        } finally {
            setIsRefreshing(false);
        }
    };

    // Show all integrations on dashboard with scroll, source of truth first
    const dashboardIntegrations: Integration[] = integrations
        .map((int) => ({
            id: int.id,
            name: int.name,
            type: int.type || "unknown",
            status: (int.status as string === "syncing" ? "warning" : int.status) as "active" | "error" | "warning",
            lastSync: int.lastSync || "Never",
            customers: int.customers || 0,
            syncStatus: int.syncStatus || "pending",
            isSourceOfTruth: int.isSourceOfTruth || false,
        }))
        .sort((a, b) => {
            // Source of truth always first
            if (a.isSourceOfTruth && !b.isSourceOfTruth) return -1;
            if (!a.isSourceOfTruth && b.isSourceOfTruth) return 1;
            return 0;
        });

    // Limit to 5 upcoming reports for dashboard (performance)
    const upcomingReports: UpcomingReport[] = schedules
        .filter((s) => s.active)
        .slice(0, 5)
        .map((schedule) => ({
            id: schedule.report_schedule_id,
            name: schedule.report_type,
            customer: schedule.customer_name || "Unknown Customer",
            nextRun: schedule.next_scheduled_at
                ? new Date(schedule.next_scheduled_at).toLocaleString('en-US', { timeZone: 'Australia/Darwin' })
                : "Not scheduled",
            status: "scheduled" as const,
        }));

    const activeIntegrations = integrations.filter((i) => i.status === "active").length;
    const errorIntegrations = integrations.filter((i) => i.status === "error").length;
    const totalReports = reportMetrics?.metrics.total_reports || 0;
    const successRate = reportMetrics?.metrics.success_rate || 0;
    const queueHealth = systemStatus?.queue.health || "unknown";

    // Fetch monthly report analytics data
    const [reportsChartData, setReportsChartData] = useState<Array<{ date: string; count: number }>>([])

    useEffect(() => {
        const fetchMonthlyReports = async () => {
            if (!isAuthenticated) return

            try {
                const response = await monitoringApi.getMonthlyReportAnalytics(6)
                setReportsChartData(response.data.monthly_data)
            } catch (error) {
                console.error('Failed to fetch monthly report analytics:', error)
                // Fallback to empty array on error
                setReportsChartData([])
            }
        }

        fetchMonthlyReports()
    }, [isAuthenticated, reportMetrics])

    const stats: Stat[] = [
        {
            label: "Customers",
            value: totalCustomers.toString(),
            icon: Users,
            trend: {
                value: totalCustomers > 0 ? "All customers" : "No customers",
                isPositive: true,
            },
        },
        {
            label: "Integrations",
            value: activeIntegrations.toString(),
            icon: Cable,
            trend: {
                value: errorIntegrations > 0
                    ? `${errorIntegrations} error${errorIntegrations > 1 ? "s" : ""}`
                    : "All healthy",
                isPositive: errorIntegrations === 0,
            },
        },
        {
            label: `Reports (${reportMetrics?.period_days || 7}d)`,
            value: totalReports.toString(),
            icon: FileText,
            trend: {
                value: `${successRate.toFixed(1)}% success`,
                isPositive: successRate >= 95,
            },
        },
        {
            label: "System Health",
            value: queueHealth === "healthy" ? "Healthy" : "Issues",
            icon: Activity,
            trend: {
                value: systemStatus?.queue.pending_jobs
                    ? `${systemStatus.queue.pending_jobs} pending`
                    : "No jobs",
                isPositive: (systemStatus?.queue.pending_jobs || 0) < 10,
            },
        },
    ];

    return {
        user,
        isLoading,
        isRefreshing,
        recentActivity,
        dashboardIntegrations,
        upcomingReports,
        reportsChartData,
        stats,
        handleRefresh,
        toast,
    };
};
