import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Calendar, Clock, Settings, CalendarClock, Repeat, Play, Pause, Edit, Trash2, MoreVertical, CheckCircle } from "lucide-react";
import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";
import {
    formatDate,
    formatFrequency,
    formatReportType,
} from "../utils/formatters";
import type { ReportSchedule } from "../types/schedule";

interface ScheduleCardProps {
    schedule: ReportSchedule;
    onConfigure: () => void;
    onPause?: () => void;
    onRunNow?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
}

export function ScheduleCard({ schedule, onConfigure, onPause, onRunNow, onEdit, onDelete }: ScheduleCardProps) {
    const { user } = useAuth();
    const canConfigure = hasPermission(user, 'reports', 'configure');

    return (
        <AnimatedCard className="p-6 h-full flex flex-col">
            {/* Header */}
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3 min-w-0 flex-1">
                    <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                        <CalendarClock className="h-6 w-6 text-primary" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="font-semibold text-foreground break-words">
                            {schedule.customer_name}
                        </h3>
                        <p className="text-xs text-muted-foreground truncate">
                            {formatReportType(schedule.report_type)}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                    {schedule.template_id && (
                        <Badge
                            variant={schedule.is_customized ? "outline" : "secondary"}
                            className={schedule.is_customized ? "text-amber-600 border-amber-600/50 bg-amber-50" : "bg-emerald-100 text-emerald-700"}
                        >
                            {schedule.is_customized ? "Customized" : "Template"}
                        </Badge>
                    )}
                    <Badge
                        variant={schedule.active ? "default" : "secondary"}
                        className={`flex-shrink-0 ${!schedule.active
                            ? "bg-purple-600 text-white hover:bg-purple-700"
                            : ""
                            }`}
                    >
                        {schedule.active ? "Active" : "Inactive"}
                    </Badge>
                </div>
            </div>

            {/* Info Section */}
            <div className="mt-6 space-y-3 flex-1">
                {/* Frequency + Time */}
                <div className="flex items-center gap-2 text-sm">
                    <Repeat className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                    <span className="text-foreground">
                        {formatFrequency(schedule.frequency)} • {schedule.time_of_day || "On-demand"}
                    </span>
                </div>

                {/* Next Scheduled */}
                <div className="flex items-center gap-2 text-sm">
                    <Calendar className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                    <span className="text-foreground">
                        Next: {formatDate(schedule.next_scheduled_at)}
                    </span>
                </div>

                {/* Last Generated */}
                {schedule.last_generated_at && (
                    <div className="flex items-center gap-2 text-sm">
                        <CheckCircle className="h-4 w-4 text-green-600 flex-shrink-0" />
                        <span className="text-green-600">
                            Last: {formatDate(schedule.last_generated_at)}
                        </span>
                    </div>
                )}
                {!schedule.last_generated_at && (
                    <div className="flex items-center gap-2 text-sm">
                        <Clock className="h-4 w-4 text-orange-600 flex-shrink-0" />
                        <span className="text-orange-600">
                            Never generated
                        </span>
                    </div>
                )}
            </div>

            {/* Actions */}
            <div className="mt-4 border-t border-border pt-4">
                <div className="flex gap-2">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    onClick={canConfigure ? onConfigure : undefined}
                                    disabled={!canConfigure}
                                    className={`flex-1 flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-all ${canConfigure
                                        ? "border-border bg-background text-foreground hover:bg-muted cursor-pointer"
                                        : "border-muted bg-muted text-muted-foreground cursor-not-allowed opacity-60"
                                        }`}
                                >
                                    <Settings className="h-4 w-4" />
                                    Configure
                                </button>
                            </TooltipTrigger>
                            {!canConfigure && (
                                <TooltipContent>
                                    <p>You need <strong>reports.configure</strong> permission</p>
                                </TooltipContent>
                            )}
                        </Tooltip>
                    </TooltipProvider>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" className="px-2">
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {onRunNow && (
                                <DropdownMenuItem onClick={onRunNow}>
                                    <Play className="mr-2 h-4 w-4" />
                                    Run Now
                                </DropdownMenuItem>
                            )}
                            {onPause && (
                                <DropdownMenuItem onClick={onPause}>
                                    <Pause className="mr-2 h-4 w-4" />
                                    {schedule.active ? 'Pause Schedule' : 'Resume Schedule'}
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            {onEdit && (
                                <DropdownMenuItem onClick={onEdit}>
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit Schedule
                                </DropdownMenuItem>
                            )}
                            {onDelete && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onClick={onDelete} className="text-destructive">
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Schedule
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </AnimatedCard>
    );
}
