import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Cable } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import type { Integration } from "../types";
import { useNavigate } from "react-router-dom";

type IntegrationsSummaryProps = {
    isLoading: boolean;
    integrations: Integration[];
    getStatusBadge: (status: string, syncStatus: string) => string;
    getStatusText: (status: string, syncStatus: string) => string;
    getStatusIcon: (status: string, syncStatus: string) => React.ReactNode;
    className?: string;
};

export function IntegrationsSummary({
    isLoading,
    integrations,
    getStatusBadge,
    getStatusText,
    getStatusIcon,
    className = "",
}: IntegrationsSummaryProps) {
    const navigate = useNavigate();

    if (isLoading) {
        return (
            <AnimatedCard className={`p-6 min-w-0 flex flex-col h-full ${className}`} hoverLift={false}>
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center gap-2">
                        <Skeleton className="h-5 w-5 rounded" />
                        <Skeleton className="h-6 w-32" />
                        <Skeleton className="h-5 w-8 rounded-full" />
                    </div>
                    <Skeleton className="h-4 w-16" />
                </div>
                <div className="space-y-3 flex-1 overflow-hidden pr-2">
                    {[...Array(4)].map((_, i) => (
                        <div key={i} className="rounded-lg border border-border/50 p-4">
                            <div className="flex items-start gap-3">
                                <Skeleton className="h-9 w-9 rounded-lg flex-shrink-0" />
                                <div className="flex-1 space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Skeleton className="h-4 w-24" />
                                        <Skeleton className="h-4 w-20 rounded-full" />
                                    </div>
                                    <Skeleton className="h-3 w-40" />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </AnimatedCard>
        );
    }

    return (
        <AnimatedCard className={`p-6 min-w-0 flex flex-col h-full ${className}`}>
            <div className="flex items-center justify-between flex-shrink-0">
                <h2 className="text-base font-medium text-foreground flex items-center gap-2">
                    <Cable className="h-5 w-5 text-primary" />
                    <span>Integrations</span>
                    {!isLoading && (
                        <span className="text-sm font-normal text-muted-foreground bg-muted px-2 py-0.5 rounded-full ml-1">
                            {integrations.length}
                        </span>
                    )}
                </h2>
                <button
                    className="text-sm text-muted-foreground hover:text-foreground"
                    onClick={() => navigate("/platform/integrations")}
                >
                    View all
                </button>
            </div>

            <div className="mt-4 space-y-3 overflow-y-auto flex-1 min-h-0 pr-2 thin-scrollbar">
                {integrations.map((intg) => (
                    <div
                        key={intg.id}
                        className={`rounded-lg p-4 ${intg.isSourceOfTruth
                                ? "bg-blue-50/50 dark:bg-blue-950/20 border-2 border-blue-500/70 shadow-sm ring-1 ring-blue-500/10"
                                : "border border-border/50"
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
                ))}
            </div>
        </AnimatedCard>
    );
}
