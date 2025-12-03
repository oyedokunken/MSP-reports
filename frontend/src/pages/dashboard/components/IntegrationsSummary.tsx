import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Cable, RefreshCw } from "lucide-react";
import { StatCardSkeleton, LoadingSpinner } from "@/components/ui/LoadingStates";
import type { Integration } from "../types";
import { useNavigate } from "react-router-dom";

type IntegrationsSummaryProps = {
    isLoading: boolean;
    integrations: Integration[];
    isSyncingId: number | null;
    syncProgress: number;
    onSync: (id: number, name: string) => void;
    getStatusBadge: (status: string, syncStatus: string) => string;
    getStatusText: (status: string, syncStatus: string) => string;
    getStatusIcon: (status: string, syncStatus: string) => React.ReactNode;
};

export function IntegrationsSummary({
    isLoading,
    integrations,
    isSyncingId,
    syncProgress,
    onSync,
    getStatusBadge,
    getStatusText,
    getStatusIcon,
}: IntegrationsSummaryProps) {
    const navigate = useNavigate();

    return (
        <AnimatedCard className="p-6 min-w-0">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-foreground">
                    Integrations
                </h2>
                <button
                    className="text-sm text-muted-foreground hover:text-foreground"
                    onClick={() => navigate("/platform/integrations")}
                >
                    View all
                </button>
            </div>

            <div className="mt-4 space-y-3">
                {isLoading ? (
                    <>
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                    </>
                ) : (
                    integrations.map((intg) => (
                        <div
                            key={intg.id}
                            className={`rounded-lg border border-border p-4 ${isSyncingId === intg.id
                                ? "bg-primary/5 ring-1 ring-primary/40"
                                : ""
                                }`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-start gap-3 min-w-0 flex-1">
                                    <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                                        <Cable className="h-5 w-5 text-primary" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <p className="font-medium text-foreground truncate">
                                                {intg.name}
                                            </p>
                                            <span
                                                className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ${getStatusBadge(
                                                    intg.status,
                                                    intg.syncStatus
                                                )}`}
                                            >
                                                {getStatusIcon(
                                                    intg.status,
                                                    intg.syncStatus
                                                )}
                                                {getStatusText(
                                                    intg.status,
                                                    intg.syncStatus
                                                )}
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-0.5 truncate">
                                            Last sync {intg.lastSync} •{" "}
                                            {intg.customers} customers
                                        </p>
                                    </div>
                                </div>
                                <button
                                    onClick={() => onSync(intg.id, intg.name)}
                                    disabled={isSyncingId === intg.id}
                                    className={`inline-flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-xs font-medium transition-all flex-shrink-0 ${isSyncingId === intg.id
                                        ? "bg-primary text-primary-foreground border-primary"
                                        : "bg-background text-foreground hover:bg-muted"
                                        } disabled:opacity-50 disabled:cursor-not-allowed`}
                                >
                                    {isSyncingId === intg.id ? (
                                        <LoadingSpinner size="sm" />
                                    ) : (
                                        <RefreshCw className="h-4 w-4" />
                                    )}
                                    {isSyncingId === intg.id
                                        ? `${Math.round(syncProgress)}%`
                                        : "Sync"}
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </AnimatedCard>
    );
}
