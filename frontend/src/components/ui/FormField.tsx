import { type ReactNode } from "react";
import { AlertCircle, CheckCircle2 } from "lucide-react";

/**
 * Enhanced Form Field Components
 * Provides validation feedback and accessibility
 */

interface FormFieldProps {
    label: string;
    error?: string;
    success?: string;
    hint?: string;
    required?: boolean;
    children: ReactNode;
}

export function FormField({
    label,
    error,
    success,
    hint,
    required,
    children,
}: FormFieldProps) {
    return (
        <div className="space-y-2">
            <label className="block text-sm font-medium text-foreground">
                {label}
                {required && <span className="text-red-500 ml-1">*</span>}
            </label>

            {children}

            {hint && !error && !success && (
                <p className="text-xs text-muted-foreground flex items-center gap-1">
                    {hint}
                </p>
            )}

            {error && (
                <p className="text-xs text-red-600 dark:text-red-400 flex items-center gap-1 animate-in slide-in-from-top-1">
                    <AlertCircle className="h-3 w-3" />
                    {error}
                </p>
            )}

            {success && (
                <p className="text-xs text-green-600 dark:text-green-400 flex items-center gap-1 animate-in slide-in-from-top-1">
                    <CheckCircle2 className="h-3 w-3" />
                    {success}
                </p>
            )}
        </div>
    );
}

interface TextInputProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    type?: "text" | "email" | "password" | "url";
    error?: boolean;
    success?: boolean;
    disabled?: boolean;
    prefix?: string;
    suffix?: string;
}

export function TextInput({
    value,
    onChange,
    placeholder,
    type = "text",
    error,
    success,
    disabled,
    prefix,
    suffix,
}: TextInputProps) {
    const borderColor = error
        ? "border-red-500 dark:border-red-400 focus:border-red-500 focus:ring-red-500"
        : success
        ? "border-green-500 dark:border-green-400 focus:border-green-500 focus:ring-green-500"
        : "border-input focus:border-ring focus:ring-ring";

    return (
        <div className="relative flex items-center">
            {prefix && (
                <span className="absolute left-3 text-sm text-muted-foreground">
                    {prefix}
                </span>
            )}

            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                disabled={disabled}
                className={`w-full rounded-md border ${borderColor} bg-background px-4 py-2 text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${
                    prefix ? "pl-8" : ""
                } ${suffix ? "pr-32" : ""}`}
            />

            {suffix && (
                <span className="absolute right-3 text-sm text-muted-foreground">
                    {suffix}
                </span>
            )}
        </div>
    );
}

/**
 * Subdomain Input with availability check
 */

interface SubdomainInputProps {
    value: string;
    onChange: (value: string) => void;
    isChecking?: boolean;
    isAvailable?: boolean;
}

export function SubdomainInput({
    value,
    onChange,
    isChecking,
    isAvailable,
}: SubdomainInputProps) {
    const handleChange = (val: string) => {
        // Only allow lowercase letters, numbers, and hyphens
        const sanitized = val.toLowerCase().replace(/[^a-z0-9-]/g, "");
        onChange(sanitized);
    };

    return (
        <FormField
            label="Subdomain"
            hint="This will be your unique URL"
            error={
                value && !isAvailable && !isChecking
                    ? "This subdomain is already taken"
                    : undefined
            }
            success={
                value && isAvailable ? "Subdomain is available!" : undefined
            }
            required
        >
            <div className="flex items-center gap-2">
                <TextInput
                    value={value}
                    onChange={handleChange}
                    placeholder="acme"
                    error={value ? !isAvailable && !isChecking : false}
                    success={value ? isAvailable : false}
                />
                <span className="text-muted-foreground whitespace-nowrap">
                    .reportingplatform.com
                </span>
            </div>
            {isChecking && (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <div className="h-3 w-3 animate-spin rounded-full border-2 border-gray-300 dark:border-gray-700 border-t-primary" />
                    Checking availability...
                </div>
            )}
        </FormField>
    );
}
