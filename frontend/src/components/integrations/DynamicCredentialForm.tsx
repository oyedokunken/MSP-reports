import React, { useState } from "react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { HelpCircle, Eye, EyeOff, ClipboardPaste } from "lucide-react";
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
    hidden?: boolean;
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
    const [visibleFields, setVisibleFields] = useState<Record<string, boolean>>({});

    const toggleVisibility = (fieldName: string) => {
        setVisibleFields(prev => ({
            ...prev,
            [fieldName]: !prev[fieldName]
        }));
    };

    const handlePaste = async (fieldName: string) => {
        try {
            const text = await navigator.clipboard.readText();
            onChange(fieldName, text);
        } catch (err) {
            console.error('Failed to read clipboard:', err);
        }
    };

    const isSecretField = (field: CredentialField) => {
        return field.type === "password" || 
               field.name.toLowerCase().includes('secret') ||
               field.name.toLowerCase().includes('key') ||
               field.name.toLowerCase().includes('password') ||
               field.name.toLowerCase().includes('token');
    };

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
                        <div className="flex gap-1">
                            <div className="relative flex-1">
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    type={isSecretField(field) && !visibleFields[field.name] ? "password" : "text"}
                                    placeholder={field.placeholder}
                                    value={values[field.name] || field.default || ""}
                                    onChange={(e) =>
                                        onChange(field.name, e.target.value)
                                    }
                                    required={field.required}
                                    className={`pr-10 ${errors[field.name] ? "border-red-500" : ""}`}
                                />
                                {isSecretField(field) && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="absolute right-0 top-0 h-full px-3 hover:bg-transparent"
                                        onClick={() => toggleVisibility(field.name)}
                                    >
                                        {visibleFields[field.name] ? (
                                            <EyeOff className="h-4 w-4 text-muted-foreground" />
                                        ) : (
                                            <Eye className="h-4 w-4 text-muted-foreground" />
                                        )}
                                    </Button>
                                )}
                            </div>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="shrink-0"
                                            onClick={() => handlePaste(field.name)}
                                        >
                                            <ClipboardPaste className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Paste from clipboard</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
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
