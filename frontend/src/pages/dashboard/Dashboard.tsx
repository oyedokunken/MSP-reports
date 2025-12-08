import FloatingLayout from "@/components/layout/FloatingLayout";
import { RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
    getIntegrationStatusBadge,
    getIntegrationStatusIcon,
    getIntegrationStatusText,
} from "./utils";
import { useDashboard, useQuickSync } from "./hooks";
import { DashboardHeaderActions } from "./components/DashboardHeaderActions";
import { RecentActivityCard } from "./components/RecentActivityCard";
import { QuickActionsCard } from "./components/QuickActionsCard";
import { IntegrationsSummary } from "./components/IntegrationsSummary";
import { StatsGrid } from "./components/StatsGrid";

export default function Dashboard() {
    const {
        user,
        isLoading,
        isRefreshing,
        recentActivity,
        dashboardIntegrations,
        stats,
        handleRefresh,
        toast,
    } = useDashboard();

    const { syncingId, progress, handleQuickSync } = useQuickSync(toast.success, toast.error);

    return (
        <FloatingLayout>
            <div className="h-full flex flex-col gap-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            {user?.name ? `Welcome back, ${user.name.split(' ')[0]}` : 'Dashboard'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Overview of your environment, reports and integrations
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
                            <RefreshCw className={`h-4 w-4 ${isRefreshing ? "animate-spin" : ""}`} />
                            Refresh
                        </Button>
                        <DashboardHeaderActions />
                    </div>
                </div>

                {/* Stats Grid */}
                <StatsGrid isLoading={isLoading} stats={stats} />

                {/* Main Content - 2 Column Layout */}
                <div className="grid gap-4 lg:grid-cols-2 flex-1 min-h-0">
                    {/* Left Column */}
                    <div className="flex flex-col gap-4 min-h-0">
                        <div className="flex-1 min-h-0">
                            <IntegrationsSummary
                                isLoading={isLoading}
                                integrations={dashboardIntegrations}
                                isSyncingId={syncingId}
                                syncProgress={progress}
                                onSync={handleQuickSync}
                                getStatusBadge={getIntegrationStatusBadge}
                                getStatusText={getIntegrationStatusText}
                                getStatusIcon={getIntegrationStatusIcon}
                            />
                        </div>
                    </div>

                    {/* Right Column */}
                    <div className="flex flex-col gap-4 min-h-0">
                        <div className="flex-1 min-h-0">
                            <RecentActivityCard
                                isLoading={isLoading}
                                items={recentActivity}
                            />
                        </div>
                        <QuickActionsCard onInfo={toast.info} />
                    </div>
                </div>
            </div>
        </FloatingLayout>
    );
}
