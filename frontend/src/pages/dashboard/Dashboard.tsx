import FloatingLayout from "@/components/layout/FloatingLayout";
// import { RefreshCw } from "lucide-react";
// import { Button } from "@/components/ui/button";
import {
    getIntegrationStatusBadge,
    getIntegrationStatusIcon,
    getIntegrationStatusText,
} from "./utils";
import { useDashboard } from "./hooks";
// import { DashboardHeaderActions } from "./components/DashboardHeaderActions";
import { ReportsGeneratedChart } from "./components/ReportsGeneratedChart";
import { HealthRateLimitChart } from "./components/HealthRateLimitChart";
import { IntegrationsSummary } from "./components/IntegrationsSummary";
import { SchedulesSummaryCard } from "./components/SchedulesSummaryCard";
import { StatsGrid } from "./components/StatsGrid";

export default function Dashboard() {
    const {
        // user,
        isLoading,
        // isRefreshing,
        dashboardIntegrations,
        reportsChartData,
        activityTrends,
        stats,
        systemStatus,
        allSchedules,
        // handleRefresh,
    } = useDashboard();

    return (
        <FloatingLayout>
            <div className="flex flex-col gap-6 pb-8 font-athletics">
                <StatsGrid isLoading={isLoading} stats={stats} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-6 h-full">
                        <SchedulesSummaryCard
                            isLoading={isLoading}
                            totalSchedules={allSchedules.length}
                            activeSchedules={allSchedules.filter((s: any) => s.active).length}
                            className="h-[134px]"
                        />
                        <IntegrationsSummary
                            isLoading={isLoading}
                            integrations={dashboardIntegrations}
                            getStatusBadge={getIntegrationStatusBadge}
                            getStatusText={getIntegrationStatusText}
                            getStatusIcon={getIntegrationStatusIcon}
                            className="h-[626px]"
                        />
                    </div>

                    <div className="flex flex-col gap-6">
                        <div className="h-[380px]">
                            <ReportsGeneratedChart
                                isLoading={isLoading}
                                data={reportsChartData}
                            />
                        </div>
                        <div className="h-[380px]">
                            <HealthRateLimitChart
                                isLoading={isLoading}
                                data={activityTrends}
                                systemStatus={systemStatus}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </FloatingLayout>
    );
}
