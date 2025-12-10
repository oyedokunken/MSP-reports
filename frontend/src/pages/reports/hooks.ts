import { useState, useEffect, useCallback } from 'react'
import { useSearchParams, useNavigate, useParams } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useReportsStore } from '@/stores/useReportsStore'
import { api } from '@/lib/api'
import type { ReportConfig, ReportType, EnabledReport } from './types'

export const useReportsList = () => {
  const toast = useToast()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const customerId = searchParams.get('customer')

  const isLoading = useReportsStore(state => state.isLoading)
  const customerName = useReportsStore(state => state.customerName)
  const downloading = useReportsStore(state => state.downloading)
  const deleting = useReportsStore(state => state.deleting)
  const searchQuery = useReportsStore(state => state.searchQuery)
  const statusFilters = useReportsStore(state => state.statusFilters)
  const reportTypeFilters = useReportsStore(state => state.reportTypeFilters)
  const fetchReports = useReportsStore(state => state.fetchReports)
  const downloadReport = useReportsStore(state => state.downloadReport)
  const deleteReport = useReportsStore(state => state.deleteReport)
  const setSearchQuery = useReportsStore(state => state.setSearchQuery)
  const setStatusFilters = useReportsStore(state => state.setStatusFilters)
  const setReportTypeFilters = useReportsStore(state => state.setReportTypeFilters)
  const getFilteredReports = useReportsStore(state => state.getFilteredReports)
  const getUniqueReportTypes = useReportsStore(state => state.getUniqueReportTypes)
  const currentPage = useReportsStore(state => state.currentPage)

  useEffect(() => {
    fetchReports(customerId).catch((err: unknown) => {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Failed to load report history', error.response?.data?.message || error.message)
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [customerId, fetchReports])

  const filteredReports = getFilteredReports()
  const uniqueReportTypes = getUniqueReportTypes()

  const handleDownload = async (reportId: number) => {
    const result = await downloadReport(reportId)
    if (result.success) {
      toast.success('Download Started', 'PDF report is downloading...')
    } else {
      toast.error('Download Failed', result.error)
    }
  }

  const handleDelete = async (reportId: number) => {
    if (!confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
      return
    }

    const result = await deleteReport(reportId)
    if (result.success) {
      toast.success('Report Deleted', 'Report has been deleted successfully')
      // Refetch to fill the gap in the current page
      fetchReports(customerId, currentPage, { background: true })
    } else {
      toast.error('Delete Failed', result.error)
    }
  }

  return {
    navigate,
    customerId,
    customerName,
    isLoading,
    downloading,
    deleting,
    searchQuery,
    statusFilters,
    reportTypeFilters,
    filteredReports,
    uniqueReportTypes,
    setSearchQuery,
    setStatusFilters,
    setReportTypeFilters,
    handleDownload,
    handleDelete
  }
}

export const useCustomerReports = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()

  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isGenerating, setIsGenerating] = useState(false)
  const [config, setConfig] = useState<ReportConfig | null>(null)
  const [destination, setDestination] = useState('')
  const [ccEmails, setCcEmails] = useState('')

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
      await api.post(`/reports/generate/${id}`)
      toast.success('Report Generated', 'Report has been generated successfully')
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Generation Failed', error.response?.data?.message || error.message)
    } finally {
      setIsGenerating(false)
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
    setDestination,
    setCcEmails,
    handleSaveDestination,
    handleGenerateReport
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
        integration_source: r.integration_source
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
    const defaultReportType = availableReportTypes.length === 1
      ? availableReportTypes[0].id
      : (availableReportTypes.find(r => r.id === 'uptime_monthly')?.id || availableReportTypes[0]?.id || 'uptime_monthly');

    const newReport: EnabledReport = {
      report_type: defaultReportType,
      frequency: 'monthly',
      time_of_day: '09:00',
      active: true
    };

    if (defaultReportType === 'monthly_comprehensive') {
      newReport.include_sections = ['uptime', 'tickets', 'backups', 'security'];
    }

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
      const response = await api.post(`/reports/generate/${id}`)
      const data = response as { data?: { data?: unknown[] } } | { data?: unknown[] }
      const responseData = (data as { data?: { data?: unknown[] } }).data || data as { data?: unknown[] }

      toast.success('Report Generated!', `Successfully generated ${responseData.data?.length || 1} report(s)`)

      setTimeout(() => {
        navigate('/platform/reports')
      }, 1500)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Generation Failed', error.response?.data?.message || error.message)
    } finally {
      setIsGenerating(false)
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
    setDestination,
    setCcEmails,
    handleAddReport,
    handleRemoveReport,
    handleUpdateReport,
    handleSaveConfiguration,
    handleGenerateNow
  }
}
