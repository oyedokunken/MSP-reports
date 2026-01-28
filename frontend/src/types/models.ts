/**
 * Frontend Type Definitions
 * Aligned with backend Laravel models
 * 
 * Note: Some types include both backend field names and transformed aliases
 * for compatibility with existing frontend components.
 */


export interface Integration {
  integration_id: number
  company_id: number
  integration_type: string
  display_name: string
  status: 'active' | 'disconnected' | 'error' | 'importing_historical'
  last_sync_at: string | null
  last_sync_status: 'success' | 'failed' | 'partial' | null
  error_message: string | null
  is_source_of_truth: boolean
  historical_import_status: 'pending' | 'running' | 'completed' | 'failed' | null
  historical_import_progress: number
  configuration?: Record<string, unknown>
  sync_schedule?: string | null
  last_historical_sync_at?: string | null
  created_at: string
  updated_at: string
}

export interface Customer {
  customer_id: number
  company_id: number
  canonical_name: string
  status: 'active' | 'inactive'
  is_static: boolean
  metadata: Record<string, unknown>
  report_configuration?: Record<string, unknown>
  report_destination?: string | null
  created_at: string
  updated_at: string
}

export interface IntegrationCustomer {
  integration_customer_id: number
  integration_id: number
  customer_id?: number | null
  external_id: string
  name: string
  metadata: Record<string, unknown>
  active: boolean
  last_seen_at: string | null
  created_at: string
}

export interface CustomerMapping {
  customer_mapping_id: number
  customer_id: number
  integration_customer_id: number
  mapping_type: 'auto' | 'manual'
  confidence_score: number | null
  mapped_by_user_id: number | null
  mapped_at: string
  notes: string | null
}

export interface ReportSchedule {
  report_schedule_id: number
  company_id: number
  customer_id: number
  template_id?: number | null
  report_type: string
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config: Record<string, unknown>
  time_of_day: string
  active: boolean
  last_generated_at: string | null
  next_scheduled_at: string | null
  timezone: string
  is_customized?: boolean
  created_at: string
  updated_at: string
}

export interface GeneratedReport {
  generated_report_id: number
  report_schedule_id: number
  customer_id: number
  company_id: number
  generated_at: string
  report_period: { start: string; end: string }
  status: 'pending' | 'generating' | 'completed' | 'failed'
  file_path: string | null
  file_size: number | null
  delivered: boolean
  delivered_at: string | null
  delivery_method: 'email' | 'api' | 'download'
  error_message: string | null
  retention_until: string | null
  created_at: string
}

export interface SyncLog {
  sync_id: number
  integration_id: number
  sync_type: 'current_state' | 'historical' | 'backfill'
  started_at: string
  completed_at: string | null
  status: 'running' | 'success' | 'failed' | 'partial'
  records_synced: number
  records_failed: number
  error_messages: string[]
  rate_limit_hits: number
  customers_mapped?: Record<string, unknown>[]
}

export interface Company {
  company_id: number
  name: string
  abn?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  address?: string | null
  status: 'active' | 'inactive' | 'suspended'
  subscription_tier: string
  settings: Record<string, unknown>
  created_at: string
  updated_at: string
}

export interface AuditLog {
  audit_log_id: number
  company_id: number
  event_type: string
  actor_type: string
  actor_id: number | null
  target_type: string | null
  target_id: number | null
  event_data: Record<string, unknown>
  ip_address: string | null
  user_agent: string | null
  created_at: string
  actor_name?: string
  target_name?: string
}

