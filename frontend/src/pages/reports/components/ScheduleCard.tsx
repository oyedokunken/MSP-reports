import { Card, CardHeader, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { Check, Zap, ExternalLink } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { EnabledReport, ReportType } from '../types'

const FREQUENCIES = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
    { value: 'on-demand', label: 'On-Demand Only' },
]

const DAYS_OF_WEEK = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

interface ScheduleCardProps {
    report: EnabledReport
    index: number
    isSelected: boolean
    reportType: ReportType | undefined
    isSaving: boolean
    onSelect: (scheduleId: number) => void
    onUpdate: (index: number, field: keyof EnabledReport, value: string | number | boolean | string[]) => void
    onReset: (scheduleId: number) => void
    onNavigateToTemplates: () => void
}

export function ScheduleCard({
    report,
    index,
    isSelected,
    reportType,
    isSaving,
    onSelect,
    onUpdate,
    onReset,
    onNavigateToTemplates,
}: ScheduleCardProps) {
    const isActive = report.active !== false
    const isDisabled = !isActive

    return (
        <TooltipProvider>
            <Tooltip delayDuration={200}>
                <TooltipTrigger asChild disabled={isActive}>
                    <div>
                        <Card
                            onClick={() => isActive && report.schedule_id && onSelect(report.schedule_id)}
                            className={cn(
                                "group relative overflow-hidden rounded-xl border-2 transition-all",
                                isActive && "cursor-pointer hover:shadow-lg",
                                isActive && isSelected
                                    ? "border-primary bg-primary/5 shadow-md"
                                    : isActive
                                        ? "border-border hover:border-primary/30"
                                        : "border-border",
                                isDisabled && "opacity-60 grayscale cursor-not-allowed",
                                "py-0 gap-0"
                            )}
                        >
                            <CardHeader className="p-4 pb-2">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-0.5">
                                        <h3 className="text-sm font-semibold tracking-tight">
                                            {report.template_name ? (
                                                <div className="flex flex-col">
                                                    <span>{report.template_name}</span>
                                                    <span className="text-[10px] font-normal text-muted-foreground">
                                                        {reportType?.name || "Unknown Report"}
                                                    </span>
                                                    {report.template_description && (
                                                        <p className="text-[10px] text-muted-foreground/70 mt-0.5 line-clamp-1" title={report.template_description}>
                                                            {report.template_description}
                                                        </p>
                                                    )}
                                                </div>
                                            ) : (
                                                reportType?.name || "Unknown Report"
                                            )}
                                        </h3>

                                        <div className="flex items-center gap-2">
                                            <span className="text-[10px] text-muted-foreground font-mono">ID: {report.schedule_id}</span>

                                            <Badge
                                                variant={isActive ? "default" : "secondary"}
                                                className="h-4 px-1.5 text-[10px] font-bold uppercase tracking-wider"
                                            >
                                                {isActive ? "Active" : "Paused"}
                                            </Badge>

                                            {report.template_id && report.is_customized && (
                                                <Badge
                                                    variant="outline"
                                                    className="h-4 border-amber-200 bg-amber-50 px-1.5 text-[10px] text-amber-700 font-bold uppercase tracking-wider"
                                                >
                                                    Customized
                                                </Badge>
                                            )}

                                            {report.has_data === false && (
                                                <Badge
                                                    variant="outline"
                                                    className="h-4 border-orange-200 bg-orange-50 px-1 text-orange-700"
                                                >
                                                    No Data
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    {report.schedule_id && (
                                        <Badge
                                            variant={isSelected ? "default" : "outline"}
                                            className={cn(
                                                "h-6 px-2 text-[10px] font-semibold transition-all",
                                                isSelected
                                                    ? "bg-primary text-primary-foreground"
                                                    : "text-muted-foreground hover:bg-muted"
                                            )}
                                        >
                                            {isSelected ? (
                                                <>
                                                    <Check className="h-3 w-3 mr-1" />
                                                    Selected
                                                </>
                                            ) : (
                                                "Select"
                                            )}
                                        </Badge>
                                    )}
                                </div>
                            </CardHeader>

                            <CardContent className="p-4 pt-2 space-y-2">
                                <div className="grid grid-cols-2 gap-4 rounded-lg border bg-muted/30 p-2">
                                    <div className="space-y-1.5 flex flex-col">
                                        <Label htmlFor={`freq-${index}`} className="text-[10px] font-semibold uppercase text-muted-foreground">
                                            Frequency
                                        </Label>
                                        <Select
                                            value={report.frequency || 'monthly'}
                                            onValueChange={(value) => onUpdate(index, 'frequency', value)}
                                            disabled={isDisabled}
                                        >
                                            <SelectTrigger id={`freq-${index}`} className="h-8 text-xs bg-background w-full">
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {FREQUENCIES.map((f) => (
                                                    <SelectItem key={f.value} value={f.value} className="text-xs">
                                                        {f.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5 flex flex-col items-end">
                                        <Label htmlFor={`time-${index}`} className="text-[10px] font-semibold uppercase text-muted-foreground text-right w-full">
                                            Schedule
                                        </Label>
                                        {report.frequency !== 'on-demand' ? (
                                            <div className="w-full">
                                                <Input
                                                    id={`time-${index}`}
                                                    type="time"
                                                    value={report.time_of_day || '09:00'}
                                                    onChange={(e) => onUpdate(index, 'time_of_day', e.target.value)}
                                                    disabled={isDisabled}
                                                    className="h-8 text-xs bg-background w-full"
                                                />
                                            </div>
                                        ) : (
                                            <div className="h-8 w-full flex items-center justify-center text-xs text-muted-foreground bg-background border rounded-md px-3 mt-auto">
                                                Manual
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {report.frequency === 'monthly' && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor={`day-month-${index}`} className="text-[10px] font-semibold uppercase text-muted-foreground">
                                            Day of Month
                                        </Label>
                                        <Select
                                            value={report.day_of_month?.toString() || '1'}
                                            onValueChange={(value) => onUpdate(index, 'day_of_month', parseInt(value))}
                                            disabled={isDisabled}
                                        >
                                            <SelectTrigger id={`day-month-${index}`} className="h-8 text-xs bg-background">
                                                <SelectValue placeholder="Select day" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Array.from({ length: 28 }, (_, i) => i + 1).map((day) => (
                                                    <SelectItem key={day} value={day.toString()} className="text-xs">
                                                        Day {day}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                {report.frequency === 'weekly' && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor={`day-week-${index}`} className="text-[10px] font-semibold uppercase text-muted-foreground">
                                            Day of Week
                                        </Label>
                                        <Select
                                            value={report.day_of_week?.toString() || '0'}
                                            onValueChange={(value) => onUpdate(index, 'day_of_week', parseInt(value))}
                                            disabled={isDisabled}
                                        >
                                            <SelectTrigger id={`day-week-${index}`} className="h-8 text-xs bg-background">
                                                <SelectValue placeholder="Select day" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {DAYS_OF_WEEK.map((day, idx) => (
                                                    <SelectItem key={idx} value={idx.toString()} className="text-xs">
                                                        {day}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                {(report.frequency === "monthly" || report.frequency === "weekly") && (
                                    <div className="flex items-center gap-2 rounded-md border bg-background px-2 py-1.5 text-[11px]">
                                        <Zap className="h-3 w-3 text-primary" />
                                        <span>
                                            Runs on{" "}
                                            <span className="font-semibold">
                                                {report.frequency === "monthly"
                                                    ? `Day ${report.day_of_month}`
                                                    : DAYS_OF_WEEK[report.day_of_week || 0]}
                                            </span>{" "}
                                            each {report.frequency}
                                        </span>
                                    </div>
                                )}

                                <div className="flex items-center justify-between border-t pt-2">
                                    <div className="flex items-center gap-2">
                                        <Switch
                                            checked={isActive}
                                            onCheckedChange={(checked) => onUpdate(index, "active", checked)}
                                            className="scale-75"
                                        />
                                        <span className="text-[10px] text-muted-foreground">Status</span>
                                    </div>

                                    <div className="flex items-center gap-1">
                                        {report.template_id && report.is_customized && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={(e) => {
                                                    e.stopPropagation()
                                                    if (report.schedule_id) onReset(report.schedule_id)
                                                }}
                                                disabled={isSaving || isDisabled}
                                                className="h-6 px-2 text-[10px] border-amber-200 text-amber-700 hover:bg-amber-50"
                                            >
                                                Reset
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={(e) => {
                                                e.stopPropagation()
                                                onNavigateToTemplates()
                                            }}
                                            disabled={isDisabled}
                                            className="h-6 px-2 text-[10px] gap-1"
                                        >
                                            Edit Template
                                            <ExternalLink className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TooltipTrigger>
                {isDisabled && (
                    <TooltipContent side="bottom" align="start" className="bg-primary text-white border-primary" sideOffset={5}>
                        <p className="text-xs font-medium">Enable first to select the card</p>
                        <div className="absolute -top-1 left-8 w-2 h-2 bg-primary rotate-45 border-l border-t border-primary" />
                    </TooltipContent>
                )}
            </Tooltip>
        </TooltipProvider>
    )
}
