import React from "react";
import type { ListConfig } from "@/types/displayTemplate";
import { replaceTemplateVariables } from "@/utils/formatters";
import * as Icons from "lucide-react";

interface DynamicListProps<T extends Record<string, any>> {
    config: ListConfig;
    data: T[];
}

export const DynamicList = <T extends Record<string, any>>({
    config,
    data,
}: DynamicListProps<T>) => {
    const sortedData = React.useMemo(() => {
        if (!config.sortBy) return data;

        return [...data].sort((a, b) => {
            const key = config.sortBy as keyof T;
            const aValue = a[key];
            const bValue = b[key];
            if (aValue === bValue) return 0;
            const comparison = aValue < bValue ? -1 : 1;
            return config.sortOrder === "desc" ? -comparison : comparison;
        });
    }, [data, config.sortBy, config.sortOrder]);

    const groupedData = React.useMemo(() => {
        if (!config.groupBy) return { "All Items": sortedData };

        const key = config.groupBy as keyof T;

        return sortedData.reduce<Record<string, T[]>>((groups, item) => {
            const groupKey = (item[key] ?? "Other") as string;
            if (!groups[groupKey]) groups[groupKey] = [];
            groups[groupKey].push(item);
            return groups;
        }, {});
    }, [sortedData, config.groupBy]);

    const getIcon = (iconName: string) => {
        const IconsMap = Icons as unknown as Record<string, React.FC<any>>;
        const Icon = IconsMap[iconName];
        return Icon ? <Icon className="w-5 h-5" /> : null;
    };

    const getIconColor = (color: string) => {
        const colors: Record<string, string> = {
            red: "text-red-500",
            orange: "text-orange-500",
            yellow: "text-yellow-500",
            green: "text-green-500",
            blue: "text-blue-500",
            purple: "text-purple-500",
            gray: "text-gray-500",
        };
        return colors[color] ?? "text-gray-500";
    };

    const getBadgeColor = (badge: string) => {
        const colors: Record<string, string> = {
            critical: "bg-red-100 text-red-800",
            high: "bg-orange-100 text-orange-800",
            medium: "bg-yellow-100 text-yellow-800",
            low: "bg-blue-100 text-blue-800",
            info: "bg-gray-100 text-gray-800",
        };
        return colors[badge.toLowerCase()] ?? "bg-gray-100 text-gray-800";
    };

    return (
        <div className="space-y-6">
            {Object.entries(groupedData).map(([groupName, items]) => (
                <div key={groupName}>
                    {config.groupBy && (
                        <h3 className="mb-3 text-sm font-semibold text-gray-700 uppercase">
                            {groupName} ({items.length})
                        </h3>
                    )}

                    <div className="space-y-2">
                        {items.map((item, index) => {
                            const title = replaceTemplateVariables(
                                config.itemTemplate.title,
                                item
                            );
                            const subtitle = config.itemTemplate.subtitle
                                ? replaceTemplateVariables(
                                      config.itemTemplate.subtitle,
                                      item
                                  )
                                : null;

                            const iconName = config.itemTemplate.icon
                                ? replaceTemplateVariables(
                                      config.itemTemplate.icon,
                                      item
                                  )
                                : null;

                            const iconColor = config.itemTemplate.iconColor
                                ? replaceTemplateVariables(
                                      config.itemTemplate.iconColor,
                                      item
                                  )
                                : "gray";

                            const badge = config.itemTemplate.badge
                                ? replaceTemplateVariables(
                                      config.itemTemplate.badge,
                                      item
                                  )
                                : null;

                            return (
                                <div
                                    key={index}
                                    className="flex items-start gap-3 p-4 bg-white border border-gray-200 rounded-lg hover:border-gray-300 transition-colors"
                                >
                                    {iconName && (
                                        <div
                                            className={getIconColor(iconColor)}
                                        >
                                            {getIcon(iconName)}
                                        </div>
                                    )}

                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="text-sm font-medium text-gray-900">
                                                {title}
                                            </p>

                                            {badge && (
                                                <span
                                                    className={`px-2 py-1 text-xs font-medium rounded-full ${getBadgeColor(
                                                        badge
                                                    )}`}
                                                >
                                                    {badge}
                                                </span>
                                            )}
                                        </div>

                                        {subtitle && (
                                            <p className="mt-1 text-sm text-gray-500">
                                                {subtitle}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
};
