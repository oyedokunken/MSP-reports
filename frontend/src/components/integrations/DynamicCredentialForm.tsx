import React from "react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { HelpCircle } from "lucide-react";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";

export interface CredentialField {
    name: string;
    label: string;
    type: "text" | "password" | "email" | "url" | "number" | "select";
    required: boolean;
    placeholder?: string;
    help?: string;
    default?: string;
    options?: Record<string, string>;
}

interface DynamicCredentialFormProps {
    fields: CredentialField[];
    values: Record<string, string>;
    onChange: (name: string, value: string) => void;
    errors?: Record<string, string>;
}

export const DynamicCredentialForm: React.FC<DynamicCredentialFormProps> = ({
    fields,
    values,
    onChange,
    errors = {},
}) => {
    return (
        <div className="space-y-4">
            {fields.map((field) => (
                <div key={field.name} className="space-y-2">
                    <div className="flex items-center gap-2">
                        <Label htmlFor={field.name}>
                            {field.label}
                            {field.required && (
                                <span className="text-red-500 ml-1">*</span>
                            )}
                        </Label>
                        {field.help && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <HelpCircle className="h-4 w-4 text-gray-400 cursor-help" />
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="max-w-xs">{field.help}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                    </div>
                    {field.type === "select" && field.options ? (
                        <Select
                            value={values[field.name] || field.default || ""}
                            onValueChange={(value) =>
                                onChange(field.name, value)
                            }
                        >
                            <SelectTrigger
                                className={
                                    errors[field.name] ? "border-red-500" : ""
                                }
                            >
                                <SelectValue
                                    placeholder={
                                        field.placeholder || "Select an option"
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(field.options).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    )
                                )}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            id={field.name}
                            name={field.name}
                            type={field.type}
                            placeholder={field.placeholder}
                            value={values[field.name] || field.default || ""}
                            onChange={(e) =>
                                onChange(field.name, e.target.value)
                            }
                            required={field.required}
                            className={
                                errors[field.name] ? "border-red-500" : ""
                            }
                        />
                    )}
                    {errors[field.name] && (
                        <p className="text-sm text-red-500">
                            {errors[field.name]}
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
};
