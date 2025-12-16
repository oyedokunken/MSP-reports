import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Cable } from "lucide-react";
import { StatCardSkeleton } from "@/components/ui/LoadingStates";
import type { Integration } from "../types";
import { useNavigate } from "react-router-dom";

type IntegrationsSummaryProps = {
    isLoading: boolean;
    integrations: Integration[];
    getStatusBadge: (status: string, syncStatus: string) => string;
    getStatusText: (status: string, syncStatus: string) => string;
    getStatusIcon: (status: string, syncStatus: string) => React.ReactNode;
};

export function IntegrationsSummary({
    isLoading,
    integrations,
    getStatusBadge,
    getStatusText,
    getStatusIcon,
}: IntegrationsSummaryProps) {
    const navigate = useNavigate();

    return (
        <AnimatedCard className="p-6 min-w-0 flex flex-col h-full">
            <div className="flex items-center justify-between flex-shrink-0">
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

            <div className="mt-4 space-y-3 overflow-y-auto flex-1 min-h-0 pr-2 scrollbar-hide">
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
                            className={`rounded-lg border p-4 ${
                                intg.isSourceOfTruth
                                    ? "bg-blue-50 dark:bg-blue-950/20 border-blue-500/50 ring-1 ring-blue-500/30"
                                    : "border-border"
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
                                            {intg.isSourceOfTruth && (
                                                <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                                    Source of Truth
                                                </span>
                                            )}
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
                            </div>
                        </div>
                    ))
                )}
            </div>
        </AnimatedCard>
    );
}
