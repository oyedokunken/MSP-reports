import { useEffect, useState } from "react";
import FloatingLayout from "@/components/layout/FloatingLayout";
import { useToast } from "@/components/ui/Toast";
import { useAuth } from "@/contexts/AuthContext";
import { FileText, Cable, Users, Activity, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
    getIntegrationStatusBadge,
    getIntegrationStatusIcon,
    getIntegrationStatusText,
} from "./utils";
import { useQuickSync } from "./hooks";
import type {
    Integration,
    UpcomingReport,
    RecentActivity,
    Stat,
} from "./types";
import { DashboardHeaderActions } from "./components/DashboardHeaderActions";
import { RecentActivityCard } from "./components/RecentActivityCard";
import { QuickActionsCard } from "./components/QuickActionsCard";
import { IntegrationsSummary } from "./components/IntegrationsSummary";
import { UpcomingReports } from "./components/UpcomingReports";
import { StatsGrid } from "./components/StatsGrid";
import { useIntegrationStore } from "@/stores/useIntegrationStore";
import { useCustomersStore } from "@/stores/useCustomersStore";
import { useMonitoringStore } from "@/stores/useMonitoringStore";
import { useSchedulesStore } from "@/stores/useSchedulesStore";
import { useNotificationStore } from "@/stores/useNotificationStore";

export default function Dashboard() {
    const toast = useToast();
    const { isAuthenticated, user } = useAuth();
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);

    // Store data
    const integrations = useIntegrationStore((state) => state.integrations);
    const customers = useCustomersStore((state) => state.customers);
    const systemStatus = useMonitoringStore((state) => state.systemStatus);
    const reportMetrics = useMonitoringStore((state) => state.reportMetrics);
    const schedules = useSchedulesStore((state) => state.schedules);

    const fetchIntegrations = useIntegrationStore(
        (state) => state.fetchIntegrations
    );
    const fetchCustomers = useCustomersStore((state) => state.fetchCustomers);
    const fetchSystemStatus = useMonitoringStore(
        (state) => state.fetchSystemStatus
    );
    const fetchReportMetrics = useMonitoringStore(
        (state) => state.fetchReportMetrics
    );
    const fetchSchedules = useSchedulesStore((state) => state.fetchSchedules);
    const setNotifications = useNotificationStore((state) => state.setNotifications);

    // Fetch all data on mount
    useEffect(() => {
        // Only load data if user is authenticated
        if (isAuthenticated) {
            loadDashboardData();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isAuthenticated]);

    const loadDashboardData = async () => {
        // Guard against loading when not authenticated
        if (!isAuthenticated) {
            return;
        }
        
        setIsLoading(true);
        try {
            await Promise.all([
                fetchIntegrations(),
                fetchCustomers(),
                fetchSystemStatus(),
                fetchReportMetrics(),
                fetchSchedules(),
            ]);
        } catch (err: any) {
            // Only show error if we're still authenticated (not during logout)
            if (isAuthenticated) {
                console.error("Failed to load dashboard data:", err);
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
        } catch (err) {
            toast.error("Failed to refresh dashboard");
        } finally {
            setIsRefreshing(false);
        }
    };

    // Generate recent activity reactively from store data
    const recentActivity: RecentActivity[] = (() => {
        const activities: RecentActivity[] = [];

        // Add integration activities (synced or newly created)
        integrations.slice(0, 5).forEach((int: any, index: number) => {
            if (int.lastSync && int.lastSync !== "Never") {
                // Show sync activity for synced integrations
                activities.push({
                    id: index + 1,
                    type: "sync",
                    title: `${int.name} sync ${
                        int.syncStatus === "success" ? "completed" : "failed"
                    }`,
                    time: int.lastSync,
                    severity: int.syncStatus === "success" ? "green" : "red",
                });
            } else if (int.status === "active" || int.status === "warning") {
                // Show newly created integrations that haven't synced yet
                activities.push({
                    id: index + 1,
                    type: "alert",
                    title: `${int.name} integration added`,
                    time: "Recently",
                    severity: int.status === "warning" ? "yellow" : "green",
                });
            }
        });

        // Add report activities
        if (reportMetrics?.metrics.completed_reports) {
            activities.push({
                id: activities.length + 1,
                type: "report",
                title: `${reportMetrics.metrics.completed_reports} reports generated successfully`,
                time: "Recently",
                severity: "green",
            });
        }

        return activities.slice(0, 5);
    })();

    // Sync notifications with recent activity
    useEffect(() => {
        const notifications = recentActivity.map((activity, index) => ({
            id: Date.now() + index, // Unique ID based on timestamp
            title: activity.title,
            time: activity.time,
            read: false, // All new activities are unread
            type: activity.type as any,
            severity: activity.severity,
        }));
        setNotifications(notifications);
    }, [integrations, reportMetrics, setNotifications]);

    // Transform integrations data (already transformed from store)
    const dashboardIntegrations: Integration[] = integrations.map(
        (int: any) => ({
            id: int.id,
            name: int.name,
            type: int.type || "unknown",
            status: (int.status === "syncing" ? "warning" : int.status) as
                | "active"
                | "error"
                | "warning",
            lastSync: int.lastSync || "Never",
            customers: int.customers || 0,
            syncStatus: int.syncStatus || "pending",
        })
    );

    // Transform schedules to upcoming reports
    const upcomingReports: UpcomingReport[] = schedules
        .filter((s: any) => s.active)
        .slice(0, 5)
        .map((schedule: any) => ({
            id: schedule.report_schedule_id,
            name: schedule.report_type,
            customer: schedule.customer_name || "Unknown Customer",
            nextRun: schedule.next_scheduled_at
                ? new Date(schedule.next_scheduled_at).toLocaleString()
                : "Not scheduled",
            status: "scheduled" as const,
        }));

    // Calculate stats from real data
    const activeIntegrations = integrations.filter(
        (i: any) => i.status === "active"
    ).length;
    const errorIntegrations = integrations.filter(
        (i: any) => i.status === "error"
    ).length;
    const totalReports = reportMetrics?.metrics.total_reports || 0;
    const successRate = reportMetrics?.metrics.success_rate || 0;
    const queueHealth = systemStatus?.queue.health || "unknown";

    const stats: Stat[] = [
        {
            label: "Customers",
            value: customers.length.toString(),
            icon: Users,
            trend: {
                value: `${
                    customers.filter((c: any) => c.status === "active").length
                } active`,
                isPositive: true,
            },
        },
        {
            label: "Integrations",
            value: activeIntegrations.toString(),
            icon: Cable,
            trend: {
                value:
                    errorIntegrations > 0
                        ? `${errorIntegrations} error${
                              errorIntegrations > 1 ? "s" : ""
                          }`
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

    const { syncingId, progress, handleQuickSync } = useQuickSync(
        toast.success,
        toast.error
    );

    return (
        <FloatingLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-foreground">
                            {user?.name ? `Welcome back, ${user.name.split(' ')[0]}` : 'Dashboard'}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            Overview of your environment, reports and
                            integrations
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                            disabled={isRefreshing || isLoading}
                            className="gap-2"
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${
                                    isRefreshing ? "animate-spin" : ""
                                }`}
                            />
                            Refresh
                        </Button>
                        <DashboardHeaderActions
                            onToastSuccess={toast.success}
                            onToastInfo={toast.info}
                        />
                    </div>
                </div>

                <StatsGrid isLoading={isLoading} stats={stats} />

                <div className="grid gap-6 lg:grid-cols-3 min-w-0">
                    <div className="lg:col-span-2 space-y-6 min-w-0">
                        <RecentActivityCard
                            isLoading={isLoading}
                            items={recentActivity}
                        />
                        <QuickActionsCard onInfo={toast.info} />
                    </div>
                    <div className="space-y-6 min-w-0">
                        <IntegrationsSummary
                            isLoading={isLoading}
                            integrations={dashboardIntegrations}
                            isSyncingId={syncingId}
                            syncProgress={progress}
                            onSync={handleQuickSync}
                            onInfo={toast.info}
                            getStatusBadge={getIntegrationStatusBadge}
                            getStatusText={getIntegrationStatusText}
                            getStatusIcon={getIntegrationStatusIcon}
                        />
                        <UpcomingReports
                            isLoading={isLoading}
                            reports={upcomingReports}
                            onInfo={toast.info}
                        />
                    </div>
                </div>
            </div>
        </FloatingLayout>
    );
}
