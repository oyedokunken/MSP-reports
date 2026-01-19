import { useEffect, useState, useCallback } from 'react'
import { flushSync } from 'react-dom'
import { useParams, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ArrowLeft, Calendar, Mail, Save, Bell, Eye, Plus, Share2, Folder, RotateCw } from 'lucide-react'
import { api } from '@/lib/api'
import { Spinner } from '@/components/ui/spinner'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { ConfigureReportsLoading, ConfigureReportsNotFound } from './components/ConfigureReportsStates'
import { ScheduleCard } from './components/ScheduleCard'
import { FileBrowserModal } from '@/components/sharepoint/FileBrowserModal'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ReportPreviewModal } from '@/components/reports/ReportPreviewModal'
import { BatchPreviewModal } from './components/BatchPreviewModal'

import type { ReportConfig, ReportType, EnabledReport } from './types'

export default function ConfigureReports() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()

  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isGenerating, setIsGenerating] = useState(false)
  const [selectedSchedules, setSelectedSchedules] = useState<number[]>([])
  const [modifiedSchedules, setModifiedSchedules] = useState<Set<number>>(new Set())
  const [config, setConfig] = useState<ReportConfig | null>(null)
  const [enabledReports, setEnabledReports] = useState<EnabledReport[]>([])
  const [destination, setDestination] = useState('')
  const [ccEmails, setCcEmails] = useState('')
  const [originalDestination, setOriginalDestination] = useState('')
  const [originalCcEmails, setOriginalCcEmails] = useState('')
  const [deliveryMethod, setDeliveryMethod] = useState('email')
  const [sharepointPath, setSharepointPath] = useState('')
  const [originalDeliveryMethod, setOriginalDeliveryMethod] = useState('email')
  const [originalSharepointPath, setOriginalSharepointPath] = useState('')
  const [isFileBrowserOpen, setIsFileBrowserOpen] = useState(false)
  const [sharepointIntegrationId, setSharepointIntegrationId] = useState<number | null>(null)
  const [sharepointIntegrations, setSharepointIntegrations] = useState<Array<{ integration_id: number; integration_type: string; display_name?: string }>>([])
  const [availableReportTypes, setAvailableReportTypes] = useState<ReportType[]>([])
  const [showValidationModal, setShowValidationModal] = useState(false)
  const [validationErrors, setValidationErrors] = useState<string[]>([])
  const [showGenerateModal, setShowGenerateModal] = useState(false)
  const [customStartDate, setCustomStartDate] = useState('')
  const [customEndDate, setCustomEndDate] = useState('')
  const [generationProgress, setGenerationProgress] = useState({ current: 0, total: 0 })
  const [failedSchedules, setFailedSchedules] = useState<Array<{ scheduleId: number; error: string }>>([])
  const [previewReport, setPreviewReport] = useState<{ id: number; name: string } | null>(null)
  const [batchPreviewReports, setBatchPreviewReports] = useState<{ id: number; name: string; status: 'preview' | 'delivered' }[]>([])

  // Validate and fix invalid report types when availableReportTypes loads
  useEffect(() => {
    if (availableReportTypes.length > 0 && enabledReports.length > 0) {
      const validReportIds = availableReportTypes.map(r => r.id)
      const needsUpdate = enabledReports.some(report => !validReportIds.includes(report.report_type))

      if (needsUpdate) {
        const updatedReports = enabledReports.map(report => {
          // If report type is invalid, use first available
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

  const fetchAvailableReports = useCallback(async () => {
    try {
      const response = await api.get<{ available_reports: ReportType[] }>(`/customers/${id}/available-reports`)
      const data = response as unknown as { data?: { available_reports: ReportType[] } } | { available_reports: ReportType[] }
      const reportData = (data as { data?: { available_reports: ReportType[] } }).data || data as { available_reports: ReportType[] }

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const reports = (reportData.available_reports || []).map((r: any) => ({
        id: r.report_type || r.id,
        name: r.name,
        description: r.description,
        integration_source: r.integration_source,
        frequency_options: r.frequency_options || ['on-demand'],
        has_data: r.has_data
      }))

      setAvailableReportTypes(reports)
    } catch (error) {
      console.error('Failed to fetch available reports:', error)
      toast.error('Failed to load available reports')
    }
  }, [id, toast])

  const fetchReportConfig = useCallback(async () => {
    try {
      setIsLoading(true)
      const response = await api.get<{ data: ReportConfig }>(`/customers/${id}/reports`)
      const data = response as unknown as { data?: ReportConfig } | ReportConfig
      const reportData = (data as { data?: ReportConfig }).data || data as ReportConfig

      setConfig(reportData)
      const schedules = reportData.report_schedules || []

      const mappedSchedules = schedules.map((s) => {
        return {
          report_type: s.report_type,
          schedule_id: s.report_schedule_id,
          frequency: s.frequency,
          time_of_day: s.time_of_day,
          active: s.active,
          is_customized: !!s.is_customized,
          template_id: s.template_id,
          template_name: s.template_name,
          template_description: s.template_description,
          day_of_month: s.day_of_month,
          day_of_week: s.day_of_week,
          include_sections: s.schedule_config?.included_sections || s.include_sections,
          _old_schedule_id: undefined
        };
      })

      const schedulesWithDataCheck = await Promise.all(
        mappedSchedules.map(async (schedule) => {
          try {
            const checkResponse = await api.get<{ available_reports: ReportType[] }>(
              `/customers/${id}/available-reports?frequency=${schedule.frequency}`
            )
            const checkData = checkResponse as unknown as { data?: { available_reports: ReportType[] } } | { available_reports: ReportType[] }
            const reportData = (checkData as { data?: { available_reports: ReportType[] } }).data || checkData as { available_reports: ReportType[] }

            const reportType = reportData.available_reports?.find(r => r.id === schedule.report_type)
            return {
              ...schedule,
              has_data: reportType?.has_data ?? true
            }
          } catch (error) {
            console.error(`Failed to check data for schedule ${schedule.schedule_id}:`, error)
            return { ...schedule, has_data: true }
          }
        })
      )

      setEnabledReports(schedulesWithDataCheck)

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
      setOriginalDestination(emailDestination)
      setOriginalCcEmails(Array.isArray(ccEmailsList) ? ccEmailsList.join(', ') : '')

      // SharePoint Config
      const currentMethod = reportData.delivery?.method || 'email'
      setDeliveryMethod(currentMethod)
      setOriginalDeliveryMethod(currentMethod)
      setSharepointPath(reportData.delivery?.path || '')
      setOriginalSharepointPath(reportData.delivery?.path || '')

      // Check for SharePoint integration (company-wide)
      try {
        const intResponse = await api.get<{ configured: Array<{ integration_id: number; integration_type: string; display_name?: string }> }>('/integrations')
        const integrations = (intResponse as unknown as { configured?: Array<{ integration_id: number; integration_type: string; display_name?: string }> }).configured || []
        const spIntegrations = Array.isArray(integrations)
          ? integrations.filter((i) => i.integration_type?.toLowerCase().includes('sharepoint'))
          : []

        setSharepointIntegrations(spIntegrations)

        // If config has an ID, use it. 
        if (reportData.delivery?.integration_id) {
          setSharepointIntegrationId(reportData.delivery.integration_id)
        }
        // Else if only one exists, default to it
        else if (spIntegrations.length === 1) {
          setSharepointIntegrationId(spIntegrations[0].integration_id)
        }
        // If config.integration_id is missing but we have SP, try to match by old logic or null
        else if (!reportData.delivery?.integration_id && spIntegrations.length > 0) {
          const first = spIntegrations[0]
          setSharepointIntegrationId(first.integration_id)
        }
      } catch (e) {
        console.warn('Failed to fetch integrations', e)
      }

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


  const handleUpdateReport = (index: number, field: keyof EnabledReport, value: string | number | boolean | string[]) => {
    const updated = [...enabledReports]
    const report = updated[index]

    // Mark this schedule as modified
    if (report.schedule_id) {
      setModifiedSchedules(prev => new Set(prev).add(report.schedule_id!))
    }

    // If changing frequency, set default day values
    if (field === 'frequency') {
      if (value === 'monthly' && !report.day_of_month) {
        updated[index] = { ...updated[index], frequency: value as string, day_of_month: 1 } as EnabledReport
      } else if (value === 'weekly' && report.day_of_week === undefined) {
        updated[index] = { ...updated[index], frequency: value as string, day_of_week: 0 } as EnabledReport
      } else {
        updated[index] = { ...updated[index], [field]: value } as EnabledReport
      }
    } else {
      updated[index] = { ...updated[index], [field]: value } as EnabledReport
    }

    setEnabledReports(updated)
  }

  const handleResetSchedule = async (scheduleId: number) => {
    try {
      setIsSaving(true)
      await api.post(`/reports/schedules/${scheduleId}/reset`)
      toast.success('Schedule reset to template')
      await fetchReportConfig()
    } catch (error) {
      console.error('Failed to reset schedule:', error)
      toast.error('Failed to reset schedule')
    } finally {
      setIsSaving(false)
    }
  }

  // Validate configuration before saving
  const validateConfiguration = (): string[] => {
    const errors: string[] = []

    if (enabledReports.length === 0) {
      errors.push('No reports configured. Please add at least one report schedule.')
    }

    if (enabledReports.length > 0) {
      if (deliveryMethod === 'email') {
        // Check if email destination is provided
        if (!destination.trim()) {
          errors.push('Email destination is required for report delivery.')
        } else {
          // Validate email format
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
          if (!emailRegex.test(destination.trim())) {
            errors.push('Please enter a valid email address for the destination.')
          }
        }

        // Validate CC emails
        if (ccEmails.trim()) {
          const ccArray = ccEmails.split(',').map(e => e.trim()).filter(e => e)
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
          const invalidCCs = ccArray.filter(email => !emailRegex.test(email))
          if (invalidCCs.length > 0) {
            errors.push(`Invalid CC email(s): ${invalidCCs.join(', ')}`)
          }
        }
      } else if (deliveryMethod === 'sharepoint') {
        if (!sharepointPath.trim()) {
          errors.push('SharePoint folder path is required.')
        }
      }
    }

    return errors
  }

  const handleSaveConfiguration = async () => {
    // Validate before saving
    const errors = validateConfiguration()
    if (errors.length > 0) {
      setValidationErrors(errors)
      setShowValidationModal(true)
      return
    }

    try {
      setIsSaving(true)
      const updatePromises = []

      // Update only modified schedules
      for (const report of enabledReports) {
        if (report.schedule_id && modifiedSchedules.has(report.schedule_id)) {
          const scheduleConfig: Record<string, unknown> = {}

          // Add day_of_month for monthly reports
          if (report.frequency === 'monthly' && report.day_of_month) {
            scheduleConfig.day_of_month = report.day_of_month
          }

          // Add day_of_week for weekly reports
          if (report.frequency === 'weekly' && report.day_of_week !== undefined) {
            scheduleConfig.day_of_week = report.day_of_week
          }

          const updateData = {
            report_type: report.report_type,
            frequency: report.frequency || 'monthly',
            time_of_day: report.time_of_day || '09:00',
            active: report.active !== false,
            schedule_config: Object.keys(scheduleConfig).length > 0 ? scheduleConfig : undefined
          }

          updatePromises.push(
            api.patch(`/reports/schedules/${report.schedule_id}`, updateData)
          )
        }
      }

      // Update delivery configuration if changed
      const deliveryChanged =
        deliveryMethod !== originalDeliveryMethod ||
        (deliveryMethod === 'email' && (destination !== originalDestination || ccEmails !== originalCcEmails)) ||
        (deliveryMethod === 'sharepoint' && sharepointPath !== originalSharepointPath);

      if (deliveryChanged) {
        const payload: { method: string; destination?: string; cc?: string[]; path?: string } = {
          method: deliveryMethod
        };

        if (deliveryMethod === 'email') {
          payload.destination = destination;
          payload.cc = ccEmails.split(',').map(e => e.trim()).filter(e => e);
        } else if (deliveryMethod === 'sharepoint') {
          payload.path = sharepointPath;
          // We might want to send 'sharepoint_configured': true explicitly or let backend handle
        }

        updatePromises.push(
          api.patch(`/customers/${id}/report-destination`, payload)
        )
      }

      // Update email destination if changed

      // Execute all updates in parallel
      await Promise.all(updatePromises)

      // Count what was actually updated
      const schedulesUpdated = modifiedSchedules.size
      const emailUpdated = (destination !== originalDestination || ccEmails !== originalCcEmails) ? 1 : 0

      // Clear modified schedules tracking
      setModifiedSchedules(new Set())
      setOriginalDestination(destination)
      setOriginalCcEmails(ccEmails)

      // Show appropriate success message
      if (schedulesUpdated > 0 && emailUpdated > 0) {
        toast.success('Saved', `Updated ${schedulesUpdated} schedule(s) and email settings successfully`)
      } else if (schedulesUpdated > 0) {
        toast.success('Saved', `Updated ${schedulesUpdated} schedule(s) successfully`)
      } else if (emailUpdated > 0) {
        toast.success('Saved', 'Email delivery settings updated successfully')
      } else {
        toast.success('Saved', 'No changes detected')
      }

      fetchReportConfig()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Save Failed', error.response?.data?.message || error.message)
    } finally {
      setIsSaving(false)
    }
  }

  const handleToggleScheduleSelection = (scheduleId: number) => {
    setSelectedSchedules(prev =>
      prev.includes(scheduleId)
        ? prev.filter(id => id !== scheduleId)
        : [...prev, scheduleId]
    )
  }

  const handleSelectAllSchedules = () => {
    const allScheduleIds = enabledReports
      .filter(r => r.schedule_id)
      .map(r => r.schedule_id!)
    setSelectedSchedules(allScheduleIds)
  }

  const handleDeselectAllSchedules = () => {
    setSelectedSchedules([])
  }

  const handleGenerateSelectedSchedules = async () => {
    // Validate
    if (selectedSchedules.length === 0) {
      setValidationErrors(['Please select at least one schedule to generate.'])
      setShowValidationModal(true)
      return
    }

    if (!destination.trim()) {
      setValidationErrors(['Email destination is required for report delivery.'])
      setShowValidationModal(true)
      return
    }

    // Check if any selected schedule is on-demand
    const selectedReports = enabledReports.filter(r => selectedSchedules.includes(r.schedule_id!))
    const hasOnDemand = selectedReports.some(r => r.frequency === 'on-demand')

    if (hasOnDemand) {
      // Show modal for date selection
      const end = new Date()
      const start = new Date()
      start.setDate(end.getDate() - 30)

      setCustomStartDate(start.toISOString().split('T')[0])
      setCustomEndDate(end.toISOString().split('T')[0])
      setShowGenerateModal(true)
    } else {
      // Generate immediately
      await performSelectedSchedulesGeneration()
    }
  }

  const performSelectedSchedulesGeneration = async (startDate?: string, endDate?: string) => {
    try {
      setIsGenerating(true)
      setShowGenerateModal(false)
      setGenerationProgress({ current: 0, total: selectedSchedules.length })
      setFailedSchedules([])

      const payload: any = (startDate && endDate) ? { start_date: startDate, end_date: endDate } : {}
      
      // Always use preview_only for on-demand generation from this page to allow review
      payload.preview_only = true

      // Generate reports sequentially to show progress
      const results: Array<{ status: 'fulfilled' | 'rejected'; value?: unknown; reason?: unknown; scheduleId: number }> = []

      for (let i = 0; i < selectedSchedules.length; i++) {
        const scheduleId = selectedSchedules[i]
        const enabledReport = enabledReports.find(r => r.schedule_id === scheduleId)
        
        const schedulePayload = { 
          ...payload,
          frequency: enabledReport?.frequency
        }

        try {
          const result = await api.post(`/reports/schedules/${scheduleId}/generate`, schedulePayload)
          results.push({ status: 'fulfilled', value: result, scheduleId })
        } catch (error) {
          results.push({ status: 'rejected', reason: error, scheduleId })
        }

        // Update progress after each request - use flushSync to force immediate render
        flushSync(() => {
          setGenerationProgress({ current: i + 1, total: selectedSchedules.length })
        })
      }

      // Separate succeeded and failed
      const succeeded = results.filter(r => r.status === 'fulfilled')
      const failed = results.filter(r => r.status === 'rejected')

      // Store failed schedules for retry
      if (failed.length > 0) {
        const failedItems = failed.map(f => ({
          scheduleId: f.scheduleId,
          error: (f.reason as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
            || (f.reason as { message?: string })?.message
            || 'Unknown error'
        }))
        setFailedSchedules(failedItems)
      }

      // Show results
      if (succeeded.length > 0) {
        const previewResults = succeeded.map(r => {
          const result = r.value as any
          const reportData = result.data || result
          return {
            id: reportData.report_id || reportData.generated_report_id,
            name: reportData.report_name || `${reportData.customer_name || config?.customer_name || 'Report'} - ${reportData.report_type?.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase()) || 'Report'}`,
            status: 'preview' as const
          }
        })

        if (previewResults.length === 1) {
          setPreviewReport(previewResults[0])
        } else {
          setBatchPreviewReports(previewResults)
        }
      }

      if (failed.length > 0) {
        // Create a detailed error message with actual reasons
        const errorDetails = failed.map((f, idx) => {
          const reportName = enabledReports.find(r => r.schedule_id === f.scheduleId)?.report_type || `Schedule ${f.scheduleId}`
          const errorMsg = (f.reason as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
            || (f.reason as { message?: string })?.message
            || 'Unknown error'
          return `${idx + 1}. ${reportName}: ${errorMsg}`
        }).join('\n')

        const title = failed.length === 1 ? 'Report Failed' : 'Some Reports Failed'
        toast.error(title, errorDetails)
      }

      // Clear selection only if all succeeded
      if (failed.length === 0) {
        setSelectedSchedules([])
        // Navigation moved to delivery confirmation
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Generation Failed', error.response?.data?.message || error.message)
    } finally {
      setIsGenerating(false)
      setGenerationProgress({ current: 0, total: 0 })
    }
  }

  const retryFailedReports = async () => {
    if (failedSchedules.length === 0) return

    const failedScheduleIds = failedSchedules.map(f => f.scheduleId)
    setSelectedSchedules(failedScheduleIds)
    await performSelectedSchedulesGeneration(customStartDate, customEndDate)
  }



  const confirmGenerate = () => {
    performSelectedSchedulesGeneration(customStartDate, customEndDate)
  }

  const handleDeliverReport = async () => {
    if (!previewReport) return
    try {
      await api.post(`/reports/${previewReport.id}/deliver`)
      toast.success('Report Delivered', 'Report has been queued for delivery')
      setPreviewReport(null)
      
      // If this was part of a batch, mark it as delivered in the batch list
      if (batchPreviewReports.length > 0) {
        setBatchPreviewReports(prev => 
          prev.map(r => r.id === previewReport.id ? { ...r, status: 'delivered' } : r)
        )
      } else {
        // Only navigate automatically if it was a single report workflow
        queryClient.invalidateQueries({ queryKey: ['reports'] })
        navigate('/platform/reports')
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Delivery Failed', error.response?.data?.message || error.message)
      throw err
    }
  }

  const handleDeliverAllReports = async () => {
    if (batchPreviewReports.length === 0) return
    try {
      const pendingReports = batchPreviewReports.filter(r => r.status === 'preview')
      
      // Deliver all pending reports in parallel
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

  if (isLoading) {
    return (
      <FloatingLayout>
        <ConfigureReportsLoading />
      </FloatingLayout>
    )
  }

  if (!config) {
    return (
      <FloatingLayout>
        <ConfigureReportsNotFound onBack={() => navigate(-1)} />
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="sm" onClick={() => navigate(-1)} className="hover:bg-muted">
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <div>
              <h1 className="text-3xl font-bold tracking-tight text-foreground">Configure Reports</h1>
              <p className="text-muted-foreground mt-1">
                Manage automated generation and delivery for <span className="font-medium text-foreground">{config.customer_name}</span>
              </p>
            </div>
          </div>
          {/* <div className="flex items-center gap-2">
             <Button
                variant="outline"
                size="sm"
                onClick={() => navigate('/platform/templates')}
                className="flex items-center gap-2"
              >
                <Plus className="h-4 w-4" />
                Templates
              </Button>
          </div> */}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Left Column: Schedules */}
          <div className="lg:col-span-2 space-y-6">
            <Card className="border border-border shadow-sm">
              <CardHeader className="pb-0">
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle className="text-xl font-semibold flex items-center gap-2">
                      <Bell className="h-5 w-5 text-primary" />
                      Report Schedules
                    </CardTitle>
                    <CardDescription>
                      Automated schedules inherited from templates
                    </CardDescription>
                  </div>
                  {enabledReports.length > 0 && (
                    <div className="flex items-center gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleSelectAllSchedules}
                        disabled={selectedSchedules.length === enabledReports.filter(r => r.schedule_id).length}
                        className="text-xs h-8"
                      >
                        Select All
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleDeselectAllSchedules}
                        disabled={selectedSchedules.length === 0}
                        className="text-xs h-8 text-destructive hover:text-destructive"
                      >
                        Clear
                      </Button>
                    </div>
                  )}
                </div>
              </CardHeader>

              <CardContent>
                {enabledReports.length === 0 ? (
                  <div className="text-center py-12 bg-background rounded-xl border-2 border-dashed">
                    <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-4 opacity-20" />
                    <p className="text-sm font-medium text-muted-foreground">No report schedules found</p>
                    <p className="text-xs text-muted-foreground mt-1 mb-6">
                      Templates define the base schedules for your customers
                    </p>
                    <Button
                      onClick={() => navigate('/platform/templates')}
                      size="sm"
                      className="gap-2"
                    >
                      <Plus className="h-4 w-4" />
                      Browse Templates
                    </Button>
                  </div>
                ) : (
                  <div className="grid gap-4 md:grid-cols-1 xl:grid-cols-2">
                    {enabledReports.map((report, index) => {
                      const reportType = availableReportTypes.find(t => t.id === report.report_type)

                      return (
                        <ScheduleCard
                          key={report.schedule_id || index}
                          report={report}
                          index={index}
                          isSelected={!!report.schedule_id && selectedSchedules.includes(report.schedule_id)}
                          reportType={reportType}
                          isSaving={isSaving}
                          onSelect={handleToggleScheduleSelection}
                          onUpdate={handleUpdateReport}
                          onReset={handleResetSchedule}
                          onNavigateToTemplates={() => navigate('/platform/templates')}
                        />
                      )
                    })}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Right Column: Settings & Actions */}
          <div className="space-y-6">
            {/* Delivery & Actions Card */}
            <Card className="shadow-md border border-border">
              <CardHeader className="pb-0">
                <CardTitle className="text-lg flex items-center gap-2">
                  <Mail className="h-5 w-5 text-primary" />
                  Delivery & Actions
                </CardTitle>
                <CardDescription>
                  Configure delivery settings and generate reports.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-4">
                {/* Delivery Settings */}
                <div className="space-y-3">
                  {/* Delivery Method Selector (Only if SharePoint is available or configured) */}
                  {/* Delivery Method Selector */}
                  <div className="space-y-1.5">
                    <Label className="text-xs font-bold text-muted-foreground uppercase">Delivery Method</Label>
                    <div className="flex flex-col gap-2">
                      <div className="grid grid-cols-2 gap-2">
                        <Button
                          variant={deliveryMethod === 'email' ? 'default' : 'outline'}
                          size="sm"
                          onClick={() => setDeliveryMethod('email')}
                          className="w-full"
                        >
                          <Mail className="h-4 w-4 mr-2" />
                          Email
                        </Button>
                        <Button
                          variant={deliveryMethod === 'sharepoint' ? 'default' : 'outline'}
                          size="sm"
                          onClick={() => setDeliveryMethod('sharepoint')}
                          className="w-full"
                          disabled={!sharepointIntegrationId}
                          title={!sharepointIntegrationId ? "Configure SharePoint integration in Settings to enable" : ""}
                        >
                          <Share2 className="h-4 w-4 mr-2" />
                          SharePoint
                        </Button>
                      </div>
                      {!sharepointIntegrationId && (
                        <p className="text-[10px] text-muted-foreground">
                          * <a href="/platform/integrations" className="underline hover:text-primary">Connect SharePoint</a> in settings to enable folder delivery.
                        </p>
                      )}
                    </div>
                  </div>

                  {deliveryMethod === 'email' ? (
                    <>
                      <div className="space-y-1.5">
                        <Label htmlFor="sidebar-destination" className="text-xs font-bold text-muted-foreground uppercase">Recipient</Label>
                        <Input
                          id="sidebar-destination"
                          type="email"
                          placeholder="reports@customer.com"
                          value={destination}
                          onChange={(e) => setDestination(e.target.value)}
                          className="h-9"
                        />
                      </div>
                      <div className="space-y-1.5">
                        <Label htmlFor="sidebar-cc" className="text-xs font-bold text-muted-foreground uppercase">CC Recipients</Label>
                        <Input
                          id="sidebar-cc"
                          type="text"
                          placeholder="manager@customer.com"
                          value={ccEmails}
                          onChange={(e) => setCcEmails(e.target.value)}
                          className="h-9"
                        />
                        <p className="text-[10px] text-muted-foreground italic">Comma-separated addresses</p>
                      </div>
                    </>
                  ) : (
                    <div className="space-y-4">
                      {sharepointIntegrations.length > 1 && (
                        <div className="space-y-1.5">
                          <Label className="text-xs font-bold text-muted-foreground uppercase">SharePoint Site</Label>
                          <Select
                            value={sharepointIntegrationId?.toString() || ''}
                            onValueChange={(val) => setSharepointIntegrationId(parseInt(val))}
                          >
                            <SelectTrigger className="w-full text-xs h-9">
                              <SelectValue placeholder="Select Integration" />
                            </SelectTrigger>
                            <SelectContent>
                              {sharepointIntegrations.map((int) => (
                                <SelectItem key={int.integration_id} value={int.integration_id.toString()}>
                                  {int.display_name || int.integration_type}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                      )}

                      <div className="space-y-1.5">
                        <Label className="text-xs font-bold text-muted-foreground uppercase">Target Folder</Label>
                        <div className="flex gap-2">
                          <Input
                            value={sharepointPath || '/'}
                            readOnly
                            className="h-9 bg-muted text-muted-foreground text-xs font-mono"
                          />
                          <Button
                            size="sm"
                            variant="outline"
                            className="px-3"
                            onClick={() => setIsFileBrowserOpen(true)}
                          >
                            <Folder className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                    </div>
                  )}

                </div>

                {/* Save Button */}
                <Button
                  onClick={handleSaveConfiguration}
                  disabled={isSaving || isGenerating}
                  className="w-full h-11"
                >
                  {isSaving ? (
                    <Spinner className="h-4 w-4 mr-2" />
                  ) : (
                    <Save className="h-4 w-4 mr-2" />
                  )}
                  Save Configuration
                </Button>

                {/* Divider with note */}
                <div className="relative py-2">
                  <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-border" />
                  </div>
                  <div className="relative flex justify-center">
                    <span className="bg-card px-2 text-[10px] text-muted-foreground uppercase tracking-wider">
                      Generate Reports
                    </span>
                  </div>
                </div>

                <p className="text-[11px] text-muted-foreground text-center">
                  Save configuration before generating reports
                </p>

                {/* Generate Button */}
                <Button
                  onClick={handleGenerateSelectedSchedules}
                  disabled={isGenerating || isSaving || selectedSchedules.length === 0}
                  variant="secondary"
                  className="w-full h-11 text-white"
                >
                  {isGenerating ? (
                    <Spinner className="h-4 w-4 mr-2" />
                  ) : (
                    <Eye className="h-4 w-4 mr-2" />
                  )}
                  Preview ({selectedSchedules.length})
                </Button>

                {isGenerating && generationProgress.total > 0 && (
                  <div className="space-y-2">
                    <div className="flex justify-between text-xs font-medium">
                      <span>Progress</span>
                      <span>{Math.round((generationProgress.current / generationProgress.total) * 100)}%</span>
                    </div>
                    <div className="w-full bg-muted rounded-full h-1.5">
                      <div
                        className="bg-primary h-1.5 rounded-full transition-all duration-300"
                        style={{ width: `${(generationProgress.current / generationProgress.total) * 100}%` }}
                      />
                    </div>
                  </div>
                )}

                {failedSchedules.length > 0 && (
                  <Button
                    onClick={retryFailedReports}
                    variant="outline"
                    disabled={isGenerating}
                    className="w-full h-9 border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                  >
                    {isGenerating ? (
                      <Spinner className="h-4 w-4 mr-2" />
                    ) : (
                      <RotateCw className="h-4 w-4 mr-2" />
                    )}
                    Retry Failed Reports
                  </Button>
                )}
              </CardContent>
            </Card>

            {/* System Info */}
            <Card className="bg-primary/5 border-primary/20">
              <CardContent className="p-4 space-y-3">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Timezone</span>
                  <span className="font-semibold">{config.timezone || 'UTC'}</span>
                </div>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Retention</span>
                  <span className="font-semibold">{config.retention_days || 1095} Days</span>
                </div>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Schedules</span>
                  <span className="font-semibold">{enabledReports.length} Active</span>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      {previewReport && (
        <ReportPreviewModal
          open={!!previewReport}
          onClose={() => setPreviewReport(null)}
          reportId={previewReport.id}
          reportName={previewReport.name}
          onConfirm={batchPreviewReports.length === 0 ? handleDeliverReport : undefined}
          confirmLabel="Generate & Deliver Report"
          onDiscard={batchPreviewReports.length === 0 ? async () => {
            try {
              await api.post('/reports/batch-discard', { report_ids: [previewReport.id] })
              setPreviewReport(null)
            } catch (err) {
              console.error('Discard failed', err)
              setPreviewReport(null)
            }
          } : undefined}
          discardLabel={batchPreviewReports.length === 0 ? "Discard Report" : "Back to Batch"}
        />
      )}

      {!previewReport && batchPreviewReports.length > 0 && (
        <BatchPreviewModal
          open={batchPreviewReports.length > 0}
          onClose={() => setBatchPreviewReports([])}
          reports={batchPreviewReports}
          onPreview={(report) => setPreviewReport(report)}
          onDeliverAll={handleDeliverAllReports}
          onDiscard={handleDiscardReports}
        />
      )}

      {/* Validation Error Modal */}
      <ConfirmationModal
        isOpen={showValidationModal}
        onClose={() => setShowValidationModal(false)}
        onConfirm={() => setShowValidationModal(false)}
        title="Missing Required Information"
        description={validationErrors.length > 0
          ? validationErrors.map((err, i) => `${i + 1}. ${err}`).join('\n\n')
          : 'Please fill in all required fields before saving.'
        }
        confirmText="OK, I'll Fix It"
        cancelText=""
        type="warning"
      />

      {/* Date Selection Modal */}
      <Dialog open={showGenerateModal} onOpenChange={setShowGenerateModal}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Prepare Preview</DialogTitle>
            <DialogDescription>
              Select the date range for your immediate report.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-4 items-center gap-4">
              <Label htmlFor="start-date" className="text-right">
                Start Date
              </Label>
              <Input
                id="start-date"
                type="date"
                value={customStartDate}
                onChange={(e) => setCustomStartDate(e.target.value)}
                className="col-span-3"
              />
            </div>
            <div className="grid grid-cols-4 items-center gap-4">
              <Label htmlFor="end-date" className="text-right">
                End Date
              </Label>
              <Input
                id="end-date"
                type="date"
                value={customEndDate}
                onChange={(e) => setCustomEndDate(e.target.value)}
                className="col-span-3"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowGenerateModal(false)}>
              Cancel
            </Button>
            <Button onClick={confirmGenerate} disabled={isGenerating}>
              {isGenerating ? (
                <>
                  <Spinner className="h-4 w-4 mr-2" />
                  Preparing...
                </>
              ) : (
                <>
                  <Eye className="h-4 w-4 mr-2" />
                  Preview
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <FileBrowserModal
        open={isFileBrowserOpen}
        onOpenChange={setIsFileBrowserOpen}
        integrationId={sharepointIntegrationId}
        onSelect={(path) => setSharepointPath(path)}
        initialPath={sharepointPath}
      />

    </FloatingLayout>
  )
}
