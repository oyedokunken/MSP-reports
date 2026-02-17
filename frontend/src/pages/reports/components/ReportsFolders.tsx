import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { FileText, FolderOpen, ArrowLeft, FolderClosed, Users } from "lucide-react";
import { ReportsGrid } from "./ReportsGrid";
import type { GeneratedReport } from "../types/report";
import { Card, CardContent } from "@/components/ui/card";

interface ReportsFoldersProps {
    reports: GeneratedReport[];
    isLoading: boolean;
    downloading: number | null;
    onDownload: (reportId: number) => void;
    onDelete?: (reportId: number) => void;
    deleting?: number | null | string;
    onResend?: (reportId: number) => void;
    resending?: number | null;
    selectedIds?: number[];
    onToggleSelection?: (reportId: number) => void;
    onBulkGenerate?: () => void;
    searchQuery: string;
}

export function ReportsFolders({
    reports,
    isLoading,
    downloading,
    onDownload,
    onDelete,
    deleting,
    onResend,
    resending,
    selectedIds = [],
    onToggleSelection,
    onBulkGenerate,
    searchQuery,
}: ReportsFoldersProps) {
    const [selectedCustomer, setSelectedCustomer] = useState<string | null>(null);

    if (isLoading) {
        return (
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2">
                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16].map((i) => (
                    <div key={i} className="flex flex-col items-center gap-2 p-2 rounded-lg text-center">
                        <Skeleton className="h-32 w-32 rounded-lg" />
                        <Skeleton className="h-4 w-24" />
                    </div>
                ))}
            </div>
        );
    }

    if (reports.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                    <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                    <p className="text-lg font-medium text-muted-foreground">No reports found</p>
                    <p className="text-sm text-muted-foreground mt-1 mb-4">
                        Try adjusting your filters or generate a new report
                    </p>
                    {onBulkGenerate && (
                        <Button onClick={onBulkGenerate}>
                            <Users className="h-4 w-4 mr-2" />
                            Bulk Generate
                        </Button>
                    )}
                </CardContent>
            </Card>
        );
    }

    // Group reports by customer
    const groupedReports = reports.reduce((acc, report) => {
        const key = report.customer_name;
        if (!acc[key]) {
            acc[key] = [];
        }
        acc[key].push(report);
        return acc;
    }, {} as Record<string, GeneratedReport[]>);

    const sortedCustomers = Object.keys(groupedReports)
        .sort()
        .filter(customerName =>
            customerName.toLowerCase().includes(searchQuery.toLowerCase())
        );

    // Render detailed view for a selected customer
    if (selectedCustomer) {
        return (
            <div className="space-y-4">
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedCustomer(null)}
                        className="gap-1 pl-2 text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Folders
                    </Button>
                    <div className="h-4 w-px bg-border mx-2" />
                    <div className="flex items-center gap-2">
                        <FolderOpen className="h-4 w-4 text-primary" />
                        <span className="font-semibold text-lg">{selectedCustomer}</span>
                        <span className="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
                            {groupedReports[selectedCustomer].length}
                        </span>
                    </div>
                </div>

                <ReportsGrid
                    reports={groupedReports[selectedCustomer]}
                    isLoading={false}
                    downloading={downloading}
                    onDownload={onDownload}
                    onDelete={onDelete}
                    deleting={deleting}
                    onResend={onResend}
                    resending={resending}
                    selectedIds={selectedIds}
                    onToggleSelection={onToggleSelection}
                />
            </div>
        );
    }

    // Render grid of folder icons
    return (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2">
            {sortedCustomers.map((customerName) => {
                const customerReports = groupedReports[customerName];
                return (
                    <div
                        key={customerName}
                        className="flex flex-col items-center gap-2 p-2 rounded-lg cursor-pointer text-center"
                        onClick={() => setSelectedCustomer(customerName)}
                    >
                        <div className="relative group">
                            <FolderClosed
                                className="h-32 w-32 text-primary transition-transform duration-200 ease-in-out group-hover:scale-110 fill-primary/30"
                                strokeWidth={0.7}
                            />
                            <div className="absolute top-4 right-1 bg-background border border-border text-xs font-bold px-2 py-0.5 rounded-full shadow-sm min-w-[24px] text-center">
                                {customerReports.length}
                            </div>
                        </div>
                        <div className="space-y-0.5 max-w-full">
                            <h3 className="font-medium text-base truncate max-w-[200px]" title={customerName}>
                                {customerName}
                            </h3>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
