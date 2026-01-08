// Integration types
export interface Integration {
  id: number
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
  created_at: string
  updated_at: string
}

// Customer types
export interface Customer {
  id: number
  company_id: number
  canonical_name: string
  status: 'active' | 'inactive'
  is_static: boolean
  metadata: Record<string, unknown>
  created_at: string
  updated_at: string
}

export interface IntegrationCustomer {
  id: number
  integration_id: number
  external_id: string
  customer_name: string
  customer_metadata: Record<string, unknown>
  active: boolean
  last_seen_at: string
  created_at: string
}

export interface CustomerMapping {
  id: number
  customer_id: number
  integration_customer_id: number
  mapping_type: 'auto' | 'manual'
  confidence_score: number | null
  mapped_by_user_id: number | null
  mapped_at: string
  notes: string | null
}

// Report types
export interface ReportSchedule {
  id: number
  company_id: number
  customer_id: number
  report_type: string
  schedule_frequency: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'on-demand'
  schedule_config: Record<string, unknown>
  time_of_day: string
  active: boolean
  last_generated_at: string | null
  next_scheduled_at: string | null
  timezone: string
  created_at: string
  updated_at: string
}

export interface GeneratedReport {
  id: number
  report_schedule_id: number
  customer_id: number
  company_id: number
  generation_timestamp: string
  report_period_start: string
  report_period_end: string
  status: 'pending' | 'generating' | 'completed' | 'failed'
  file_path: string | null
  file_size: number | null
  delivered_status: 'pending' | 'delivered' | 'failed'
  delivered_at: string | null
  delivery_method: 'email' | 'api' | 'download'
  error_message: string | null
  retention_until: string | null
  created_at: string
}

// Sync Log types
export interface SyncLog {
  id: number
  integration_id: number
  sync_type: 'current_state' | 'historical' | 'backfill'
  started_at: string
  completed_at: string | null
  status: 'running' | 'success' | 'failed' | 'partial'
  records_synced: number
  records_failed: number
  error_messages: string[]
  rate_limit_hits: number
}
