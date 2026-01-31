import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Users, RefreshCw, FileText, Send } from "lucide-react";
import { useNavigate } from "react-router-dom";

type QuickActionsCardProps = {
    onInfo: (title: string, description?: string) => void;
};

const quickActions = [
    {
        icon: Users,
        label: "Review Mappings",
        description: "Customer mapping workspace",
        color: "bg-blue-500/10 text-blue-600 dark:text-blue-400",
        hoverColor: "hover:bg-blue-500/20",
        path: "/platform/customers",
    },
    {
        icon: RefreshCw,
        label: "Sync All",
        description: "Sync all integrations",
        color: "bg-green-500/10 text-green-600 dark:text-green-400",
        hoverColor: "hover:bg-green-500/20",
        path: "/platform/integrations",
    },
    {
        icon: FileText,
        label: "Manage Templates",
        description: "Report templates",
        color: "bg-purple-500/10 text-purple-600 dark:text-purple-400",
        hoverColor: "hover:bg-purple-500/20",
        path: "/platform/reports",
    },
    {
        icon: Send,
        label: "Delivery Settings",
        description: "Configure report schedules",
        color: "bg-orange-500/10 text-orange-600 dark:text-orange-400",
        hoverColor: "hover:bg-orange-500/20",
        path: "/platform/schedules",
    },
];

export function QuickActionsCard({ onInfo }: QuickActionsCardProps) {
    const navigate = useNavigate();

    const handleAction = (action: typeof quickActions[0]) => {
        if (action.path) {
            navigate(action.path);
        } else {
            onInfo("Coming Soon", action.description);
        }
    };

    return (
        <AnimatedCard className="p-6">
            <h2 className="text-lg font-semibold text-foreground">
                Quick Actions
            </h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {quickActions.map((action) => {
                    const Icon = action.icon;
                    return (
                        <button
                            key={action.label}
                            onClick={() => handleAction(action)}
                            className="rounded-lg border border-border/50 bg-background p-4 text-left hover:bg-muted transition-colors"
                        >
                            <div className={`inline-flex rounded-lg p-2 ${action.color} mb-3`}>
                                <Icon className="h-5 w-5" />
                            </div>
                            <p className="text-sm font-semibold text-foreground">
                                {action.label}
                            </p>
                        </button>
                    );
                })}
            </div>
        </AnimatedCard>
    );
}
