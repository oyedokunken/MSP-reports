import React from "react";
import type { KeyValueConfig } from "@/types/displayTemplate";
import { formatValue, getValueByPath } from "@/utils/formatters";

interface DynamicKeyValueProps {
    config: KeyValueConfig;
    data: Record<string, any>;
}

export const DynamicKeyValue: React.FC<DynamicKeyValueProps> = ({
    config,
    data,
}) => {
    const isHorizontal = config.layout === "horizontal";

    return (
        <div
            className={`bg-white border border-gray-200 rounded-lg divide-y divide-gray-200`}
        >
            {config.pairs.map((pair, index) => {
                const value = getValueByPath(data, pair.valuePath);
                const formatted = formatValue(value, pair.format);

                return (
                    <div
                        key={pair.key || index}
                        className={`p-4 ${
                            isHorizontal
                                ? "flex items-center justify-between"
                                : "space-y-1"
                        }`}
                    >
                        <dt className="text-sm font-medium text-gray-500">
                            {pair.label}
                        </dt>
                        <dd
                            className={`text-sm text-gray-900 ${
                                isHorizontal ? "text-right" : "font-semibold"
                            }`}
                        >
                            {formatted}
                        </dd>
                    </div>
                );
            })}
        </div>
    );
};
