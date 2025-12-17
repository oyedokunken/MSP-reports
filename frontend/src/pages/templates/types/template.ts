export interface ReportTemplate {
  template_id: number
  company_id: number
  template_name: string
  description: string | null
  report_type: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config: ScheduleConfig | null
  time_of_day: string | null
  timezone: string
  included_sections: string[] | null
  delivery_settings: DeliverySettings | null
  report_options: Record<string, any> | null
  active: boolean
  created_at: string
  updated_at: string
}

export interface ScheduleConfig {
  days_of_week?: number[]
  day_of_month?: number
  fallback_to_last_day?: boolean
  month?: number
}

export interface DeliverySettings {
  method: 'email' | 'api' | 'download'
  recipients?: string[]
  cc?: string[]
  format?: 'pdf' | 'html'
}

export interface CreateTemplateRequest {
  template_name: string
  description?: string
  report_type: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config?: ScheduleConfig
  time_of_day?: string
  timezone?: string
  included_sections?: string[]
  delivery_settings?: DeliverySettings
  report_options?: Record<string, any>
  active?: boolean
}

export interface UpdateTemplateRequest extends Partial<CreateTemplateRequest> {}

export interface ApplyTemplateRequest {
  customer_id: number
}

export interface ApplyTemplateBulkRequest {
  customer_ids: number[]
}
