import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
    Calendar,
    Clock,
    Settings,
    CalendarClock,
    Repeat,
    Trash2,
    CheckCircle
} from "lucide-react";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";
import {
    formatDate,
    formatFrequency,
    formatReportType,
} from "../utils/formatters";
import { TimeRemaining } from "./TimeRemaining";
import type { ReportSchedule } from "../types/schedule";
import { cn } from "@/lib/utils";

interface SchedulesTableProps {
    schedules: ReportSchedule[];
    isLoading: boolean;
    onConfigure: (customerId: number) => void;
    onDelete: (scheduleId: number) => void;
    onCreateSchedule: () => void;
}

export function SchedulesTable({
    schedules,
    isLoading,
    onConfigure,
    onDelete,
    onCreateSchedule,
}: SchedulesTableProps) {
    const { user } = useAuth();
    const canConfigure = hasPermission(user, 'reports', 'configure');
    const canDelete = hasPermission(user, 'schedules', 'delete');

    if (isLoading) {
        return (
            <div className="border rounded-xl overflow-hidden bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/50">
                            <TableHead className="w-[300px]">Customer / Report</TableHead>
                            <TableHead>Frequency</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Next Run</TableHead>
                            <TableHead>Last Run</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {[1, 2, 3, 4, 5].map((i) => (
                            <TableRow key={i}>
                                <TableCell>
                                    <div className="flex items-center gap-3">
                                        <Skeleton className="h-10 w-10 rounded-lg" />
                                        <div className="space-y-2">
                                            <Skeleton className="h-4 w-32" />
                                            <Skeleton className="h-3 w-24" />
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                                <TableCell><Skeleton className="h-6 w-16 rounded-full" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                                <TableCell className="text-right"><Skeleton className="h-8 w-8 ml-auto rounded-md" /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        );
    }

    if (schedules.length === 0) {
        return (
            <div className="border rounded-xl p-12 text-center bg-card">
                <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <p className="text-lg font-medium">No Scheduled Reports</p>
                <p className="text-sm text-muted-foreground mb-6">
                    Configure report schedules for your customers
                </p>
                <Button onClick={onCreateSchedule}>
                    <Calendar className="h-4 w-4 mr-2" />
                    Go to Customers
                </Button>
            </div>
        );
    }

    return (
        <TooltipProvider>
            <div className="border rounded-xl overflow-hidden bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/50 hover:bg-muted/50">
                            <TableHead className="w-[300px] font-semibold">Customer / Report</TableHead>
                            <TableHead className="font-semibold">Frequency</TableHead>
                            <TableHead className="font-semibold">Status</TableHead>
                            <TableHead className="font-semibold">Next Run</TableHead>
                            <TableHead className="font-semibold">Last Run</TableHead>
                            <TableHead className="text-right font-semibold">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {schedules.map((schedule) => (
                            <TableRow key={schedule.report_schedule_id} className="transition-colors">
                                <TableCell>
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                                            <CalendarClock className="h-5 w-5 text-primary" />
                                        </div>
                                        <div className="min-w-0">
                                            <div className="font-medium text-foreground truncate max-w-[200px]" title={schedule.customer_name}>
                                                {schedule.customer_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground truncate" title={formatReportType(schedule.report_type)}>
                                                {formatReportType(schedule.report_type)}
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Repeat className="h-3.5 w-3.5" />
                                        <span className="capitalize">{formatFrequency(schedule.frequency)}</span>
                                        {schedule.time_of_day && (
                                            <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded uppercase">
                                                {schedule.time_of_day}
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={schedule.active ? "default" : "secondary"}
                                            className={cn(
                                                "text-[10px] py-0 px-2",
                                                !schedule.active && "bg-purple-600/10 text-purple-600 hover:bg-purple-600/20"
                                            )}
                                        >
                                            {schedule.active ? "Active" : "Inactive"}
                                        </Badge>
                                        {schedule.template_id && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div className={cn(
                                                        "h-2 w-2 rounded-full",
                                                        schedule.is_customized ? "bg-amber-500" : "bg-emerald-500"
                                                    )} />
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    {schedule.is_customized ? "Customized Configuration" : "Using System Template"}
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-col">
                                        <span className="text-sm font-medium whitespace-nowrap">
                                            {formatDate(schedule.next_scheduled_at)}
                                        </span>
                                        {schedule.next_scheduled_at && (
                                            <TimeRemaining dateString={schedule.next_scheduled_at} />
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-1.5">
                                        {schedule.last_generated_at ? (
                                            <>
                                                <CheckCircle className="h-3.5 w-3.5 text-green-600" />
                                                <span className="text-sm text-green-600 whitespace-nowrap font-medium">
                                                    {formatDate(schedule.last_generated_at)}
                                                </span>
                                            </>
                                        ) : (
                                            <>
                                                <Clock className="h-3.5 w-3.5 text-orange-500" />
                                                <span className="text-sm text-orange-500 whitespace-nowrap font-medium">
                                                    Never
                                                </span>
                                            </>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right">
                                    <div className="flex items-center justify-end gap-1">
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => onConfigure(schedule.customer_id)}
                                                    className="h-8 w-8 p-0"
                                                    disabled={!canConfigure}
                                                >
                                                    <Settings className="h-4 w-4" />
                                                    <span className="sr-only">Configure</span>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {canConfigure ? "Configure Schedule" : "Insufficient Permissions"}
                                            </TooltipContent>
                                        </Tooltip>

                                        {canDelete && (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onDelete(schedule.report_schedule_id)}
                                                        className="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">Delete Schedule</span>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Delete Schedule</TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </TooltipProvider>
    );
}
