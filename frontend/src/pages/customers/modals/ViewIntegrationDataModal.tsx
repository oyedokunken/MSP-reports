import { useState, useEffect, useCallback } from "react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import { Database } from "lucide-react";
import { api } from "@/lib/api";

interface ViewIntegrationDataModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    integrationId: number | null;
    integrationName: string;
    integrationCustomerId?: number | null;
}

interface PreviewSection {
    title: string;
    type: 'table' | 'key_value' | 'list';
    data?: unknown;
    columns?: string[];
}

interface PreviewData {
    success: boolean;
    data?: unknown;
    sections?: PreviewSection[];
    message?: string;
}

export function ViewIntegrationDataModal({
    open,
    onOpenChange,
    integrationId,
    integrationName,
    integrationCustomerId,
}: ViewIntegrationDataModalProps) {
    const [isLoading, setIsLoading] = useState(false);
    const [previewData, setPreviewData] = useState<PreviewData | null>(null);
    const [error, setError] = useState("");

    const fetchPreviewData = useCallback(async () => {
        if (!integrationId) return;

        setIsLoading(true);
        setError("");

        try {
            const url = integrationCustomerId
                ? `/integrations/${integrationId}/preview?customer_id=${integrationCustomerId}`
                : `/integrations/${integrationId}/preview`;

            const response = await api.get(url);
            const responseData = response as { data?: PreviewData } | PreviewData;
            const data = (responseData as { data?: PreviewData }).data || responseData as PreviewData;

            if (data.success && data.sections) { // Check for sections directly on data if success is true
                setPreviewData(data);
            } else if ((data as unknown as { preview: PreviewData }).preview) { // Handle legacy structure if needed
                setPreviewData((data as unknown as { preview: PreviewData }).preview);
            } else {
                setPreviewData(data);
            }
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string };
            const errorMessage =
                error.response?.data?.message ||
                error.message ||
                "Failed to load integration data";
            setError(errorMessage);
        } finally {
            setIsLoading(false);
        }
    }, [integrationId, integrationCustomerId]);

    useEffect(() => {
        if (open && integrationId) {
            fetchPreviewData();
        }
    }, [open, integrationId, integrationCustomerId, fetchPreviewData]);

    const handleClose = () => {
        onOpenChange(false);
        setPreviewData(null);
        setError("");
    };

    const renderTableSection = (section: PreviewSection) => {
        if (!section.data || !Array.isArray(section.data)) return null;

        const columns = section.columns || (section.data.length > 0 ? Object.keys(section.data[0]) : []);

        return (
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            {columns.map((col) => (
                                <th
                                    key={col}
                                    className="text-left p-2 font-semibold text-foreground"
                                >
                                    {col}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {(section.data as Record<string, unknown>[]).map((row, idx) => (
                            <tr key={idx} className="border-b border-border/50">
                                {columns.map((col) => (
                                    <td key={col} className="p-2 text-muted-foreground">
                                        {typeof row[col] === 'object'
                                            ? JSON.stringify(row[col])
                                            : String(row[col] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    };

    const renderKeyValueSection = (section: PreviewSection) => {
        if (!section.data || typeof section.data !== 'object') return null;

        return (
            <div className="space-y-2">
                {Object.entries(section.data as Record<string, unknown>).map(([key, value]) => (
                    <div key={key} className="flex items-start gap-4">
                        <div className="font-medium text-foreground min-w-[150px]">
                            {key}:
                        </div>
                        <div className="text-muted-foreground flex-1">
                            {typeof value === 'object'
                                ? JSON.stringify(value, null, 2)
                                : String(value ?? '')}
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    const renderListSection = (section: PreviewSection) => {
        if (!section.data || !Array.isArray(section.data)) return null;

        return (
            <ul className="space-y-1 list-disc list-inside">
                {(section.data as unknown[]).map((item, idx) => (
                    <li key={idx} className="text-muted-foreground">
                        {typeof item === 'object' ? JSON.stringify(item) : String(item)}
                    </li>
                ))}
            </ul>
        );
    };

    const renderSection = (section: PreviewSection, idx: number) => {
        return (
            <div key={idx} className="rounded-lg border border-border p-4 space-y-3">
                <h3 className="font-semibold text-foreground">{section.title}</h3>
                {section.type === 'table' && renderTableSection(section)}
                {section.type === 'key_value' && renderKeyValueSection(section)}
                {section.type === 'list' && renderListSection(section)}
            </div>
        );
    };

    const renderPreviewData = () => {
        if (!previewData) return null;

        // If there are sections, render them
        if (previewData.sections && Array.isArray(previewData.sections)) {
            return (
                <div className="space-y-4">
                    {previewData.sections.map((section, idx) => renderSection(section, idx))}
                </div>
            );
        }

        // Otherwise, render data as JSON
        if (previewData.data) {
            return (
                <div className="rounded-lg border border-border p-4">
                    <pre className="text-xs overflow-auto max-h-96">
                        {JSON.stringify(previewData.data, null, 2)}
                    </pre>
                </div>
            );
        }

        return (
            <div className="text-center py-8 text-muted-foreground">
                <Database className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No data available</p>
            </div>
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-4xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <Database className="h-5 w-5 text-primary" />
                        <DialogTitle>Integration Data Preview</DialogTitle>
                    </div>
                    <DialogDescription>
                        Viewing data from {integrationName}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Spinner className="h-8 w-8" />
                        </div>
                    ) : error ? (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
                            <p className="text-sm text-destructive font-medium">
                                {error}
                            </p>
                        </div>
                    ) : (
                        renderPreviewData()
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={handleClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
