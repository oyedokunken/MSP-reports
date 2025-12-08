/**
 * Type-safe API client for Monitoring endpoints
 */

import { api } from '@/lib/api'
import type {
    SystemStatusResponse,
    IntegrationMetricsResponse,
    ReportMetricsResponse,
    QueueMetricsResponse,
} from '@/types/api/monitoring'

export const monitoringApi = {
    /**
     * Get system status
     * GET /api/monitoring/status
     */
    async getSystemStatus(): Promise<SystemStatusResponse> {
        return api.get('/monitoring/status') as unknown as Promise<SystemStatusResponse>
    },

    /**
     * Get integration metrics
     * GET /api/monitoring/integrations
     */
    async getIntegrationMetrics(): Promise<IntegrationMetricsResponse> {
        return api.get('/monitoring/integrations') as unknown as Promise<IntegrationMetricsResponse>
    },

    /**
     * Get report metrics
     * GET /api/monitoring/reports
     */
    async getReportMetrics(): Promise<ReportMetricsResponse> {
        return api.get('/monitoring/reports') as unknown as Promise<ReportMetricsResponse>
    },

    /**
     * Get queue metrics
     * GET /api/monitoring/queue
     */
    async getQueueMetrics(): Promise<QueueMetricsResponse> {
        return api.get('/monitoring/queue') as unknown as Promise<QueueMetricsResponse>
    },
}
