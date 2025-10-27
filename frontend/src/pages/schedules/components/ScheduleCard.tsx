import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Calendar, Clock, Settings } from 'lucide-react'
import { formatDate, formatFrequency, formatReportType } from '../utils/formatters'
import type { ReportSchedule } from '../types/schedule'

interface ScheduleCardProps {
  schedule: ReportSchedule
  onConfigure: () => void
}

export function ScheduleCard({ schedule, onConfigure }: ScheduleCardProps) {
  return (
    <Card className="flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <CardTitle className="text-base truncate">
              {schedule.customer_name}
            </CardTitle>
            <CardDescription className="text-xs mt-1 truncate">
              {formatReportType(schedule.report_type)}
            </CardDescription>
          </div>
          <Badge variant={schedule.active ? 'default' : 'secondary'}>
            {schedule.active ? 'Active' : 'Inactive'}
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="flex-1 space-y-3">
        <div className="space-y-2 text-xs">
          <div className="flex justify-between">
            <span className="text-muted-foreground flex items-center gap-1">
              <Calendar className="h-3 w-3" />
              Frequency
            </span>
            <span className="font-medium">{formatFrequency(schedule.frequency)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground flex items-center gap-1">
              <Clock className="h-3 w-3" />
              Time
            </span>
            <span className="font-medium">{schedule.time_of_day || 'On-demand'}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground">Last Generated</span>
            <span className="font-medium">{formatDate(schedule.last_generated_at)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground">Next Scheduled</span>
            <span className="font-medium">{formatDate(schedule.next_scheduled_at)}</span>
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
  )
}
