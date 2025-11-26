import { Button } from "@/components/ui/button";
import { Calendar } from "lucide-react";

interface SchedulesHeaderProps {
    onCreateSchedule: () => void;
}

export function SchedulesHeader({ onCreateSchedule }: SchedulesHeaderProps) {
    return (
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
    );
}
