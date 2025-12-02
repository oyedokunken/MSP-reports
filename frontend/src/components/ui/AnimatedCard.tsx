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
            hoverLift={false}
            className={`p-6 ${
                className ?? ""
            }`}
        >
            <div className="flex items-center justify-between">
                <div className="flex-1">
                    <p className="text-sm font-medium text-muted-foreground">
                        {name}
                    </p>
                    <p className="mt-2 text-3xl font-bold text-foreground">
                        {value}
                    </p>
                    {trend && (
                        <p
                            className={`mt-2 text-xs font-medium ${
                                trend.isPositive
                                    ? "text-green-600 dark:text-green-400"
                                    : "text-orange-600 dark:text-orange-400"
                            }`}
                        >
                            {trend.value}
                        </p>
                    )}
                </div>
                <div className="rounded-lg bg-primary/10 p-3">
                    <Icon className="h-6 w-6 text-primary" />
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
    color?: "green" | "yellow" | "red" | "blue";
}) {
    const colorClasses = {
        green: "bg-green-500",
        yellow: "bg-yellow-500",
        red: "bg-red-500",
        blue: "bg-blue-500",
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
