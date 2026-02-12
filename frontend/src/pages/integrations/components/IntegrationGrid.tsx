import { StatCardSkeleton, EmptyState } from "@/components/ui/LoadingStates";
import { Cable } from "lucide-react";
import type { Integration } from "../types/integration";
import { IntegrationCard } from "./IntegrationCard";

type IntegrationGridProps = {
    isLoading: boolean;
    items: Integration[];
    syncingId: number | null;
    testingId?: number | null;
    progress: number;
    settingSourceOfTruthId: number | null;
    onSync: (id: number, name: string) => void;

    onViewCustomers: (id: number, name: string) => void;
    onSetSourceOfTruth: (id: number, name: string) => void;
    onEdit: (integration: Integration) => void;
    onViewLogs: (integration: Integration) => void;
    onHealthCheck: (integration: Integration) => void;
    onRateLimit: (integration: Integration) => void;
    onHistoricalImport: (integration: Integration) => void;
    onViewHistoricalJobs: (integration: Integration) => void;
    onDelete: (integration: Integration) => void;
    onPreviewData: (integration: Integration) => void;
    onTestIntegration: (integration: Integration) => void;
    onRetryHistoricalImport?: (integration: Integration) => void;
    onCancelHistoricalImport?: (integration: Integration) => void;
    sourceOfTruthId: number | null;
};

export function IntegrationGrid({
    isLoading,
    items,
    syncingId,
    testingId,
    progress,
    settingSourceOfTruthId,
    onSync,

    onSetSourceOfTruth,
    onEdit,
    onViewLogs,
    onHealthCheck,
    onRateLimit,
    onHistoricalImport,
    onViewHistoricalJobs,
    onDelete,
    onPreviewData,
    onTestIntegration,
    onRetryHistoricalImport,
    onCancelHistoricalImport,
    sourceOfTruthId,
}: IntegrationGridProps) {
    return (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 items-stretch">
            {isLoading ? (
                <>
                    <StatCardSkeleton />
                    <StatCardSkeleton />
                    <StatCardSkeleton />
                </>
            ) : items.length === 0 ? (
                <div className="col-span-full">
                    <EmptyState
                        icon={Cable}
                        title="No Integrations Yet"
                        description="Connect your first integration to start syncing data and generating reports"
                    />
                </div>
            ) : (
                [...items]
                    .sort((a, b) =>
                        a.id === sourceOfTruthId
                            ? -1
                            : b.id === sourceOfTruthId
                                ? 1
                                : 0
                    )
                    .map((integration) => (
                        <IntegrationCard
                            key={integration.id}
                            integration={integration}
                            isSyncing={syncingId === integration.id}
                            isTesting={testingId === integration.id}
                            progress={progress}
                            isSettingSourceOfTruth={settingSourceOfTruthId === integration.id}
                            onSync={onSync}
                            onSetSourceOfTruth={onSetSourceOfTruth}
                            onEdit={onEdit}
                            onViewLogs={onViewLogs}
                            onHealthCheck={onHealthCheck}
                            onRateLimit={onRateLimit}
                            onHistoricalImport={onHistoricalImport}
                            onViewHistoricalJobs={onViewHistoricalJobs}
                            onDelete={onDelete}
                            onPreviewData={onPreviewData}
                            onTestIntegration={onTestIntegration}
                            onRetryHistoricalImport={onRetryHistoricalImport}
                            onCancelHistoricalImport={onCancelHistoricalImport}
                            isSourceOfTruth={sourceOfTruthId === integration.id}
                        />
                    ))
            )}


        </div>
    );
}
