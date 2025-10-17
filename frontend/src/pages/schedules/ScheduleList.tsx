import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import { Calendar, Clock, FileText, Settings } from 'lucide-react'
import { api } from '@/lib/api'

interface Customer {
  customer_id: number
  canonical_name: string
}

interface ReportSchedule {
  report_schedule_id: number
  report_type: string
  frequency: string
  time_of_day: string | null
  active: boolean
  last_generated_at: string | null
  next_scheduled_at: string | null
  timezone: string
  customer: Customer
}

export default function ScheduleList() {
  const navigate = useNavigate()
  const toast = useToast()
  const [isLoading, setIsLoading] = useState(true)
  const [schedules, setSchedules] = useState<ReportSchedule[]>([])

  useEffect(() => {
    fetchScheduledReports()
  }, [])

  const fetchScheduledReports = async () => {
    try {
      setIsLoading(true)
      const response = await api.get<{ schedules: ReportSchedule[] }>('/reports/scheduled')
      const data = (response as any).data || response
      setSchedules(data.schedules || [])
    } catch (err: any) {
      toast.error('Failed to load scheduled reports', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never'
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold text-foreground">Report Schedules</h1>
          <p className="mt-2 text-muted-foreground">
            View and manage automated report generation schedules
          </p>
        </div>

        {/* Content */}
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Spinner className="h-8 w-8" />
          </div>
        ) : schedules.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <Calendar className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-lg font-medium">No Scheduled Reports</p>
              <p className="text-sm text-muted-foreground">
                Configure report schedules for your customers
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {schedules.map((schedule) => (
              <Card key={schedule.report_schedule_id} className="flex flex-col">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                      <CardTitle className="text-base truncate">
                        {schedule.customer.canonical_name}
                      </CardTitle>
                      <CardDescription className="text-xs mt-1 truncate">
                        {schedule.report_type}
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
                      <span className="font-medium capitalize">{schedule.frequency}</span>
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
                    {schedule.timezone && (
                      <div className="flex justify-between pt-2 border-t border-border">
                        <span className="text-muted-foreground">Timezone</span>
                        <span className="font-medium">{schedule.timezone}</span>
                      </div>
                    )}
                  </div>

                  <Button
                    size="sm"
                    variant="outline"
                    className="w-full"
                    onClick={(e) => {
                      e.preventDefault()
                      navigate(`/customers/${schedule.customer.customer_id}/configure-reports`)
                    }}
                  >
                    <Settings className="h-4 w-4 mr-2" />
                    Configure
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </FloatingLayout>
  )
}
