import { create } from 'zustand'
import { api } from '@/lib/api'
import apiClient from '@/lib/api'
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
  // Data
  reports: GeneratedReport[]
  isLoading: boolean
  customerName: string

  // Pagination
  currentPage: number
  totalPages: number
  perPage: number
  total: number

  // Filters
  searchQuery: string
  statusFilters: string[]
  reportTypeFilters: string[]

  // Download state
  downloading: number | null

  // Delete state
  deleting: number | null

  // Actions
  fetchReports: (customerId?: string | null, page?: number, options?: { background?: boolean }) => Promise<void>
  downloadReport: (reportId: number) => Promise<{ success: boolean; error?: string }>
  deleteReport: (reportId: number) => Promise<{ success: boolean; error?: string }>

  // Filter actions
  setSearchQuery: (query: string) => void
  setStatusFilters: (statuses: string[]) => void
  setReportTypeFilters: (types: string[]) => void

  // Computed
  getFilteredReports: () => GeneratedReport[]
  getUniqueReportTypes: () => string[]
}

export const useReportsStore = create<ReportsState>((set, get) => ({
  // Initial state
  reports: [],
  isLoading: true,
  customerName: '',
  currentPage: 1,
  totalPages: 1,
  perPage: 9,
  total: 0,
  searchQuery: '',
  statusFilters: [],
  reportTypeFilters: [],
  downloading: null,
  deleting: null,

  // Fetch reports
  fetchReports: async (customerId = null, page = 1, options = { background: false }) => {
    if (!options.background) {
      set({ isLoading: true })
    }
    try {
      const params = new URLSearchParams({ page: page.toString(), per_page: '9' })
      if (customerId) params.append('customer_id', customerId)

      const url = `/reports/history?${params.toString()}`
      const response = await api.get(url) as unknown as ReportHistoryResponse

      const reportsList = response.data.reports
      const pagination = response.data.pagination

      // Set customer name from first report if filtering by customer
      const customerName = customerId && reportsList.length > 0
        ? reportsList[0].customer_name
        : ''

      set({
        reports: reportsList,
        customerName,
        currentPage: pagination.current_page || page,
        totalPages: pagination.last_page || 1,
        total: pagination.total || reportsList.length,
        perPage: pagination.per_page || 9,
        isLoading: false,
      })
    } catch (error) {
      console.error('Failed to fetch reports:', error)
      set({ isLoading: false, reports: [], currentPage: 1, totalPages: 1, total: 0 })
      throw error
    }
  },

  // Download report
  downloadReport: async (reportId: number) => {
    set({ downloading: reportId })
    try {
      // Use apiClient directly for blob downloads
      const response = await apiClient.get(`/reports/${reportId}/download`, {
        responseType: 'blob',
      })

      const blob = response.data

      // Extract filename from Content-Disposition header or use default
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

  // Delete report
  deleteReport: async (reportId: number) => {
    set({ deleting: reportId })
    try {
      await api.delete(`/reports/${reportId}`)

      // Remove the report from the list
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

  // Filter actions
  setSearchQuery: (query: string) => set({ searchQuery: query }),
  setStatusFilters: (statuses: string[]) => set({ statusFilters: statuses }),
  setReportTypeFilters: (types: string[]) => set({ reportTypeFilters: types }),

  // Computed - get filtered reports
  getFilteredReports: () => {
    const { reports, searchQuery, statusFilters, reportTypeFilters } = get()

    return reports.filter(report => {
      // Search filter
      if (searchQuery && !report.customer_name.toLowerCase().includes(searchQuery.toLowerCase())) {
        return false
      }

      // Status filter
      if (statusFilters.length > 0 && !statusFilters.includes(report.status)) {
        return false
      }

      // Report type filter
      if (reportTypeFilters.length > 0 && !reportTypeFilters.includes(report.report_type)) {
        return false
      }

      return true
    })
  },

  // Computed - get unique report types
  getUniqueReportTypes: () => {
    const { reports } = get()
    return Array.from(new Set(reports.map(r => r.report_type)))
  },
}))
