import { create } from 'zustand'
import { api } from '@/lib/api'
import apiClient from '@/lib/api'

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
  
  // Filters
  searchQuery: string
  statusFilter: string
  reportTypeFilter: string
  
  // Download state
  downloading: number | null
  
  // Actions
  fetchReports: (customerId?: string | null) => Promise<void>
  downloadReport: (reportId: number) => Promise<{ success: boolean; error?: string }>
  
  // Filter actions
  setSearchQuery: (query: string) => void
  setStatusFilter: (status: string) => void
  setReportTypeFilter: (type: string) => void
  
  // Computed
  getFilteredReports: () => GeneratedReport[]
  getUniqueReportTypes: () => string[]
}

export const useReportsStore = create<ReportsState>((set, get) => ({
  // Initial state
  reports: [],
  isLoading: true,
  customerName: '',
  searchQuery: '',
  statusFilter: 'all',
  reportTypeFilter: 'all',
  downloading: null,

  // Fetch reports
  fetchReports: async (customerId = null) => {
    set({ isLoading: true })
    try {
      const url = customerId 
        ? `/reports/history?customer_id=${customerId}`
        : '/reports/history'
      const response = await api.get<any>(url)
      
      // Handle nested response structure from API
      // API returns: { status: 'success', data: { reports: [...], pagination: {...} } }
      const responseData = (response as any).data || response
      const reportsList = responseData.data?.reports || responseData.reports || []
      
      console.log('Reports fetched:', reportsList)
      
      // Set customer name from first report if filtering by customer
      const customerName = customerId && reportsList.length > 0 
        ? reportsList[0].customer_name 
        : ''
      
      set({
        reports: reportsList,
        customerName,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch reports:', err)
      set({ isLoading: false, reports: [] })
      throw err
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
    } catch (err: any) {
      set({ downloading: null })
      return {
        success: false,
        error: err.response?.data?.message || err.message,
      }
    }
  },

  // Filter actions
  setSearchQuery: (query: string) => set({ searchQuery: query }),
  setStatusFilter: (status: string) => set({ statusFilter: status }),
  setReportTypeFilter: (type: string) => set({ reportTypeFilter: type }),

  // Computed - get filtered reports
  getFilteredReports: () => {
    const { reports, searchQuery, statusFilter, reportTypeFilter } = get()
    
    return reports.filter(report => {
      // Search filter
      if (searchQuery && !report.customer_name.toLowerCase().includes(searchQuery.toLowerCase())) {
        return false
      }
      
      // Status filter
      if (statusFilter !== 'all' && report.status !== statusFilter) {
        return false
      }
      
      // Report type filter
      if (reportTypeFilter !== 'all' && report.report_type !== reportTypeFilter) {
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
