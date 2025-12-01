import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Calendar, Clock, Settings } from "lucide-react";
import {
    formatDate,
    formatFrequency,
    formatReportType,
} from "../utils/formatters";
import type { ReportSchedule } from "../types/schedule";

interface ScheduleCardProps {
    schedule: ReportSchedule;
    onConfigure: () => void;
}

export function ScheduleCard({ schedule, onConfigure }: ScheduleCardProps) {
    return (
        <Card className="flex flex-col">
            <CardHeader className="pb-3 space-y-2">
                {/* Customer Name - Full Width, 2 Lines */}
                <div>
                    <CardTitle className="text-base leading-snug line-clamp-2" title={schedule.customer_name}>
                        {schedule.customer_name}
                    </CardTitle>
                    <CardDescription className="text-xs mt-1" title={formatReportType(schedule.report_type)}>
                        {formatReportType(schedule.report_type)}
                    </CardDescription>
                </div>
                
                {/* Status Badge - Separate Line */}
                <div>
                    <Badge
                        variant={schedule.active ? "default" : "secondary"}
                        className={`flex-shrink-0 ${
                            !schedule.active
                                ? "bg-purple-600 text-white hover:bg-purple-700"
                                : ""
                        }`}
                    >
                        {schedule.active ? "Active" : "Inactive"}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="flex-1 space-y-3">
                <div className="space-y-2 text-xs">
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground flex items-center gap-1 flex-shrink-0">
                            <Calendar className="h-3 w-3" />
                            Frequency
                        </span>
                        <span className="font-medium truncate" title={formatFrequency(schedule.frequency)}>
                            {formatFrequency(schedule.frequency)}
                        </span>
                    </div>
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground flex items-center gap-1 flex-shrink-0">
                            <Clock className="h-3 w-3" />
                            Time
                        </span>
                        <span className="font-medium truncate">
                            {schedule.time_of_day || "On-demand"}
                        </span>
                    </div>
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground flex-shrink-0">
                            Last Generated
                        </span>
                        <span className="font-medium truncate" title={formatDate(schedule.last_generated_at)}>
                            {formatDate(schedule.last_generated_at)}
                        </span>
                    </div>
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground flex-shrink-0">
                            Next Scheduled
                        </span>
                        <span className="font-medium truncate" title={formatDate(schedule.next_scheduled_at)}>
                            {formatDate(schedule.next_scheduled_at)}
                        </span>
                    </div>
                </div>

                <Button
                    size="sm"
                    variant="outline"
                    className="w-full"
                    onClick={onConfigure}
                >
                    <Settings className="h-4 w-4 mr-2" />
                    Configure
                </Button>
            </CardContent>
        </Card>
    );
}
