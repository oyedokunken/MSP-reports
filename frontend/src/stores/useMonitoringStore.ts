import { create } from 'zustand'
import { monitoringApi } from '@/services/api/monitoring'
import type {
  SystemStatus,
  IntegrationMetrics,
  ReportMetrics,
  QueueMetrics,
  ActivityTrends,
} from '@/types/api/monitoring'

interface MonitoringState {
  // Data
  systemStatus: SystemStatus | null
  integrationMetrics: IntegrationMetrics | null
  reportMetrics: ReportMetrics | null
  queueMetrics: QueueMetrics | null
  activityTrends: ActivityTrends | null
  isLoading: boolean
  error: string | null
  lastUpdated: Date | null

  // Actions
  fetchSystemStatus: () => Promise<void>
  fetchIntegrationMetrics: () => Promise<void>
  fetchReportMetrics: () => Promise<void>
  fetchQueueMetrics: () => Promise<void>
  fetchActivityTrends: () => Promise<void>
  fetchAllMetrics: () => Promise<void>
}

export const useMonitoringStore = create<MonitoringState>((set) => ({
  // Initial state
  systemStatus: null,
  integrationMetrics: null,
  reportMetrics: null,
  queueMetrics: null,
  activityTrends: null,
  isLoading: true,
  error: null,
  lastUpdated: null,

  // Fetch system status
  fetchSystemStatus: async () => {
    try {
      const response = await monitoringApi.getSystemStatus()

      set({
        systemStatus: response.data,
        lastUpdated: new Date(),
      })
    } catch (error) {
      console.error('Failed to fetch system status:', error)
    }
  },

  // Fetch integration metrics
  fetchIntegrationMetrics: async () => {
    try {
      const response = await monitoringApi.getIntegrationMetrics()

      set({
        integrationMetrics: response.data,
      })
    } catch (error) {
      console.error('Failed to fetch integration metrics:', error)
    }
  },

  // Fetch report metrics
  fetchReportMetrics: async () => {
    try {
      const response = await monitoringApi.getReportMetrics()

      set({
        reportMetrics: response.data,
      })
    } catch (error) {
      console.error('Failed to fetch report metrics:', error)
    }
  },

  // Fetch queue metrics
  fetchQueueMetrics: async () => {
    try {
      const response = await monitoringApi.getQueueMetrics()

      set({
        queueMetrics: response.data,
      })
    } catch (error) {
      console.error('Failed to fetch queue metrics:', error)
    }
  },

  // Fetch activity trends
  fetchActivityTrends: async () => {
    try {
      const response = await monitoringApi.getActivityTrends()

      set({
        activityTrends: response.data,
      })
    } catch (error) {
      console.error('Failed to fetch activity trends:', error)
    }
  },

  // Fetch all metrics
  fetchAllMetrics: async () => {
    set({ isLoading: true, error: null })
    try {
      await Promise.all([
        useMonitoringStore.getState().fetchSystemStatus(),
        useMonitoringStore.getState().fetchIntegrationMetrics(),
        useMonitoringStore.getState().fetchReportMetrics(),
        useMonitoringStore.getState().fetchQueueMetrics(),
        useMonitoringStore.getState().fetchActivityTrends(),
      ])

      set({
        isLoading: false,
      })
    } catch (error) {
      console.error('Failed to fetch monitoring data:', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to load monitoring data'
      set({
        error: errorMessage,
        isLoading: false,
      })
    }
  },
}))

// Re-export types for backward compatibility
export type { SystemStatus, IntegrationMetrics, ReportMetrics, QueueMetrics, ActivityTrends }
