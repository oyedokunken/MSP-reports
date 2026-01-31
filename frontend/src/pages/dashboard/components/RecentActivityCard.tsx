import { AnimatedCard, PulsingDot } from "@/components/ui/AnimatedCard";
import { Activity, Users, RefreshCw, FileText, Send } from "lucide-react";
import { ActivityCardSkeleton, EmptyState } from "@/components/ui/LoadingStates";
import type { RecentActivity } from "../types";
import { useNavigate } from "react-router-dom";

type RecentActivityCardProps = {
    isLoading: boolean;
    items: RecentActivity[];
    onInfo: (title: string, description?: string) => void;
};

const quickActions = [
    {
        icon: Users,
        label: "Review Mappings",
        description: "Customer mapping workspace",
        color: "bg-blue-500/10 text-blue-600 dark:text-blue-400",
        path: "/platform/customers",
    },
    {
        icon: RefreshCw,
        label: "Sync All",
        description: "Sync all integrations",
        color: "bg-green-500/10 text-green-600 dark:text-green-400",
        path: "/platform/integrations",
    },
    {
        icon: FileText,
        label: "Manage Templates",
        description: "Report templates",
        color: "bg-purple-500/10 text-purple-600 dark:text-purple-400",
        path: "/platform/reports",
    },
    {
        icon: Send,
        label: "Delivery Settings",
        description: "Configure report schedules",
        color: "bg-orange-500/10 text-orange-600 dark:text-orange-400",
        path: "/platform/schedules",
    },
];

export function RecentActivityCard({ isLoading, items, onInfo }: RecentActivityCardProps) {
    const navigate = useNavigate();

    const handleAction = (action: typeof quickActions[0]) => {
        if (action.path) {
            navigate(action.path);
        } else {
            onInfo("Coming Soon", action.description);
        }
    };

    return (
        <AnimatedCard className="p-6 min-w-0 flex flex-col h-full">
            <div className="flex items-center justify-between mb-4 flex-shrink-0">
                <h2 className="text-lg font-semibold text-foreground">
                    Quick Actions
                </h2>
            </div>

            <div className="grid gap-3 grid-cols-4 mb-6 flex-shrink-0">
                {quickActions.map((action) => {
                    const Icon = action.icon;
                    return (
                        <button
                            key={action.label}
                            onClick={() => handleAction(action)}
                            className="rounded-lg border border-border/50 bg-background p-3 text-left hover:bg-muted transition-colors"
                        >
                            <div className={`inline-flex rounded-lg p-2 ${action.color} mb-2`}>
                                <Icon className="h-4 w-4" />
                            </div>
                            <p className="text-sm font-semibold text-foreground">
                                {action.label}
                            </p>
                        </button>
                    );
                })}
            </div>

            <div className="border-t border-border/50 mb-4 flex-shrink-0"></div>

            <div className="flex items-center justify-between mb-4 flex-shrink-0">
                <h2 className="text-lg font-semibold text-foreground">
                    Recent Activity
                </h2>
                <button
                    onClick={() => navigate("/platform/notifications")}
                    className="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1 transition-colors"
                >
                    View all
                </button>
            </div>

            <div className="overflow-y-auto flex-1 min-h-0 pr-2">
                {isLoading ? (
                    <ActivityCardSkeleton />
                ) : items.length === 0 ? (
                    <EmptyState
                        icon={Activity}
                        title="No activity yet"
                        description="Once you start syncing data and generating reports, activity will show up here."
                    />
                ) : (
                    <div className="divide-y divide-border/50">
                        {items.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between gap-2 py-3"
                            >
                                <div className="flex items-center gap-3 min-w-0 flex-1">
                                    <div className="flex-shrink-0">
                                        <PulsingDot color={item.severity} />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-foreground truncate">
                                            {item.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground truncate">
                                            {item.time}
                                        </p>
                                    </div>
                                </div>
                                <span className="text-xs rounded-full bg-muted px-2 py-1 whitespace-nowrap flex-shrink-0">
                                    {item.type}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AnimatedCard>
    );
}
