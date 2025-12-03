import React from "react";
import type { GridConfig } from "@/types/displayTemplate";
import { formatValue, getValueByPath } from "@/utils/formatters";
import * as Icons from "lucide-react";

interface DynamicGridProps {
    config: GridConfig;
    data: Record<string, any>;
}

export const DynamicGrid: React.FC<DynamicGridProps> = ({ config, data }) => {
    const getIcon = (iconName: string) => {
        const Icon = (Icons as any)[iconName];
        return Icon ? <Icon className="w-6 h-6" /> : null;
    };

    const getColorClass = (color: string) => {
        const colorMap: Record<string, string> = {
            blue: "text-blue-600 bg-blue-100",
            green: "text-green-600 bg-green-100",
            red: "text-red-600 bg-red-100",
            yellow: "text-yellow-600 bg-yellow-100",
            purple: "text-purple-600 bg-purple-100",
            orange: "text-orange-600 bg-orange-100",
            gray: "text-gray-600 bg-gray-100",
        };
        return colorMap[color] || colorMap.gray;
    };

    return (
        <div
            className="grid gap-4"
            style={{
                gridTemplateColumns: `repeat(${config.columns}, minmax(0, 1fr))`,
            }}
        >
            {config.metrics.map((metric) => {
                const value = getValueByPath(data, metric.valuePath);
                const formatted = formatValue(value, metric.format);
                const colorClass = getColorClass(metric.color || "gray");

                return (
                    <div
                        key={metric.id}
                        className="p-6 bg-card dark:bg-[hsl(var(--auth-card))] border border-border rounded-lg hover:border-primary/50 transition-colors"
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="text-sm font-medium text-muted-foreground">
                                    {metric.label}
                                </p>
                                <p className="mt-2 text-3xl font-semibold text-foreground">
                                    {formatted}
                                </p>
                            </div>
                            {metric.icon && (
                                <div className={`p-3 rounded-lg ${colorClass}`}>
                                    {getIcon(metric.icon)}
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
};
