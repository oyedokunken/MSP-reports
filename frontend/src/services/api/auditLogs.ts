import { api } from '@/lib/api'
import type { AuditLog, AuditLogsFilters, AuditLogStats } from '@/pages/audit-logs/types'

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
    // For exports, we might want to use a direct window.open or a blob download
    // But since our api util adds the prefix and token, let's construct the URL
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token')
    const searchParams = new URLSearchParams(params as Record<string, string>)
    if (token) searchParams.append('api_token', token) // If backend supports token in query
    
    // Better way: trigger the download via window.location for csv if token is handled
    // Or fetch as blob. Let's assume the user just wants the export triggered.
    const baseUrl = import.meta.env.VITE_API_BASE_URL || `${window.location.origin}/api`
    const url = `${baseUrl}/audit-logs/export?${searchParams.toString()}`
    
    window.open(url, '_blank')
  }
}
