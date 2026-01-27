import { useState, useEffect, useCallback, useMemo } from 'react'
import { useSearchParams, useNavigate, useParams } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useReportsStore } from '@/stores/useReportsStore'
import apiClient, { api } from '@/lib/api'
import type { ReportConfig } from './types'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import type { ReportHistoryResponse } from '@/types/api/report'

export const useReportsList = () => {
  const toast = useToast()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const customerId = searchParams.get('customer')
  const page = parseInt(searchParams.get('page') || '1')

  const searchQuery = useReportsStore(state => state.searchQuery)
  const statusFilters = useReportsStore(state => state.statusFilters)
  const reportTypeFilters = useReportsStore(state => state.reportTypeFilters)
  const setSearchQuery = useReportsStore(state => state.setSearchQuery)
  const setStatusFilters = useReportsStore(state => state.setStatusFilters)
  const setReportTypeFilters = useReportsStore(state => state.setReportTypeFilters)

  const [selectedReportIds, setSelectedReportIds] = useState<number[]>([])

  const toggleReportSelection = (reportId: number) => {
    setSelectedReportIds(prev =>
      prev.includes(reportId)
        ? prev.filter(id => id !== reportId)
        : [...prev, reportId]
    )
  }

  const clearSelection = () => setSelectedReportIds([])

  // React Query for fetching reports
  const { data: reportData, isLoading } = useQuery({
    queryKey: ['reports', customerId, page],
    queryFn: async () => {
      const params = new URLSearchParams({ page: page.toString(), per_page: '9' })
      if (customerId) params.append('customer_id', customerId)

      const response = await api.get(`/reports/history?${params.toString()}`) as unknown as ReportHistoryResponse
      return response.data
    }
  })

  const reports = useMemo(() => reportData?.reports || [], [reportData])
  const pagination = reportData?.pagination || { current_page: 1, last_page: 1, total: 0, per_page: 9 }
  const customerName = customerId && reports.length > 0 ? reports[0].customer_name : ''

  const setPage = (newPage: number) => {
    const params = new URLSearchParams(searchParams)
    params.set('page', newPage.toString())
    setSearchParams(params)
  }

  // Derived state (filtering)
  const filteredReports = useMemo(() => {
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
  }, [reports, searchQuery, statusFilters, reportTypeFilters])

  const uniqueReportTypes = useMemo(() => {
    return Array.from(new Set(reports.map(r => r.report_type)))
  }, [reports])

  // Mutations
  const downloadMutation = useMutation({
    mutationFn: async (reportId: number) => {
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
    },
    onSuccess: () => {
      toast.success('Download Started', 'PDF report is downloading...')
    },
    onError: (error: Error) => {
      toast.error('Download Failed', error.message)
    }
  })

  const deleteMutation = useMutation({
    mutationFn: async (reportId: number) => {
      await api.delete(`/reports/${reportId}`)
    },
    onSuccess: () => {
      toast.success('Report Deleted', 'Report has been deleted successfully')
      queryClient.invalidateQueries({ queryKey: ['reports'] })
    },
    onError: (error: Error) => {
      toast.error('Delete Failed', error.message)
    }
  })

  const batchDeleteMutation = useMutation({
    mutationFn: async (reportIds: number[]) => {
      await apiClient.delete('/reports', { data: { report_ids: reportIds } })
    },
    onSuccess: (_, variables) => {
      toast.success('Reports Deleted', `Successfully deleted ${variables.length} reports`)
      setSelectedReportIds([])
      queryClient.invalidateQueries({ queryKey: ['reports'] })
    },
    onError: (error: Error) => {
      toast.error('Bulk Delete Failed', error.message)
    }
  })

  return {
    navigate,
    customerId,
    customerName,
    isLoading,
    downloading: downloadMutation.isPending ? downloadMutation.variables : null,
    deleting: deleteMutation.isPending ? deleteMutation.variables : (batchDeleteMutation.isPending ? 'batch' : null),
    isBulkDeleting: batchDeleteMutation.isPending,
    searchQuery,
    statusFilters,
    reportTypeFilters,
    filteredReports,
    uniqueReportTypes,
    setSearchQuery,
    setStatusFilters,
    setReportTypeFilters,
    pagination,
    setPage,
    handleDownload: (id: number) => downloadMutation.mutate(id),
    handleDelete: (id: number) => deleteMutation.mutate(id),
    handleBatchDelete: () => {
      if (selectedReportIds.length > 0) {
        batchDeleteMutation.mutate(selectedReportIds)
      }
    },
    selectedReportIds,
    toggleReportSelection,
    clearSelection
  }
}

export const useCustomerReports = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()

  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isGenerating, setIsGenerating] = useState(false)
  const [isDelivering, setIsDelivering] = useState(false)
  const [config, setConfig] = useState<ReportConfig | null>(null)
  const [destination, setDestination] = useState('')
  const [ccEmails, setCcEmails] = useState('')
  const [previewReport, setPreviewReport] = useState<{ id: number; name: string } | null>(null)
  const [batchPreviewReports, setBatchPreviewReports] = useState<{ id: number; name: string; status: 'preview' | 'delivered' }[]>([])

  const fetchReportConfig = useCallback(async () => {
    try {
      setIsLoading(true)
      const response = await api.get<{ data: ReportConfig }>(`/customers/${id}/reports`)
      const data = response as unknown as { data?: ReportConfig } | ReportConfig
      const reportData = (data as { data?: ReportConfig }).data || data as ReportConfig

      const configData = {
        ...reportData,
        enabled_reports: reportData.enabled_reports || [],
        delivery: reportData.delivery || { method: 'email', destination: '', cc: [] },
        report_branding: reportData.report_branding || {}
      }

      setConfig(configData)
      setDestination(configData.delivery?.destination || '')
      setCcEmails(configData.delivery?.cc?.join(', ') || '')
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Failed to load report configuration', error.response?.data?.message || error.message)
    } finally {
      setIsLoading(false)
    }
  }, [id, toast])

  useEffect(() => {
    fetchReportConfig()
  }, [fetchReportConfig])

  const handleSaveDestination = async () => {
    try {
      setIsSaving(true)

      const ccArray = ccEmails
        .split(',')
        .map(email => email.trim())
        .filter(email => email.length > 0)

      await api.patch(`/customers/${id}/report-destination`, {
        method: 'email',
        destination,
        cc: ccArray
      })

      toast.success('Saved', 'Report destination updated successfully')
      fetchReportConfig()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Save Failed', error.response?.data?.message || error.message)
    } finally {
      setIsSaving(false)
    }
  }

  const handleGenerateReport = async () => {
    try {
      setIsGenerating(true)
      const response = await api.post<{ data?: { reports: Array<Record<string, unknown>> } | Array<Record<string, unknown>> }>(`/reports/generate/${id}`, {
        preview_only: true
      })

      const responseData = response.data;
      let reports: Array<Record<string, unknown>> = [];

      if (Array.isArray(responseData)) {
        reports = responseData;
      } else if (responseData && typeof responseData === 'object' && 'reports' in responseData && Array.isArray((responseData as { reports: Array<Record<string, unknown>> }).reports)) {
        reports = (responseData as { reports: Array<Record<string, unknown>> }).reports;
      }

      if (reports.length > 0) {
        const previewResults = reports.map((r) => {
          const report = r as { generated_report_id: number; report_name?: string; customer_name?: string; report_type?: string };
          return {
            id: report.generated_report_id,
            name: report.report_name || `${report.customer_name} - ${report.report_type?.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase()) || 'Report'}`,
            status: 'preview' as const
          };
        })

        if (previewResults.length === 1) {
          setPreviewReport(previewResults[0])
        } else {
          setBatchPreviewReports(previewResults)
        }
      } else {
        toast.success('Report Generated', 'Report has been generated successfully')
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Generation Failed', error.response?.data?.message || error.message)
    } finally {
      setIsGenerating(false)
    }
  }

  const handleDeliverReport = async () => {
    if (!previewReport) return
    try {
      setIsDelivering(true)
      await api.post(`/reports/${previewReport.id}/deliver`)
      toast.success('Report Delivered', 'Report has been queued for delivery')
      setPreviewReport(null)

      if (batchPreviewReports.length > 0) {
        setBatchPreviewReports(prev =>
          prev.map(r => r.id === previewReport.id ? { ...r, status: 'delivered' } : r)
        )
      } else {
        queryClient.invalidateQueries({ queryKey: ['reports'] })
        navigate('/platform/reports')
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Delivery Failed', error.response?.data?.message || error.message)
      throw err
    } finally {
      setIsDelivering(false)
    }
  }

  const handleDeliverAllReports = async () => {
    if (batchPreviewReports.length === 0) return
    try {
      setIsDelivering(true)
      const pendingReports = batchPreviewReports.filter(r => r.status === 'preview')
      await Promise.all(
        pendingReports.map(report => api.post(`/reports/${report.id}/deliver`))
      )
      toast.success('Batch Delivery Initiated', `${pendingReports.length} reports have been queued for delivery`)
      setBatchPreviewReports([])
      queryClient.invalidateQueries({ queryKey: ['reports'] })
      navigate('/platform/reports')
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Batch Delivery Failed', error.response?.data?.message || error.message)
      throw err
    } finally {
      setIsDelivering(false)
    }
  }

  const handleDiscardReports = async () => {
    if (batchPreviewReports.length === 0) return
    try {
      const reportIds = batchPreviewReports
        .filter(r => r.status === 'preview')
        .map(r => r.id)

      if (reportIds.length > 0) {
        await api.post('/reports/batch-discard', { report_ids: reportIds })
      }

      setBatchPreviewReports([])
    } catch (err: unknown) {
      logger.error('Failed to discard reports:', err)
      setBatchPreviewReports([]) // Still clear UI
    }
  }

  return {
    navigate,
    isLoading,
    isSaving,
    isGenerating,
    config,
    destination,
    ccEmails,
    previewReport,
    batchPreviewReports,
    isDelivering,
    setDestination,
    setCcEmails,
    setPreviewReport,
    setBatchPreviewReports,
    handleSaveDestination,
    handleGenerateReport,
    handleDeliverReport,
    handleDeliverAllReports,
    handleDiscardReports
  }
}
