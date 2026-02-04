import { useCallback, useRef, useState, useEffect, useMemo } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { logger } from '@/lib/logger'
import { useToast } from "@/components/ui/Toast";
import { Users, UserMinus, LayoutTemplate, ShieldCheck } from "lucide-react";
import { hasPermission } from "@/utils/permissions";
import { useCustomersStore } from "@/stores/useCustomersStore";
import { useMonitoringStore } from "@/stores/useMonitoringStore";
import { useSchedulesStore } from "@/stores/useSchedulesStore";
import { monitoringApi } from "@/services/api/monitoring";
import { useNotificationStore } from "@/stores/useNotificationStore";
import { useIntegrationsQuery } from "../integrations/hooks/useIntegrations";
import type { Integration, UpcomingReport, RecentActivity, Stat } from "./types";
import type { ActivityTrendData } from "@/types/api/monitoring";
import type { ReportSchedule } from "@/stores/useSchedulesStore";

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
    
    // Granular loading states
    const [loadingStats, setLoadingStats] = useState(true);
    const [loadingIntegrations, setLoadingIntegrations] = useState(true);
    const [loadingSchedules, setLoadingSchedules] = useState(true);
    const [loadingCharts, setLoadingCharts] = useState(true);
    
    // Keep isRefreshing for the button
    const [isRefreshing, setIsRefreshing] = useState(false);

    const canViewIntegrations = hasPermission(user, 'integrations', 'view');
    const canViewMonitoring = hasPermission(user, 'monitoring', 'view');
    const canViewSchedules = hasPermission(user, 'reports', 'view');

    const { data: integrationsData, refetch: refetchIntegrations } = useIntegrationsQuery({
        enabled: isAuthenticated && canViewIntegrations
    });
    const integrations = useMemo(() => integrationsData?.integrations || [], [integrationsData?.integrations]);

    const totalCustomers = useCustomersStore((state) => state.totalCustomersCount);
    const systemStatus = useMonitoringStore((state) => state.systemStatus);
    const reportMetrics = useMonitoringStore((state) => state.reportMetrics);
    const allSchedules = useSchedulesStore((state) => state.allSchedules);

    const fetchCustomers = useCustomersStore((state) => state.fetchTotalCustomersCount);
    const unmappedCount = useCustomersStore((state) => state.unmappedCount);
    const fetchUnmappedCustomers = useCustomersStore((state) => state.fetchUnmappedCustomers);
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
    }, [isAuthenticated, canViewIntegrations, canViewMonitoring, canViewSchedules]);

    const loadDashboardData = async () => {
        if (!isAuthenticated) return;

        // Reset individual loading states
        setLoadingStats(true);
        setLoadingIntegrations(true);
        setLoadingSchedules(true);
        setLoadingCharts(true);

        // Fetch Stats (Customers & System Overview)
        const loadStats = async () => {
            try {
                await Promise.all([
                    fetchCustomers(),
                    fetchUnmappedCustomers(),
                    canViewMonitoring ? fetchSystemStatus() : Promise.resolve()
                ]);
            } finally {
                setLoadingStats(false);
            }
        };

        // Fetch Integrations
        const loadIntegrations = async () => {
            if (canViewIntegrations) {
                try {
                    await refetchIntegrations();
                } finally {
                    setLoadingIntegrations(false);
                }
            } else {
                setLoadingIntegrations(false);
            }
        };

        // Fetch Schedules
        const loadSchedules = async () => {
            if (canViewSchedules) {
                try {
                    await fetchSchedules();
                } finally {
                    setLoadingSchedules(false);
                }
            } else {
                setLoadingSchedules(false);
            }
        };

        // Fetch Chart Data
        const loadCharts = async () => {
            if (canViewMonitoring) {
                try {
                    await Promise.all([
                        fetchReportMetrics(),
                        fetchMonthlyReports(),
                        fetchActivityTrends()
                    ]);
                } finally {
                    setLoadingCharts(false);
                }
            } else {
                setLoadingCharts(false);
            }
        };

        // Fire all sub-loaders in parallel WITHOUT and-ing them with a global Promise.all
        loadStats();
        loadIntegrations();
        loadSchedules();
        loadCharts();
    };

    const handleRefresh = async () => {
        setIsRefreshing(true);
        try {
            await loadDashboardData();
            toast.success("Dashboard refreshed");
        } catch (err) {
            logger.error('Dashboard refresh error:', err);
            toast.error("Failed to refresh dashboard");
        } finally {
            setIsRefreshing(false);
        }
    };

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
            if (a.isSourceOfTruth && !b.isSourceOfTruth) return -1;
            if (!a.isSourceOfTruth && b.isSourceOfTruth) return 1;
            return 0;
        });

    const upcomingReports: UpcomingReport[] = allSchedules
        .filter((s: ReportSchedule) => s.active)
        .slice(0, 5)
        .map((schedule: ReportSchedule) => ({
            id: schedule.report_schedule_id,
            name: schedule.report_type,
            customer: schedule.customer_name || "Unknown Customer",
            nextRun: schedule.next_scheduled_at
                ? new Date(schedule.next_scheduled_at).toLocaleString('en-US', { timeZone: 'Australia/Darwin' })
                : "Not scheduled",
            status: "scheduled" as const,
        }));

    const [reportsChartData, setReportsChartData] = useState<Array<{ date: string; count: number }>>([])
    const [activityTrends, setActivityTrends] = useState<ActivityTrendData[]>([])

    const fetchMonthlyReports = async () => {
        if (!isAuthenticated || !canViewMonitoring) return;
        try {
            const response = await monitoringApi.getMonthlyReportAnalytics(6);
            setReportsChartData(response.data.monthly_data);
        } catch (error) {
            logger.apiError('/monitoring/monthly-analytics', error, { months: 6 });
            setReportsChartData([]);
        }
    };

    const fetchActivityTrends = async () => {
        if (!isAuthenticated || !canViewMonitoring) return;
        try {
            const response = await monitoringApi.getActivityTrends(7);
            setActivityTrends(response.data.activity_data);
        } catch (error) {
            logger.apiError('/monitoring/activity-trends', error, { days: 7 });
            setActivityTrends([]);
        }
    };

    useEffect(() => {
        if (isAuthenticated && canViewMonitoring && reportMetrics) {
            fetchMonthlyReports();
            fetchActivityTrends();
        }
    }, [isAuthenticated, canViewMonitoring, reportMetrics]);

    const stats: Stat[] = [
        {
            label: "Mapped Customers",
            value: totalCustomers.toString(),
            icon: Users,
            trend: {
                value: totalCustomers > 0 ? "All customers" : "No customers",
                isPositive: true,
            },
        },
        {
            label: "Unmapped Customers",
            value: unmappedCount.toString(),
            icon: UserMinus,
            trend: {
                value: unmappedCount > 0 ? `${unmappedCount} pending` : "All mapped",
                isPositive: unmappedCount === 0,
            },
        },
        {
            label: "Customized Templates",
            value: (systemStatus?.templates.customized || 0).toString(),
            icon: LayoutTemplate,
            trend: {
                value: `${systemStatus?.templates.total || 0} total templates`,
                isPositive: true,
            },
        },
        {
            label: "System Users",
            value: (systemStatus?.users.total || 0).toString(),
            icon: ShieldCheck,
            trend: {
                value: `${systemStatus?.users.active || 0} active users`,
                isPositive: true,
            },
        },
    ];

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
        handleRefresh,
        toast,
    };
};
