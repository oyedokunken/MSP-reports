import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar, Search } from "lucide-react";

interface SchedulesHeaderProps {
    onCreateSchedule: () => void;
    searchQuery: string;
    statusFilter: string;
    frequencyFilter: string;
    uniqueFrequencies: string[];
    onSearchChange: (query: string) => void;
    onStatusChange: (status: string) => void;
    onFrequencyChange: (frequency: string) => void;
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
    onFrequencyChange
}: SchedulesHeaderProps) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-foreground">
                        Report Schedules
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        View and manage automated report generation schedules
                    </p>
                </div>
                <Button onClick={onCreateSchedule}>
                    <Calendar className="h-4 w-4 mr-2" />
                    Create Schedule
                </Button>
            </div>
            
            {/* Search and Filters */}
            <div className="flex flex-col md:flex-row gap-3">
                {/* Search */}
                <div className="flex-1 relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Search by customer or report type..."
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="pl-10"
                    />
                </div>
                
                {/* Status Filter */}
                <Select value={statusFilter} onValueChange={onStatusChange}>
                    <SelectTrigger className="w-full md:w-[150px]">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="inactive">Inactive</SelectItem>
                    </SelectContent>
                </Select>
                
                {/* Frequency Filter */}
                <Select value={frequencyFilter} onValueChange={onFrequencyChange}>
                    <SelectTrigger className="w-full md:w-[150px]">
                        <SelectValue placeholder="Frequency" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Frequencies</SelectItem>
                        {uniqueFrequencies.map(freq => (
                            <SelectItem key={freq} value={freq.toLowerCase()}>
                                {formatFrequency(freq)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
