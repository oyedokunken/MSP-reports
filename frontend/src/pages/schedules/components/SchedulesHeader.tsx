import { Button } from "@/components/ui/button";
import { Calendar, Search } from "lucide-react";

interface SchedulesHeaderProps {
    onCreateSchedule: () => void;
    searchQuery: string;
    onSearchChange: (query: string) => void;
}

export function SchedulesHeader({ onCreateSchedule, searchQuery, onSearchChange }: SchedulesHeaderProps) {
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
            
            {/* Search */}
            <div className="relative max-w-md">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                    type="text"
                    placeholder="Search schedules by customer, report type, or frequency..."
                    value={searchQuery}
                    onChange={(e) => onSearchChange(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary"
                />
            </div>
        </div>
    );
}
