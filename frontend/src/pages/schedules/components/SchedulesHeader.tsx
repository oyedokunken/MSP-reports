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
} from "lucide-react";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { List } from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";

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
    const { user } = useAuth();
    const canCreateSchedule = hasPermission(user, 'schedules', 'create');
    
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
            
            <div className="flex flex-col md:flex-row gap-3">
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

                <Tabs value={viewMode} onValueChange={(v) => onViewModeChange(v as ViewMode)} className="w-full md:w-auto">
                    <TabsList className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1 h-10 w-full justify-start">
                        <TabsTrigger value="grid" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md flex-1 sm:flex-initial">
                            <LayoutGrid className="h-4 w-4" />
                            <span className="ml-2">Grid</span>
                        </TabsTrigger>
                        <TabsTrigger value="table" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground data-[state=active]:shadow-sm px-3 rounded-md flex-1 sm:flex-initial">
                            <List className="h-4 w-4" />
                            <span className="ml-2">Table</span>
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <div className="flex-1 relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Search by customer or report type..."
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="pl-10 h-10"
                    />
                </div>

                {canCreateSchedule && (
                    <Button onClick={onCreateSchedule} className="h-10">
                        <Calendar className="h-4 w-4 mr-2" />
                        Create Schedule
                    </Button>
                )}
            </div>
        </div>
    );
}
