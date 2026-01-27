import { create } from 'zustand'
import { logger } from '@/lib/logger'
import type { AuditLog, AuditLogsFilters, AuditLogStats } from '@/pages/audit-logs/types'
import { auditLogsService, type AuditLogListResponse, type AuditLogStatsResponse } from '@/services/api/auditLogs'

export interface AuditLogsState {
  auditLogs: AuditLog[]
  stats: AuditLogStats | null
  isLoading: boolean
  error: string | null
  currentPage: number
  totalPages: number
  totalLogs: number
  filters: AuditLogsFilters

  // Actions
  fetchAuditLogs: () => Promise<void>
  fetchStats: () => Promise<void>
  setPage: (page: number) => void
  setFilters: (filters: Partial<AuditLogsFilters>) => void
  exportLogs: () => Promise<void>
}

export const useAuditLogsStore = create<AuditLogsState>((set, get) => ({
  auditLogs: [],
  stats: null,
  isLoading: false,
  error: null,
  currentPage: 1,
  totalPages: 1,
  totalLogs: 0,
  filters: {
    search: '',
    event_type: '',
    actor_type: '',
    target_type: '',
    date_from: '',
    date_to: '',
  },

  fetchAuditLogs: async () => {
    set({ isLoading: true, error: null })

    try {
      const { filters, currentPage } = get()
      const response: AuditLogListResponse = await auditLogsService.getAuditLogs({
        ...filters,
        page: currentPage,
        per_page: 10,
      })

      const { data, pagination } = response

      set({
        auditLogs: data,
        totalLogs: pagination.total,
        totalPages: pagination.last_page,
        isLoading: false,
      })
    } catch (error: unknown) {
      const err = error as { message?: string }
      set({
        error: err.message || 'Failed to fetch audit logs',
        isLoading: false,
      })
    }
  },

  fetchStats: async () => {
    try {
      const response: AuditLogStatsResponse = await auditLogsService.getStats()
      set({ stats: response.data })
    } catch (error) {
      logger.apiError('/audit-logs/stats', error)
    }
  },

  setPage: (page: number) => {
    set({ currentPage: page })
    get().fetchAuditLogs()
  },

  setFilters: (newFilters: Partial<AuditLogsFilters>) => {
    set((state) => ({
      filters: { ...state.filters, ...newFilters },
      currentPage: 1, // Reset to first page when filters change
    }))
    get().fetchAuditLogs()
  },

  exportLogs: async () => {
    const { filters } = get()
    try {
      await auditLogsService.exportLogs(filters)
    } catch (error) {
      logger.apiError('/audit-logs/export', error, { filters })
    }
  },
}))

