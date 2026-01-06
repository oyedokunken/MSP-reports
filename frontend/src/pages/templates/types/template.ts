export interface ReportTemplate {
  template_id: number
  company_id: number
  customer_id: number | null
  customer?: {
    customer_id: number
    canonical_name: string
  }
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
  schedules_count?: number
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
  customer_id?: number
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

export interface ReportTypeDefinition {
  type: string
  name: string
  description: string
  required_integrations: string[]
  optional_integrations?: string[]
  available_sections: ReportSection[]
}

export interface ReportSection {
  id: string
  label: string
  description?: string
  default: boolean
  requires?: string // Integration type required for this section
  configurable?: boolean
}

export interface CustomerIntegrationInfo {
  customer_id: number
  customer_name: string
  integrations: Array<{
    integration_id: number
    integration_type: string
    integration_name: string
    status: string
  }>
}

export interface AvailableReportTypesResponse {
  customer_id: number
  report_types: ReportTypeDefinition[]
}

export interface ApplyTemplateRequest {
  customer_id: number
}

export interface ApplyTemplateBulkRequest {
  customer_ids: number[]
}
