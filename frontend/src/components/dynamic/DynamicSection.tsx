import React, { useState } from "react";
import type { TemplateSection } from "@/types/displayTemplate";
import { getValueByPath } from "@/utils/formatters";
import { DynamicTable } from "./DynamicTable";
import { DynamicList } from "./DynamicList";
import { DynamicCard } from "./DynamicCard";
import { DynamicGrid } from "./DynamicGrid";
import { DynamicKeyValue } from "./DynamicKeyValue";
import { ChevronDown, ChevronRight } from "lucide-react";
import * as Icons from "lucide-react";

interface DynamicSectionProps {
    section: TemplateSection;
    data: Record<string, any>;
    onSelectionChange?: (sectionId: string, selectedIds: string[]) => void;
}

export const DynamicSection: React.FC<DynamicSectionProps> = ({
    section,
    data,
    onSelectionChange,
}) => {
    const [isCollapsed, setIsCollapsed] = useState(
        section.defaultCollapsed || false
    );

    // Get data for this section
    const sectionData = section.dataPath
        ? getValueByPath(data, section.dataPath)
        : data;

    // Check if data is empty
    const isEmpty =
        !sectionData ||
        (Array.isArray(sectionData) && sectionData.length === 0) ||
        (typeof sectionData === "object" &&
            Object.keys(sectionData).length === 0);

    const getIcon = (iconName: string) => {
        const Icon = (Icons as any)[iconName];
        return Icon ? <Icon className="w-5 h-5" /> : null;
    };

    const renderComponent = () => {
        if (isEmpty) {
            return (
                <div className="p-8 text-center text-muted-foreground">
                    {section.emptyMessage || "No data available"}
                </div>
            );
        }

        switch (section.component) {
            case "table":
                return (
                    <DynamicTable
                        config={section.config as any}
                        data={Array.isArray(sectionData) ? sectionData : []}
                        onSelectionChange={(ids) =>
                            onSelectionChange?.(section.id, ids)
                        }
                    />
                );

            case "list":
                return (
                    <DynamicList
                        config={section.config as any}
                        data={Array.isArray(sectionData) ? sectionData : []}
                    />
                );

            case "card":
                return (
                    <DynamicCard
                        config={section.config as any}
                        data={Array.isArray(sectionData) ? sectionData : []}
                    />
                );

            case "grid":
                return (
                    <DynamicGrid
                        config={section.config as any}
                        data={sectionData}
                    />
                );

            case "keyvalue":
                return (
                    <DynamicKeyValue
                        config={section.config as any}
                        data={sectionData}
                    />
                );

            case "chart":
                return (
                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                        Chart component coming soon
                    </div>
                );

            case "metric":
                return (
                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                        Metric component coming soon
                    </div>
                );

            case "timeline":
                return (
                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                        Timeline component coming soon
                    </div>
                );

            case "tree":
                return (
                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                        Tree component coming soon
                    </div>
                );

            default:
                return (
                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                        Unknown component type: {section.component}
                    </div>
                );
        }
    };

    return (
        <div className="bg-card dark:bg-[hsl(var(--auth-card))] border border-border rounded-lg overflow-hidden">
            <div
                className={`px-6 py-4 border-b border-border ${section.collapsible ? "cursor-pointer hover:bg-muted/50" : ""
                    }`}
                onClick={() =>
                    section.collapsible && setIsCollapsed(!isCollapsed)
                }
            >
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {section.icon && (
                            <div className="text-muted-foreground">
                                {getIcon(section.icon)}
                            </div>
                        )}
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                {section.title}
                            </h2>
                            {section.description && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {section.description}
                                </p>
                            )}
                        </div>
                    </div>
                    {section.collapsible && (
                        <div className="text-muted-foreground">
                            {isCollapsed ? (
                                <ChevronRight className="w-5 h-5" />
                            ) : (
                                <ChevronDown className="w-5 h-5" />
                            )}
                        </div>
                    )}
                </div>
            </div>

            {!isCollapsed && <div className="p-6">{renderComponent()}</div>}
        </div>
    );
};
