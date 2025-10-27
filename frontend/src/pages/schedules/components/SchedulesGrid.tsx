import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { Calendar } from 'lucide-react'
import { ScheduleCard } from './ScheduleCard'
import type { ReportSchedule } from '../types/schedule'

interface SchedulesGridProps {
  schedules: ReportSchedule[]
  isLoading: boolean
  onConfigure: (customerId: number) => void
  onCreateSchedule: () => void
}

export function SchedulesGrid({ schedules, isLoading, onConfigure, onCreateSchedule }: SchedulesGridProps) {
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Spinner className="h-8 w-8" />
      </div>
    )
  }

  if (schedules.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <Calendar className="h-12 w-12 text-muted-foreground mb-4" />
          <p className="text-lg font-medium">No Scheduled Reports</p>
          <p className="text-sm text-muted-foreground mb-4">
            Configure report schedules for your customers
          </p>
          <Button onClick={onCreateSchedule}>
            <Calendar className="h-4 w-4 mr-2" />
            Go to Customers
          </Button>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {schedules.map((schedule) => (
        <ScheduleCard
          key={schedule.report_schedule_id}
          schedule={schedule}
          onConfigure={() => onConfigure(schedule.customer_id)}
        />
      ))}
    </div>
  )
}
