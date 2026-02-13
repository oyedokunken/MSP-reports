import { useEffect } from "react";
import {
    getIntegrationStatusBadge,
    getIntegrationStatusIcon,
    getIntegrationStatusText,
} from "./utils";
import { useDashboard } from "./hooks";
import { ReportsGeneratedChart } from "./components/ReportsGeneratedChart";
import { HealthRateLimitChart } from "./components/HealthRateLimitChart";
import { IntegrationsSummary } from "./components/IntegrationsSummary";
import { SchedulesSummaryCard } from "./components/SchedulesSummaryCard";
import { StatsGrid } from "./components/StatsGrid";
import { DashboardErrorBoundary } from "./components/DashboardErrorBoundary";
import { StatsGridSkeleton } from "./components/skeletons/StatsGridSkeleton";
import { ChartSkeleton } from "./components/skeletons/ChartSkeleton";
import { markPerformance, measurePerformance } from "@/utils/performance";

function DashboardContent() {
    const {
        loadingStats,
        loadingIntegrations,
        loadingSchedules,
        loadingCharts,
        dashboardIntegrations,
        reportsChartData,
        activityTrends,
        stats,
        systemStatus,
        scheduleCounts,
    } = useDashboard();

    // Performance monitoring
    useEffect(() => {
        markPerformance('dashboard-start');

        return () => {
            if (!loadingStats && !loadingCharts) {
                markPerformance('dashboard-interactive');
                measurePerformance('dashboard-load-time', 'dashboard-start', 'dashboard-interactive');
            }
        };
    }, [loadingStats, loadingCharts]);

    useEffect(() => {
        if (!loadingStats && !loadingCharts) {
            markPerformance('dashboard-data-ready');
            measurePerformance('dashboard-data-load', 'dashboard-start', 'dashboard-data-ready');
        }
    }, [loadingStats, loadingCharts]);

    return (
        <div className="flex flex-col gap-6 pb-0">
            {loadingStats ? (
                <StatsGridSkeleton />
            ) : (
                <StatsGrid isLoading={false} stats={stats} />
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="flex flex-col gap-6">
                    <div className="h-[130px]">
                        <SchedulesSummaryCard
                            isLoading={loadingSchedules}
                            totalSchedules={scheduleCounts.total}
                            activeSchedules={scheduleCounts.active}
                            className="h-full"
                        />
                    </div>
                    <div className="h-[630px]">
                        <IntegrationsSummary
                            isLoading={loadingIntegrations}
                            integrations={dashboardIntegrations}
                            getStatusBadge={getIntegrationStatusBadge}
                            getStatusText={getIntegrationStatusText}
                            getStatusIcon={getIntegrationStatusIcon}
                            className="h-full"
                        />
                    </div>
                </div>

                <div className="flex flex-col gap-6">
                    <div className="h-[380px]">
                        {loadingCharts ? (
                            <ChartSkeleton />
                        ) : (
                            <ReportsGeneratedChart
                                isLoading={false}
                                data={reportsChartData}
                            />
                        )}
                    </div>
                    <div className="h-[380px]">
                        {loadingCharts ? (
                            <ChartSkeleton />
                        ) : (
                            <HealthRateLimitChart
                                isLoading={false}
                                data={activityTrends}
                                systemStatus={systemStatus}
                            />
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Dashboard() {
    return (
        <DashboardErrorBoundary>
            <DashboardContent />
        </DashboardErrorBoundary>
    );
}
