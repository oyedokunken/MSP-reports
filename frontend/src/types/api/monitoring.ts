/**
 * Monitoring API types
 * Matches backend monitoring endpoints
 */

import type { ApiSuccessResponse } from './common'

export interface SystemStatus {
    system: {
        api: {
            status: string
            message: string
        }
        database: {
            status: string
            message: string
        }
        redis: {
            status: string
            message: string
        }
        storage: {
            status: string
            message: string
            free_bytes: number
            total_bytes: number
        }
        uptime: {
            message: string
        }
    }
    integrations: {
        total: number
        active: number
        error: number
        health: string
    }
    reports: {
        last_24_hours: {
            total: number
            completed: number
            failed: number
            success_rate: number
        }
        health: string
    }
    queue: {
        pending_jobs: number
        failed_jobs: number
        health: string
    }
}

export interface IntegrationMetrics {
    period_days: number
    integrations: Array<{
        integration_id: number
        integration_type: string
        display_name: string
        status: string
        last_sync_at: string
        last_sync_status: string
        metrics: {
            total_syncs: number
            successful_syncs: number
            failed_syncs: number
            success_rate: number
            avg_records_synced: number
            total_rate_limit_hits: number
        }
    }>
}

export interface ReportFailure {
    report_id: number
    customer_name: string
    report_type: string
    failed_at: string
    error_message: string
}

export interface ReportMetrics {
    period_days: number
    metrics: {
        total_reports: number
        completed_reports: number
        failed_reports: number
        success_rate: number
        delivered_reports: number
        failed_deliveries: number
        delivery_rate: number
        avg_generation_time_seconds: number
    }
    recent_failures: ReportFailure[]
}

export interface QueueMetrics {
    pending_jobs: number
    failed_jobs: number
    workers_status: string
}

// API Response Types
export type SystemStatusResponse = ApiSuccessResponse<SystemStatus>
export type IntegrationMetricsResponse = ApiSuccessResponse<IntegrationMetrics>
export type ReportMetricsResponse = ApiSuccessResponse<ReportMetrics>
export type QueueMetricsResponse = ApiSuccessResponse<QueueMetrics>
