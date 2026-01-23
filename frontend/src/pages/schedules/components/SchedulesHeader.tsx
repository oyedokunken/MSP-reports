import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { 
    Calendar, 
    Search, 
    Filter, 
    LayoutGrid, 
    LayoutList 
} from "lucide-react";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

export type ViewMode = 'grid' | 'table';

interface SchedulesHeaderProps {
    onCreateSchedule: () => void;
    searchQuery: string;
    statusFilter: string;
    frequencyFilter: string;
    uniqueFrequencies: string[];
    onSearchChange: (query: string) => void;
    onStatusChange: (status: string) => void;
    onFrequencyChange: (frequency: string) => void;
    viewMode: ViewMode;
    onViewModeChange: (mode: ViewMode) => void;
}

function formatFrequency(frequency: string): string {
    return frequency.charAt(0).toUpperCase() + frequency.slice(1).toLowerCase();
}

export function SchedulesHeader({ 
    onCreateSchedule, 
    searchQuery, 
    statusFilter,
    frequencyFilter,
    uniqueFrequencies,
    onSearchChange,
    onStatusChange,
    onFrequencyChange,
    viewMode,
    onViewModeChange
}: SchedulesHeaderProps) {
    const activeFilterCount = (statusFilter !== 'all' ? 1 : 0) + (frequencyFilter !== 'all' ? 1 : 0);

    return (
        <div className="space-y-4">
            <div>
                <h1 className="text-3xl font-bold text-foreground">
                    Report Schedules
                </h1>
                <p className="mt-2 text-muted-foreground">
                    View and manage automated report generation schedules
                </p>
            </div>
            
            {/* Filters and Search */}
            <div className="flex flex-col md:flex-row gap-3">
                {/* Filter Dropdown */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="w-full md:w-auto h-10">
                            <Filter className="h-4 w-4 mr-2" />
                            Filters
                            {activeFilterCount > 0 && (
                                <span className="ml-2 px-2 py-0.5 text-xs bg-primary text-primary-foreground rounded-full">
                                    {activeFilterCount}
                                </span>
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-56">
                        {/* Status Section */}
                        <DropdownMenuLabel>Status</DropdownMenuLabel>
                        <DropdownMenuCheckboxItem
                            checked={statusFilter === 'active'}
                            onCheckedChange={() => onStatusChange(statusFilter === 'active' ? 'all' : 'active')}
                        >
                            Active
                        </DropdownMenuCheckboxItem>
                        <DropdownMenuCheckboxItem
                            checked={statusFilter === 'inactive'}
                            onCheckedChange={() => onStatusChange(statusFilter === 'inactive' ? 'all' : 'inactive')}
                        >
                            Inactive
                        </DropdownMenuCheckboxItem>

                        {/* Frequency Section */}
                        {uniqueFrequencies.length > 0 && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>Frequency</DropdownMenuLabel>
                                {uniqueFrequencies.map((freq) => (
                                    <DropdownMenuCheckboxItem
                                        key={freq}
                                        checked={frequencyFilter === freq.toLowerCase()}
                                        onCheckedChange={() => onFrequencyChange(frequencyFilter === freq.toLowerCase() ? 'all' : freq.toLowerCase())}
                                        className="capitalize"
                                    >
                                        {formatFrequency(freq)}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* View Mode Toggle */}
                <TooltipProvider>
                    <div className="flex items-center border border-slate-200 dark:border-slate-800 rounded-md bg-white dark:bg-slate-900 p-1 h-10">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    onClick={() => onViewModeChange('grid')}
                                    className={cn(
                                        "flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium transition-colors rounded-md",
                                        viewMode === 'grid'
                                            ? "bg-primary text-primary-foreground shadow-sm"
                                            : "text-muted-foreground hover:text-foreground"
                                    )}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                    <span className="hidden sm:inline">Grid</span>
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>Grid View</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    onClick={() => onViewModeChange('table')}
                                    className={cn(
                                        "flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium transition-colors rounded-md",
                                        viewMode === 'table'
                                            ? "bg-primary text-primary-foreground shadow-sm"
                                            : "text-muted-foreground hover:text-foreground"
                                    )}
                                >
                                    <LayoutList className="h-4 w-4" />
                                    <span className="hidden sm:inline">Table</span>
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>Table View</TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>

                {/* Search */}
                <div className="flex-1 relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Search by customer or report type..."
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="pl-10 h-10"
                    />
                </div>

                {/* Create Schedule Button */}
                <Button onClick={onCreateSchedule} className="h-10">
                    <Calendar className="h-4 w-4 mr-2" />
                    Create Schedule
                </Button>
            </div>
        </div>
    );
}
