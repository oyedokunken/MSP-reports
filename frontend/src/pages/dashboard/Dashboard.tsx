import type { ReportSchedule } from "@/stores/useSchedulesStore";
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

export default function Dashboard() {
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
        allSchedules,
    } = useDashboard();

    return (
        <div className="flex flex-col gap-6 pb-8 font-athletics">
            <StatsGrid isLoading={loadingStats} stats={stats} />

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="flex flex-col gap-6 h-full">
                    <SchedulesSummaryCard
                        isLoading={loadingSchedules}
                        totalSchedules={allSchedules.length}
                        activeSchedules={allSchedules.filter((s: ReportSchedule) => s.active).length}
                        className=""
                    />
                    <IntegrationsSummary
                        isLoading={loadingIntegrations}
                        integrations={dashboardIntegrations}
                        getStatusBadge={getIntegrationStatusBadge}
                        getStatusText={getIntegrationStatusText}
                        getStatusIcon={getIntegrationStatusIcon}
                        className="flex-1"
                    />
                </div>

                <div className="flex flex-col gap-6">
                    <div className="h-[380px]">
                        <ReportsGeneratedChart
                            isLoading={loadingCharts}
                            data={reportsChartData}
                        />
                    </div>
                    <div className="h-[380px]">
                        <HealthRateLimitChart
                            isLoading={loadingCharts}
                            data={activityTrends}
                            systemStatus={systemStatus}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
