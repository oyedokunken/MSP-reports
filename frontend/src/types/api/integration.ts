/**
 * Integration API types
 * Matches backend IntegrationController response structure
 */

import type { ApiSuccessResponse } from './common'

export interface Integration {
    integration_id: number
    company_id: number
    integration_type: string
    display_name: string
    status: 'active' | 'disconnected' | 'error' | 'importing_historical'
    is_source_of_truth: boolean
    credentials: Record<string, string>
    configuration: Record<string, unknown> | null
    last_sync_at: string | null
    last_sync_status: string | null
    error_message: string | null
    unmapped_customers_count?: number
    source_label?: string | null
    created_at: string
    updated_at: string
}

export interface IntegrationConfig {
    name: string
    description: string
    credential_fields: CredentialField[]
    data_types: string[]
    supports_historical: boolean
    historical_range_days: number
}

export interface CredentialField {
    name: string
    label: string
    type: 'text' | 'password' | 'select' | 'url'
    required: boolean
    placeholder?: string
    options?: Array<{ value: string; label: string }>
}

export interface IntegrationCustomer {
    integration_customer_id: number
    external_id: string
    customer_name: string
    active_status: boolean
    last_seen_at: string | null
    customer_metadata: Record<string, unknown>
}

export interface SyncLog {
    sync_id: number
    integration_id: number
    started_at: string
    completed_at: string | null
    status: 'queued' | 'running' | 'completed' | 'failed'
    records_synced: number
    customers_mapped: Record<string, unknown>
    error_message: string | null
    rate_limit_hits: number
}

export interface IntegrationHealth {
    healthy: boolean
    status: string
    last_sync_at: string | null
    last_sync_status: string | null
    last_sync_age_minutes: number | null
    error_message: string | null
    checks: {
        credentials_valid: boolean
        recent_sync: boolean
        no_errors: boolean
    }
}

export interface RateLimitStatus {
    integration_type: string
    limits: {
        per_minute: number
        per_hour: number
        burst_allowance: number
    }
    current_usage: {
        requests_last_hour: number
        rate_limit_hits: number
    }
    percentage_used: number
    status: 'normal' | 'throttled'
}

export interface HistoricalImportJob {
    job_id: number
    integration_id: number
    start_date: string
    end_date: string
    status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled'
    progress_percentage: number
    records_imported: number
    error_message: string | null
    created_at: string
    started_at: string | null
    completed_at: string | null
}

// API Response Types
export interface IntegrationListResponse {
    available_types: string[]
    configured: Integration[]
}

export type IntegrationConfigResponse = ApiSuccessResponse<{ config: IntegrationConfig }>
export type CreateIntegrationResponse = ApiSuccessResponse<Integration>
export type UpdateIntegrationResponse = ApiSuccessResponse<Integration>
export type DeleteIntegrationResponse = ApiSuccessResponse<{ message: string }>

export interface TriggerSyncResponse {
    status: 'success'
    message: string
    data: {
        sync_log_id: number
        status: string
        integration_id: number
        integration_type: string
    }
}

export interface IntegrationCustomersResponse {
    status: 'success'
    integration: {
        integration_id: number
        display_name: string
        integration_type: string
    }
    customers: IntegrationCustomer[]
}

export interface SyncLogsResponse {
    status: 'success'
    data: {
        integration: {
            integration_id: number
            display_name: string
            integration_type: string
        }
        latest_sync: {
            sync_id: number
            started_at: string
            completed_at: string | null
            status: string
            records_synced: number
            customers_mapped: Record<string, unknown>
        } | null
        sync_logs: SyncLog[]
    }
}

export type HealthCheckResponse = ApiSuccessResponse<IntegrationHealth>
export type RateLimitResponse = ApiSuccessResponse<RateLimitStatus>
export type HistoricalImportResponse = ApiSuccessResponse<HistoricalImportJob>
export type HistoricalJobsResponse = ApiSuccessResponse<HistoricalImportJob[]>
