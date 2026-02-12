export interface AuditLog {
  audit_log_id: number
  company_id: number
  event_type: string
  actor_type: string
  actor_id: number | null
  actor_name?: string
  target_type: string | null
  target_id: number | null
  target_name?: string
  event_data: Record<string, unknown>
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface AuditLogsFilters {
  search: string
  event_type: string
  actor_type: string
  target_type: string
  date_from: string
  date_to: string
}

export interface AuditLogStats {
  total_logs: number
  logs_today: number
  unique_actors: number
  critical_events: number
}

export const EVENT_TYPES = {
  // Auth events
  user_login: 'User Login',
  user_logout: 'User Logout',
  user_login_failed: 'Login Failed',
  user_jit_provisioned: 'User JIT Provisioned',
  user_password_reset: 'Password Reset',
  user_password_reset_requested: 'Password Reset Requested',
  user_password_changed: 'Password Changed',

  // User management events
  user_created: 'User Created',
  user_updated: 'User Updated',
  user_updated_from_provider: 'User Updated from Provider',
  user_deleted: 'User Deleted',
  user_deactivated: 'User Deactivated',
  user_reactivated: 'User Reactivated',
  user_permissions_updated: 'Permissions Updated',

  // Integration events
  integration_created: 'Integration Created',
  integration_updated: 'Integration Updated',
  integration_deleted: 'Integration Deleted',
  integration_synced: 'Integration Synced',
  integration_source_of_truth_changed: 'Source of Truth Changed',
  historical_import_queued: 'Historical Import Queued',
  historical_import_started: 'Historical Import Started',
  historical_import_completed: 'Historical Import Completed',
  historical_import_failed: 'Historical Import Failed',

  // Customer events
  customer_created: 'Customer Created',
  customer_updated: 'Customer Updated',
  customer_mapped: 'Customer Mapped',
  customer_created_and_mapped: 'Customer Created & Mapped',
  customer_batch_auto_matched: 'Batch Auto-Match',
  customer_merged: 'Customer Merged',
  customer_split: 'Customer Split',
  mapping_deleted: 'Mapping Deleted',

  // Report events
  report_generated: 'Report Generated',
  report_emailed: 'Report Emailed',
  report_downloaded: 'Report Downloaded',
  report_shared: 'Report Shared',
  report_deleted: 'Report Deleted',
  report_batch_deleted: 'Reports Batch Deleted',
  report_bulk_generated: 'Reports Bulk Generated',
  report_delivered: 'Report Delivered',
  report_delivery_failed: 'Report Delivery Failed',

  // Schedule events
  schedule_updated: 'Schedule Updated',
  schedule_deleted: 'Schedule Deleted',
  schedule_reset: 'Schedule Reset to Template',

  // Template events
  template_created: 'Template Created',
  template_updated: 'Template Updated',
  template_deleted: 'Template Deleted',
  template_applied: 'Template Applied',
  template_bulk_applied: 'Template Bulk Applied',

  // Company/Settings events
  settings_updated: 'Settings Updated',
  branding_updated: 'Branding Updated',
  logo_uploaded: 'Logo Uploaded',

  // API Key events
  api_key_created: 'API Key Created',
  api_key_revoked: 'API Key Revoked',
  api_key_deleted: 'API Key Deleted',
} as const

export const ACTOR_TYPES = {
  User: 'User',
  System: 'System',
  ApiKey: 'API Key',
} as const

export const TARGET_TYPES = {
  User: 'User',
  Integration: 'Integration',
  Customer: 'Customer',
  Report: 'Report',
  ReportSchedule: 'Schedule',
  ReportTemplate: 'Template',
  GeneratedReport: 'Generated Report',
  ApiKey: 'API Key',
  Company: 'Company',
} as const
