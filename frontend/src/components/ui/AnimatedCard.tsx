import { type ReactNode } from "react";

/**
 * Animated Card Component
 * Adds subtle hover and interaction animations
 */

interface AnimatedCardProps {
    children: ReactNode;
    onClick?: () => void;
    className?: string;
    hoverScale?: boolean;
    hoverLift?: boolean;
}

export function AnimatedCard({
    children,
    onClick,
    className = "",
    hoverScale = false,
    hoverLift = true,
}: AnimatedCardProps) {
    const baseClasses =
        "rounded-lg border border-border bg-card dark:bg-[hsl(var(--auth-card))] text-card-foreground shadow-sm transition-all duration-300";

    const interactionClasses = onClick ? "cursor-pointer" : "";

    const animationClasses = [
        hoverLift && "hover:shadow-md hover:-translate-y-0.5",
        hoverScale && "hover:scale-[1.02]",
    ]
        .filter(Boolean)
        .join(" ");

    return (
        <div
            onClick={onClick}
            className={`${baseClasses} ${interactionClasses} ${animationClasses} ${className}`}
        >
            {children}
        </div>
    );
}

/**
 * Animated Stat Card with count-up animation
 */

interface AnimatedStatCardProps {
    name: string;
    value: string;
    icon: any;
    trend?: {
        value: string;
        isPositive: boolean;
    };
    className?: string;
}

export function AnimatedStatCard({
    name,
    value,
    icon: Icon,
    trend,
    className,
}: AnimatedStatCardProps) {
    return (
        <AnimatedCard
            className={`p-6 group shadow-[0_1px_0_rgba(0,0,0,0.2),0_8px_24px_rgba(0,0,0,0.18)] dark:shadow-[0_1px_0_rgba(0,0,0,0.35),0_12px_28px_rgba(0,0,0,0.28)] ${
                className ?? ""
            }`}
        >
            <div className="flex items-center justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-muted-foreground">
                        {name}
                    </p>
                    <p className="mt-2 text-3xl font-bold text-foreground transition-transform group-hover:scale-105">
                        {value}
                    </p>
                    {trend && (
                        <p
                            className={`mt-2 text-xs font-medium flex items-center gap-1 ${
                                trend.isPositive
                                    ? "text-green-600 dark:text-green-400"
                                    : "text-red-600 dark:text-red-400"
                            }`}
                        >
                            <span>{trend.isPositive ? "↑" : "↓"}</span>
                            <span>{trend.value}</span>
                        </p>
                    )}
                </div>
                <div className="rounded-lg bg-accent/40 p-3 transition-transform group-hover:scale-110 group-hover:rotate-3">
                    <Icon className="h-6 w-6 text-accent-foreground" />
                </div>
            </div>
        </AnimatedCard>
    );
}

/**
 * Pulse Animation for notifications
 */

export function PulsingDot({
    color = "green",
}: {
    color?: "green" | "yellow" | "red";
}) {
    const colorClasses = {
        green: "bg-green-500",
        yellow: "bg-yellow-500",
        red: "bg-red-500",
    };

    return (
        <span className="relative flex h-3 w-3">
            <span
                className={`animate-ping absolute inline-flex h-full w-full rounded-full ${colorClasses[color]} opacity-75`}
            />
            <span
                className={`relative inline-flex rounded-full h-3 w-3 ${colorClasses[color]}`}
            />
        </span>
    );
}
