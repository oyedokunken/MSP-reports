import { api } from '@/lib/api'
import apiClient from '@/lib/api'
import type { AuditLog, AuditLogsFilters, AuditLogStats } from '@/pages/audit-logs/types'
import type { AxiosResponse } from 'axios'

export interface AuditLogListResponse {
  status: string
  data: AuditLog[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}
export interface AuditLogStatsResponse {
  status: string
  data: AuditLogStats
}

export const auditLogsService = {
  getAuditLogs: async (params: Partial<AuditLogsFilters & { page?: number; per_page?: number }>) => {
    return api.get<AuditLogListResponse>('/audit-logs', params as Record<string, unknown>)
  },

  getStats: async () => {
    return api.get<AuditLogStatsResponse>('/audit-logs/stats')
  },

  exportLogs: async (params: Partial<AuditLogsFilters>) => {
    const response: AxiosResponse<Blob> = await apiClient.get('/audit-logs/export', {
      params,
      responseType: 'blob',
    })

    const blob = new Blob([response.data], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `audit_logs_${new Date().toISOString().slice(0, 19).replace(/[T:]/g, '_')}.csv`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  }
}
