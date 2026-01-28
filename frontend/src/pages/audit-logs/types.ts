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
  user_login: 'User Login',
  user_logout: 'User Logout',
  user_jit_provisioned: 'User JIT Provisioned',
  user_created: 'User Created',
  user_updated: 'User Updated',
  user_updated_from_provider: 'User Updated from Provider',
  user_deleted: 'User Deleted',
  user_deactivated: 'User Deactivated',
  user_reactivated: 'User Reactivated',
  user_password_reset: 'Password Reset',
  user_password_changed: 'Password Changed',
  user_permissions_updated: 'Permissions Updated',

  integration_created: 'Integration Created',
  integration_updated: 'Integration Updated',
  integration_deleted: 'Integration Deleted',
  integration_synced: 'Integration Synced',
  historical_import_started: 'Historical Import Started',

  customer_created: 'Customer Created',
  customer_updated: 'Customer Updated',
  customer_mapped: 'Customer Mapped',
  customer_merged: 'Customer Merged',
  customer_split: 'Customer Split',
  mapping_deleted: 'Mapping Deleted',

  settings_updated: 'Settings Updated',
  branding_updated: 'Branding Updated',

  report_generated: 'Report Generated',
  report_emailed: 'Report Emailed',
  report_shared: 'Report Shared',

  api_key_created: 'API Key Created',
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
  ApiKey: 'API Key',
  Company: 'Company',
} as const
