export interface ReportSchedule {
  report_schedule_id: number
  customer_id: number
  customer_name: string
  report_type: string
  frequency: string
  time_of_day: string | null
  next_scheduled_at: string | null
  last_generated_at: string | null
  active: boolean
}

export type ScheduleFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
