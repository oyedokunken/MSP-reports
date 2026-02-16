import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Calendar, FolderOpen, ArrowLeft, FolderClosed } from "lucide-react";
import { SchedulesGrid } from "./SchedulesGrid";
import type { ReportSchedule } from "../types/schedule";

interface SchedulesFoldersProps {
    schedules: ReportSchedule[];
    isLoading: boolean;
    onConfigure: (customerId: number) => void;
    onDelete: (scheduleId: number) => void;
    onCreateSchedule: () => void;
    searchQuery: string;
}

export function SchedulesFolders({
    schedules,
    isLoading,
    onConfigure,
    onDelete,
    onCreateSchedule,
    searchQuery,
}: SchedulesFoldersProps) {
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

    if (schedules.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-12 border rounded-lg bg-card shadow-sm">
                <Calendar className="h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-lg font-medium">No Scheduled Reports</p>
                <p className="text-sm text-muted-foreground mb-4">
                    Configure report schedules for your customers
                </p>
                <Button onClick={onCreateSchedule}>
                    <Calendar className="h-4 w-4 mr-2" />
                    Go to Customers
                </Button>
            </div>
        );
    }

    // Group schedules by customer
    const groupedSchedules = schedules.reduce((acc, schedule) => {
        const key = schedule.customer_name;
        if (!acc[key]) {
            acc[key] = [];
        }
        acc[key].push(schedule);
        return acc;
    }, {} as Record<string, ReportSchedule[]>);

    const sortedCustomers = Object.keys(groupedSchedules)
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
                            {groupedSchedules[selectedCustomer].length}
                        </span>
                    </div>
                </div>

                <SchedulesGrid
                    schedules={groupedSchedules[selectedCustomer]}
                    isLoading={false}
                    onConfigure={onConfigure}
                    onDelete={onDelete}
                    onCreateSchedule={onCreateSchedule}
                />
            </div>
        );
    }

    // Render grid of folder icons
    return (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2">
            {sortedCustomers.map((customerName) => {
                const customerSchedules = groupedSchedules[customerName];
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
                                {customerSchedules.length}
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
