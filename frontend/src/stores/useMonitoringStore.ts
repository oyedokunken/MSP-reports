import { create } from 'zustand'
import { api } from '@/lib/api'

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
  recent_failures: Array<any>
}

export interface QueueMetrics {
  pending_jobs: number
  failed_jobs: number
  workers_status: string
}

interface MonitoringState {
  // Data
  systemStatus: SystemStatus | null
  integrationMetrics: IntegrationMetrics | null
  reportMetrics: ReportMetrics | null
  queueMetrics: QueueMetrics | null
  isLoading: boolean
  error: string | null
  lastUpdated: Date | null
  
  // Actions
  fetchSystemStatus: () => Promise<void>
  fetchIntegrationMetrics: () => Promise<void>
  fetchReportMetrics: () => Promise<void>
  fetchQueueMetrics: () => Promise<void>
  fetchAllMetrics: () => Promise<void>
}

export const useMonitoringStore = create<MonitoringState>((set) => ({
  // Initial state
  systemStatus: null,
  integrationMetrics: null,
  reportMetrics: null,
  queueMetrics: null,
  isLoading: true,
  error: null,
  lastUpdated: null,

  // Fetch system status
  fetchSystemStatus: async () => {
    try {
      const response = await api.get('/monitoring/status')
      const apiResponse = (response as any).data || response
      const data = apiResponse.data || apiResponse
      
      set({
        systemStatus: data,
        lastUpdated: new Date(),
      })
    } catch (err: any) {
      console.error('Failed to fetch system status:', err)
    }
  },

  // Fetch integration metrics
  fetchIntegrationMetrics: async () => {
    try {
      const response = await api.get('/monitoring/integrations')
      const apiResponse = (response as any).data || response
      const data = apiResponse.data || apiResponse
      
      set({
        integrationMetrics: data,
      })
    } catch (err: any) {
      console.error('Failed to fetch integration metrics:', err)
    }
  },

  // Fetch report metrics
  fetchReportMetrics: async () => {
    try {
      const response = await api.get('/monitoring/reports')
      const apiResponse = (response as any).data || response
      const data = apiResponse.data || apiResponse
      
      set({
        reportMetrics: data,
      })
    } catch (err: any) {
      console.error('Failed to fetch report metrics:', err)
    }
  },

  // Fetch queue metrics
  fetchQueueMetrics: async () => {
    try {
      const response = await api.get('/monitoring/queue')
      const apiResponse = (response as any).data || response
      const data = apiResponse.data || apiResponse
      
      set({
        queueMetrics: data,
      })
    } catch (err: any) {
      console.error('Failed to fetch queue metrics:', err)
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
      ])
      
      set({
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch monitoring data:', err)
      set({
        error: err.response?.data?.message || err.message || 'Failed to load monitoring data',
        isLoading: false,
      })
    }
  },
}))
