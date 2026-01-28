import { create } from 'zustand'
import apiClient, { api } from '@/lib/api'
import { logger } from '@/lib/logger'
import { PAGINATION } from '@/lib/constants'
import type { ReportHistoryResponse } from '@/types/api/report'


export interface GeneratedReport {
  generated_report_id: number
  customer_id: number
  customer_name: string
  report_type: string
  generated_at: string
  status: string
  file_size: number
  delivered: boolean
  delivered_at: string | null
  delivery_method: string
  error_message: string | null
}

interface ReportsState {
  reports: GeneratedReport[]
  isLoading: boolean
  customerName: string

  currentPage: number
  totalPages: number
  perPage: number
  total: number

  searchQuery: string
  statusFilters: string[]
  reportTypeFilters: string[]

  downloading: number | null

  deleting: number | null

  fetchReports: (customerId?: string | null, page?: number, options?: { background?: boolean }) => Promise<void>
  downloadReport: (reportId: number) => Promise<{ success: boolean; error?: string }>
  deleteReport: (reportId: number) => Promise<{ success: boolean; error?: string }>

  setSearchQuery: (query: string) => void
  setStatusFilters: (statuses: string[]) => void
  setReportTypeFilters: (types: string[]) => void

  getFilteredReports: () => GeneratedReport[]
  getUniqueReportTypes: () => string[]
}

export const useReportsStore = create<ReportsState>((set, get) => ({
  reports: [],
  isLoading: true,
  customerName: '',
  currentPage: 1,
  totalPages: 1,
  perPage: PAGINATION.REPORTS_PER_PAGE,
  total: 0,
  searchQuery: '',
  statusFilters: [],
  reportTypeFilters: [],
  downloading: null,
  deleting: null,

  fetchReports: async (customerId = null, page = 1, options = { background: false }) => {
    if (!options.background) {
      set({ isLoading: true })
    }
    try {
      const params = new URLSearchParams({ page: page.toString(), per_page: PAGINATION.REPORTS_PER_PAGE.toString() })
      if (customerId) params.append('customer_id', customerId)

      const url = `/reports/history?${params.toString()}`
      const response = await api.get<ReportHistoryResponse>(url)

      const reportsList = response.data.reports
      const pagination = response.data.pagination

      const customerName = customerId && reportsList.length > 0
        ? reportsList[0].customer_name
        : ''

      set({
        reports: reportsList,
        customerName,
        currentPage: pagination.current_page || page,
        totalPages: pagination.last_page || 1,
        total: pagination.total || reportsList.length,
        perPage: pagination.per_page || PAGINATION.REPORTS_PER_PAGE,
        isLoading: false,
      })
    } catch (error) {
      logger.apiError('/reports/history', error, { customerId, page })
      set({ isLoading: false, reports: [], currentPage: 1, totalPages: 1, total: 0 })
      throw error
    }
  },

  downloadReport: async (reportId: number) => {
    set({ downloading: reportId })
    try {
      const response = await apiClient.get(`/reports/${reportId}/download`, {
        responseType: 'blob',
      })

      const blob = response.data

      const contentDisposition = response.headers['content-disposition']
      let filename = `report_${reportId}.pdf`

      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
        if (filenameMatch && filenameMatch[1]) {
          filename = filenameMatch[1].replace(/['"]/g, '')
        }
      }

      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)

      set({ downloading: null })
      return { success: true }
    } catch (error) {
      set({ downloading: null })
      const errorMessage = error instanceof Error ? error.message : 'Download failed'
      return {
        success: false,
        error: errorMessage,
      }
    }
  },

  deleteReport: async (reportId: number) => {
    set({ deleting: reportId })
    try {
      await api.delete(`/reports/${reportId}`)

      const { reports } = get()
      set({
        reports: reports.filter(r => r.generated_report_id !== reportId),
        deleting: null
      })

      return { success: true }
    } catch (error) {
      set({ deleting: null })
      const errorMessage = error instanceof Error ? error.message : 'Delete failed'
      return {
        success: false,
        error: errorMessage,
      }
    }
  },

  setSearchQuery: (query: string) => set({ searchQuery: query }),
  setStatusFilters: (statuses: string[]) => set({ statusFilters: statuses }),
  setReportTypeFilters: (types: string[]) => set({ reportTypeFilters: types }),

  getFilteredReports: () => {
    const { reports, searchQuery, statusFilters, reportTypeFilters } = get()

    return reports.filter(report => {
      if (searchQuery && !report.customer_name.toLowerCase().includes(searchQuery.toLowerCase())) {
        return false
      }

      if (statusFilters.length > 0 && !statusFilters.includes(report.status)) {
        return false
      }

      if (reportTypeFilters.length > 0 && !reportTypeFilters.includes(report.report_type)) {
        return false
      }

      return true
    })
  },

  getUniqueReportTypes: () => {
    const { reports } = get()
    return Array.from(new Set(reports.map(r => r.report_type)))
  },
}))
