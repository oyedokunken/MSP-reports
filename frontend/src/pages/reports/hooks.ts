import { useState, useEffect, useCallback, useMemo } from 'react'
import { useSearchParams, useNavigate, useParams } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useReportsStore } from '@/stores/useReportsStore'
import { api } from '@/lib/api'
import apiClient from '@/lib/api'
import type { ReportConfig, ReportType, EnabledReport } from './types'
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
      console.error('Failed to discard reports:', err)
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


export const useConfigureReports = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()

  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isGenerating, setIsGenerating] = useState(false)
  const [config, setConfig] = useState<ReportConfig | null>(null)
  const [enabledReports, setEnabledReports] = useState<EnabledReport[]>([])
  const [destination, setDestination] = useState('')
  const [ccEmails, setCcEmails] = useState('')
  const [availableReportTypes, setAvailableReportTypes] = useState<ReportType[]>([])
  const [previewReport, setPreviewReport] = useState<{ id: number; name: string } | null>(null)
  const [isDelivering, setIsDelivering] = useState(false)

  const fetchAvailableReports = useCallback(async () => {
    if (!id) return
    try {
      const response = await api.get<{ available_reports: ReportType[] }>(`/customers/${id}/available-reports`)
      const data = response as unknown as { data?: { available_reports: ReportType[] } } | { available_reports: ReportType[] }
      const reportData = (data as { data?: { available_reports: ReportType[] } }).data || data as { available_reports: ReportType[] }

      const reports = (reportData.available_reports || []).map((r) => ({
        id: r.id,
        name: r.name,
        description: r.description,
        integration_source: r.integration_source,
        frequency_options: r.frequency_options || ['monthly', 'weekly', 'daily'],
        has_data: r.has_data
      }))

      setAvailableReportTypes(reports)
    } catch (error) {
      console.error('Failed to fetch available reports:', error)
      toast.error('Failed to load available reports')
    }
  }, [id, toast])

  const fetchReportConfig = useCallback(async () => {
    if (!id) return
    try {
      setIsLoading(true)
      const response = await api.get<{ data: ReportConfig }>(`/customers/${id}/reports`)
      const data = response as unknown as { data?: ReportConfig } | ReportConfig
      const reportData = (data as { data?: ReportConfig }).data || data as ReportConfig

      setConfig(reportData)
      const schedules = reportData.report_schedules || []

      const mappedSchedules = schedules.map((s) => ({
        report_type: s.report_type,
        schedule_id: s.report_schedule_id,
        frequency: s.frequency,
        time_of_day: s.time_of_day,
        active: s.active,
        day_of_month: s.day_of_month,
        day_of_week: s.day_of_week,
        include_sections: s.include_sections,
        _old_schedule_id: undefined
      }))

      setEnabledReports(mappedSchedules)

      let emailDestination = ''
      let ccEmailsList: string[] = []

      if (reportData.email_destination) {
        emailDestination = reportData.email_destination
        ccEmailsList = reportData.cc_emails || []
      }
      else if (schedules.length > 0 && schedules[0].email_destination) {
        emailDestination = schedules[0].email_destination
        ccEmailsList = schedules[0].cc_emails || []
      }
      else if (reportData.delivery?.destination) {
        emailDestination = reportData.delivery.destination
        ccEmailsList = reportData.delivery.cc || []
      }

      setDestination(emailDestination)
      setCcEmails(Array.isArray(ccEmailsList) ? ccEmailsList.join(', ') : '')
    } catch (err: unknown) {
      console.error('Failed to load report configuration:', err)
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Failed to load configuration', error.response?.data?.message || error.message)
    } finally {
      setIsLoading(false)
    }
  }, [id, toast])

  useEffect(() => {
    fetchAvailableReports()
    fetchReportConfig()
  }, [fetchAvailableReports, fetchReportConfig])

  useEffect(() => {
    if (availableReportTypes.length > 0 && enabledReports.length > 0) {
      const validReportIds = availableReportTypes.map(r => r.id)
      const needsUpdate = enabledReports.some(report => !validReportIds.includes(report.report_type))

      if (needsUpdate) {
        const updatedReports = enabledReports.map(report => {
          if (!validReportIds.includes(report.report_type)) {
            return {
              ...report,
              report_type: availableReportTypes[0].id
            }
          }
          return report
        })
        setEnabledReports(updatedReports)
      }
    }
  }, [availableReportTypes, enabledReports])


  const handleAddReport = () => {
    // Use first available report type or monthly_comprehensive as fallback
    const defaultReportType = availableReportTypes.length > 0
      ? availableReportTypes[0].id
      : 'monthly_comprehensive';

    const newReport: EnabledReport = {
      report_type: defaultReportType,
      frequency: 'monthly',
      time_of_day: '09:00',
      active: true
    };

    setEnabledReports([
      ...enabledReports,
      newReport
    ])
  }

  const handleRemoveReport = async (index: number) => {
    const report = enabledReports[index]

    if (report.schedule_id) {
      try {
        await api.delete(`/reports/schedules/${report.schedule_id}`)
        toast.success('Deleted', 'Report schedule deleted successfully')
      } catch (err: unknown) {
        const error = err as { response?: { data?: { message?: string } }; message?: string }
        toast.error('Delete Failed', error.response?.data?.message || error.message)
        return
      }
    }

    setEnabledReports(enabledReports.filter((_, i) => i !== index))
  }

  const handleUpdateReport = (index: number, field: string, value: unknown) => {
    const updated = [...enabledReports]

    if (field === 'report_type' && updated[index].schedule_id) {
      const oldScheduleId = updated[index].schedule_id
      updated[index] = {
        ...updated[index],
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        [field]: value as any,
        schedule_id: undefined,
        _old_schedule_id: oldScheduleId
      }

      if (value === 'monthly_comprehensive') {
        updated[index].include_sections = ['uptime', 'tickets', 'backups', 'security']
      }
    } else {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      updated[index] = { ...updated[index], [field]: value as any }
    }

    setEnabledReports(updated)
  }

  const handleSaveConfiguration = async () => {
    try {
      setIsSaving(true)

      const oldSchedulesToDelete = enabledReports
        .filter(report => (report as { _old_schedule_id?: number })._old_schedule_id)
        .map(report => (report as { _old_schedule_id?: number })._old_schedule_id)

      for (const oldScheduleId of oldSchedulesToDelete) {
        try {
          await api.delete(`/reports/schedules/${oldScheduleId}`)
        } catch {
          // Continue anyway
        }
      }

      const ccArray = ccEmails
        .split(',')
        .map(email => email.trim())
        .filter(email => email.length > 0)

      const schedules = enabledReports.map(report => {
        const scheduleConfig: Record<string, unknown> = {}

        if (report.frequency === 'monthly' && (report as { day_of_month?: number }).day_of_month) {
          scheduleConfig.day_of_month = (report as { day_of_month?: number }).day_of_month
        }

        if (report.frequency === 'weekly' && (report as { day_of_week?: number }).day_of_week !== undefined) {
          scheduleConfig.day_of_week = (report as { day_of_week?: number }).day_of_week
        }

        if (report.report_type === 'monthly_comprehensive' && (report as { include_sections?: string[] }).include_sections) {
          scheduleConfig.include_sections = (report as { include_sections?: string[] }).include_sections
        }

        return {
          report_type: report.report_type,
          frequency: report.frequency || 'monthly',
          time_of_day: report.time_of_day || '09:00',
          active: report.active !== false,
          schedule_config: Object.keys(scheduleConfig).length > 0 ? scheduleConfig : null
        }
      })

      await api.patch(`/customers/${id}/reports`, {
        schedules: schedules
      })

      await api.patch(`/customers/${id}/report-destination`, {
        method: 'email',
        destination,
        cc: ccArray
      })

      toast.success('Saved', 'Report configuration updated successfully')
      fetchReportConfig()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Save Failed', error.response?.data?.message || error.message)
    } finally {
      setIsSaving(false)
    }
  }

  const handleGenerateNow = async () => {
    if (enabledReports.length === 0) {
      toast.error('No Reports Configured', 'Please add at least one report before generating')
      return
    }

    try {
      setIsGenerating(true)
      const response = await api.post<{ data: { reports: Record<string, unknown>[] } }>(`/reports/generate/${id}`, {
        preview_only: true
      })


      const responseData = response as unknown as { data?: { reports: Record<string, unknown>[] }, reports?: Record<string, unknown>[] };
      const reports = responseData.data?.reports || responseData.reports || [];

      if (reports.length > 0) {
        const report = reports[0] as { generated_report_id: number; customer_name: string; report_type: string };
        setPreviewReport({
          id: report.generated_report_id,
          name: `${report.customer_name} - ${report.report_type}`
        })
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
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Delivery Failed', error.response?.data?.message || error.message)
      throw err
    } finally {
      setIsDelivering(false)
    }
  }

  return {
    navigate,
    isLoading,
    isSaving,
    isGenerating,
    config,
    enabledReports,
    destination,
    ccEmails,
    availableReportTypes,
    previewReport,
    isDelivering,
    setDestination,
    setCcEmails,
    setPreviewReport,
    handleAddReport,
    handleRemoveReport,
    handleUpdateReport,
    handleSaveConfiguration,
    handleGenerateNow,
    handleDeliverReport
  }
}
