export type ReportStatus = 'completed' | 'failed' | 'pending'

export type ReportItem = {
  id: number
  name: string
  customer: string
  schedule: string
  lastGenerated: string
  status: ReportStatus
  nextRun: string
}

export type Stat = {
  label: string
  value: string
  color: string
}

export interface ReportType {
  id: string
  name: string
  description: string
  integration_source?: string
  frequency_options: string[]
  has_data?: boolean
}

export interface EnabledReport {
  report_type: string
  schedule_id?: number
  frequency?: string
  time_of_day?: string
  active?: boolean
  _old_schedule_id?: number
  include_sections?: string[]
  day_of_month?: number
  day_of_week?: number
  has_data?: boolean
}

export interface ReportDelivery {
  method: string
  destination: string
  cc: string[]
}

export interface ReportBranding {
  customer_logo_url?: string
  report_title?: string
  footer_notes?: string
}

export interface ReportSchedule {
  report_type: string
  report_schedule_id?: number
  frequency: string
  time_of_day: string
  active: boolean
  day_of_month?: number
  day_of_week?: number
  include_sections?: string[]
  schedule_config?: {
    included_sections?: string[]
    report_options?: Record<string, unknown>
    day_of_month?: number
    day_of_week?: number
  }
  email_destination?: string
  cc_emails?: string[]
  has_data?: boolean
}

export interface ReportConfig {
  enabled_reports: EnabledReport[]
  delivery: ReportDelivery
  timezone: string | null
  retention_days: number | null
  report_branding?: ReportBranding
  format?: unknown
  email_destination?: string
  cc_emails?: string[]
  report_schedules?: ReportSchedule[]
}


