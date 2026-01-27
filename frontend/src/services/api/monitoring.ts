/**
 * Type-safe API client for Monitoring endpoints
 */

import { api } from '@/lib/api'
import type {
    SystemStatusResponse,
    IntegrationMetricsResponse,
    ReportMetricsResponse,
    QueueMetricsResponse,
    ActivityTrendsResponse,
} from '@/types/api/monitoring'

export const monitoringApi = {
    /**
     * Get system status
     * GET /api/monitoring/status
     */
    async getSystemStatus(): Promise<SystemStatusResponse> {
        return api.get<SystemStatusResponse>('/monitoring/status')
    },

    /**
     * Get integration metrics
     * GET /api/monitoring/integrations
     */
    async getIntegrationMetrics(): Promise<IntegrationMetricsResponse> {
        return api.get<IntegrationMetricsResponse>('/monitoring/integrations')
    },

    /**
     * Get report metrics
     * GET /api/monitoring/reports
     */
    async getReportMetrics(): Promise<ReportMetricsResponse> {
        return api.get<ReportMetricsResponse>('/monitoring/reports')
    },

    /**
     * Get queue metrics
     * GET /api/monitoring/queue
     */
    async getQueueMetrics(): Promise<QueueMetricsResponse> {
        return api.get<QueueMetricsResponse>('/monitoring/queue')
    },

    /**
     * Get monthly report analytics
     * GET /api/monitoring/reports/monthly?months=6
     */
    async getMonthlyReportAnalytics(months: number = 6): Promise<{
        status: string
        data: {
            period_months: number
            monthly_data: Array<{
                date: string
                count: number
            }>
        }
    }> {
        return api.get(`/monitoring/reports/monthly?months=${months}`)
    },

    /**
     * Get activity trends
     * GET /api/monitoring/activity-trends?days=7
     */
    async getActivityTrends(days: number = 7): Promise<ActivityTrendsResponse> {
        return api.get<ActivityTrendsResponse>(`/monitoring/activity-trends?days=${days}`)
    },
}
