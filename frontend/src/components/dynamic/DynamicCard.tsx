import React from "react";
import type { CardConfig } from "@/types/displayTemplate";
import { replaceTemplateVariables } from "@/utils/formatters";
import * as Icons from "lucide-react";

interface DynamicCardProps {
    config: CardConfig;
    data: Record<string, unknown>[];
}

export const DynamicCard: React.FC<DynamicCardProps> = ({ config, data }) => {
    const getIcon = (iconName: string) => {
        const Icon = (Icons as unknown as Record<string, React.ComponentType<{ className?: string }>>)[iconName];
        return Icon ? <Icon className="w-5 h-5" /> : null;
    };

    const containerClass =
        config.layout === "grid"
            ? `grid gap-4 grid-cols-${config.columns || 3}`
            : "space-y-4";

    return (
        <div className={containerClass}>
            {data.map((item, index) => {
                const title = replaceTemplateVariables(
                    config.cardTemplate.title,
                    item
                );
                const subtitle = config.cardTemplate.subtitle
                    ? replaceTemplateVariables(
                        config.cardTemplate.subtitle,
                        item
                    )
                    : null;
                const content = config.cardTemplate.content
                    ? replaceTemplateVariables(
                        config.cardTemplate.content,
                        item
                    )
                    : null;
                const footer = config.cardTemplate.footer
                    ? replaceTemplateVariables(config.cardTemplate.footer, item)
                    : null;
                const iconName = config.cardTemplate.icon
                    ? replaceTemplateVariables(config.cardTemplate.icon, item)
                    : null;

                return (
                    <div
                        key={String((item as Record<string, unknown>).id ?? index)}
                        className="p-6 bg-white border border-gray-200 rounded-lg hover:border-gray-300 transition-colors"
                    >
                        {iconName && (
                            <div className="mb-4 text-blue-600">
                                {getIcon(iconName)}
                            </div>
                        )}
                        <h3 className="text-lg font-semibold text-gray-900">
                            {title}
                        </h3>
                        {subtitle && (
                            <p className="mt-1 text-sm text-gray-500">
                                {subtitle}
                            </p>
                        )}
                        {content && (
                            <p className="mt-4 text-sm text-gray-700">
                                {content}
                            </p>
                        )}
                        {footer && (
                            <div className="mt-4 pt-4 border-t border-gray-200">
                                <p className="text-xs text-gray-500">
                                    {footer}
                                </p>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
};
