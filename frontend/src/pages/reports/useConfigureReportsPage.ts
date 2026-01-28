import { useEffect, useState, useCallback } from 'react'
import { flushSync } from 'react-dom'
import { logger } from '@/lib/logger'
import { useParams, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useToast } from '@/components/ui/Toast'
import { api } from '@/lib/api'
import type { ReportConfig, ReportType, EnabledReport } from './types'

interface SharepointIntegration {
    integration_id: number
    integration_type: string
    display_name?: string
}

interface FailedSchedule {
    scheduleId: number
    error: string
}

interface PreviewReport {
    id: number
    name: string
    status: 'preview' | 'delivered'
}

export const useConfigureReportsPage = () => {
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const toast = useToast()
    const queryClient = useQueryClient()

    const [isLoading, setIsLoading] = useState(true)
    const [isSaving, setIsSaving] = useState(false)
    const [isGenerating, setIsGenerating] = useState(false)
    const [config, setConfig] = useState<ReportConfig | null>(null)
    const [enabledReports, setEnabledReports] = useState<EnabledReport[]>([])
    const [availableReportTypes, setAvailableReportTypes] = useState<ReportType[]>([])

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
    const [sharepointIntegrations, setSharepointIntegrations] = useState<SharepointIntegration[]>([])

    const [selectedSchedules, setSelectedSchedules] = useState<number[]>([])
    const [modifiedSchedules, setModifiedSchedules] = useState<Set<number>>(new Set())

    const [showValidationModal, setShowValidationModal] = useState(false)
    const [validationErrors, setValidationErrors] = useState<string[]>([])

    const [showGenerateModal, setShowGenerateModal] = useState(false)
    const [customStartDate, setCustomStartDate] = useState('')
    const [customEndDate, setCustomEndDate] = useState('')
    const [generationProgress, setGenerationProgress] = useState({ current: 0, total: 0 })
    const [failedSchedules, setFailedSchedules] = useState<FailedSchedule[]>([])

    const [previewReport, setPreviewReport] = useState<PreviewReport | null>(null)
    const [batchPreviewReports, setBatchPreviewReports] = useState<PreviewReport[]>([])

    useEffect(() => {
        // Automatically sync enabled reports with the latest available report types for the customer.
        // If an enabled report's type is no longer available, default it to the first available type.
        if (availableReportTypes.length > 0 && enabledReports.length > 0) {
            const validReportIds = availableReportTypes.map(r => r.id)
            const needsUpdate = enabledReports.some(report => !validReportIds.includes(report.report_type))

            if (needsUpdate) {
                const updatedReports = enabledReports.map(report => {
                    if (!validReportIds.includes(report.report_type)) {
                        return { ...report, report_type: availableReportTypes[0].id }
                    }
                    return report
                })
                setEnabledReports(updatedReports)
            }
        }
    }, [availableReportTypes, enabledReports])

    const fetchAvailableReports = useCallback(async () => {
        try {
            const response = await api.get<{ data?: { available_reports: ReportType[] }; available_reports?: ReportType[] }>(`/customers/${id}/available-reports`)
            const reportData = response.data || response

            const reports = (reportData.available_reports || []).map((r: ReportType) => ({
                id: r.id,
                name: r.name,
                description: r.description,
                integration_source: r.integration_source,
                frequency_options: r.frequency_options || ['on-demand'],
                has_data: r.has_data
            }))

            setAvailableReportTypes(reports)
        } catch (error) {
            logger.error('Failed to fetch available reports:', error)
            toast.error('Failed to load available reports')
        }
    }, [id, toast])

    const fetchReportConfig = useCallback(async () => {
        try {
            setIsLoading(true)
            const response = await api.get<{ data: ReportConfig }>(`/customers/${id}/reports`)
            const reportData = response.data

            setConfig(reportData)
            const schedules = reportData.report_schedules || []

            const mappedSchedules = schedules.map((s) => ({
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
            }))

            const schedulesWithDataCheck = await Promise.all(
                mappedSchedules.map(async (schedule) => {
                    try {
                        const checkResponse = await api.get<{ data?: { available_reports: ReportType[] }; available_reports?: ReportType[] }>(
                            `/customers/${id}/available-reports?frequency=${schedule.frequency}`
                        )
                        const reportCheckData = checkResponse.data || checkResponse

                        const reportType = reportCheckData.available_reports?.find(r => r.id === schedule.report_type)
                        return { ...schedule, has_data: reportType?.has_data ?? true }
                    } catch {
                        return { ...schedule, has_data: true }
                    }
                })
            )

            setEnabledReports(schedulesWithDataCheck as EnabledReport[])

            let emailDestination = ''
            let ccEmailsList: string[] = []

            if (reportData.email_destination) {
                emailDestination = reportData.email_destination
                ccEmailsList = reportData.cc_emails || []
            } else if (schedules.length > 0 && schedules[0].email_destination) {
                emailDestination = schedules[0].email_destination
                ccEmailsList = schedules[0].cc_emails || []
            } else if (reportData.delivery?.destination) {
                emailDestination = reportData.delivery.destination
                ccEmailsList = reportData.delivery.cc || []
            }

            setDestination(emailDestination)
            setCcEmails(Array.isArray(ccEmailsList) ? ccEmailsList.join(', ') : '')
            setOriginalDestination(emailDestination)
            setOriginalCcEmails(Array.isArray(ccEmailsList) ? ccEmailsList.join(', ') : '')

            const currentMethod = reportData.delivery?.method || 'email'
            setDeliveryMethod(currentMethod)
            setOriginalDeliveryMethod(currentMethod)
            setSharepointPath(reportData.delivery?.path || '')
            setOriginalSharepointPath(reportData.delivery?.path || '')

            try {
                const intResponse = await api.get<{ configured?: SharepointIntegration[] }>('/integrations')
                const integrations = intResponse.configured || []
                const spIntegrations = Array.isArray(integrations)
                    ? integrations.filter((i) => i.integration_type?.toLowerCase().includes('sharepoint'))
                    : []

                setSharepointIntegrations(spIntegrations)

                if (reportData.delivery?.integration_id) {
                    setSharepointIntegrationId(reportData.delivery.integration_id)
                } else if (spIntegrations.length === 1) {
                    setSharepointIntegrationId(spIntegrations[0].integration_id)
                } else if (spIntegrations.length > 0) {
                    setSharepointIntegrationId(spIntegrations[0].integration_id)
                }
            } catch (e) {
                logger.warn('Failed to fetch integrations', { error: e })
            }
        } catch (err: unknown) {
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

    const handleUpdateReport = useCallback((index: number, field: keyof EnabledReport, value: string | number | boolean | string[]) => {
        setEnabledReports(prev => {
            const updated = [...prev]
            const report = updated[index]

            if (report.schedule_id) {
                setModifiedSchedules(prevMod => new Set(prevMod).add(report.schedule_id!))
            }

            if (field === 'frequency') {
                if (value === 'monthly' && !report.day_of_month) {
                    updated[index] = { ...report, frequency: value as string, day_of_month: 1 } as EnabledReport
                } else if (value === 'weekly' && report.day_of_week === undefined) {
                    updated[index] = { ...report, frequency: value as string, day_of_week: 0 } as EnabledReport
                } else {
                    updated[index] = { ...report, [field]: value } as EnabledReport
                }
            } else {
                updated[index] = { ...report, [field]: value } as EnabledReport
            }

            return updated
        })
    }, [])

    const handleResetSchedule = useCallback(async (scheduleId: number) => {
        try {
            setIsSaving(true)
            await api.post(`/reports/schedules/${scheduleId}/reset`)
            toast.success('Schedule reset to template')
            await fetchReportConfig()
        } catch (error) {
            logger.error('Failed to reset schedule:', error)
            toast.error('Failed to reset schedule')
        } finally {
            setIsSaving(false)
        }
    }, [fetchReportConfig, toast])

    const validateConfiguration = useCallback((): string[] => {
        const errors: string[] = []

        if (enabledReports.length === 0) {
            errors.push('No reports configured. Please add at least one report schedule.')
        }

        if (enabledReports.length > 0) {
            if (deliveryMethod === 'email') {
                if (!destination.trim()) {
                    errors.push('Email destination is required for report delivery.')
                } else {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
                    if (!emailRegex.test(destination.trim())) {
                        errors.push('Please enter a valid email address for the destination.')
                    }
                }

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
    }, [enabledReports, deliveryMethod, destination, ccEmails, sharepointPath])

    const handleSaveConfiguration = useCallback(async () => {
        const errors = validateConfiguration()
        if (errors.length > 0) {
            setValidationErrors(errors)
            setShowValidationModal(true)
            return
        }

        try {
            setIsSaving(true)
            const updatePromises = []

            for (const report of enabledReports) {
                if (report.schedule_id && modifiedSchedules.has(report.schedule_id)) {
                    const scheduleConfig: Record<string, unknown> = {}

                    if (report.frequency === 'monthly' && report.day_of_month) {
                        scheduleConfig.day_of_month = report.day_of_month
                    }

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

                    updatePromises.push(api.patch(`/reports/schedules/${report.schedule_id}`, updateData))
                }
            }

            const deliveryChanged =
                deliveryMethod !== originalDeliveryMethod ||
                (deliveryMethod === 'email' && (destination !== originalDestination || ccEmails !== originalCcEmails)) ||
                (deliveryMethod === 'sharepoint' && sharepointPath !== originalSharepointPath)

            if (deliveryChanged) {
                const payload: { method: string; destination?: string; cc?: string[]; path?: string } = { method: deliveryMethod }

                if (deliveryMethod === 'email') {
                    payload.destination = destination
                    payload.cc = ccEmails.split(',').map(e => e.trim()).filter(e => e)
                } else if (deliveryMethod === 'sharepoint') {
                    payload.path = sharepointPath
                }

                updatePromises.push(api.patch(`/customers/${id}/report-destination`, payload))
            }

            await Promise.all(updatePromises)

            const schedulesUpdated = modifiedSchedules.size
            const emailUpdated = (destination !== originalDestination || ccEmails !== originalCcEmails) ? 1 : 0

            setModifiedSchedules(new Set())
            setOriginalDestination(destination)
            setOriginalCcEmails(ccEmails)

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
    }, [validateConfiguration, enabledReports, modifiedSchedules, deliveryMethod, originalDeliveryMethod, destination, originalDestination, ccEmails, originalCcEmails, sharepointPath, originalSharepointPath, id, toast, fetchReportConfig])

    const handleToggleScheduleSelection = useCallback((scheduleId: number) => {
        setSelectedSchedules(prev =>
            prev.includes(scheduleId)
                ? prev.filter(sid => sid !== scheduleId)
                : [...prev, scheduleId]
        )
    }, [])

    const handleSelectAllSchedules = useCallback(() => {
        const allScheduleIds = enabledReports.filter(r => r.schedule_id).map(r => r.schedule_id!)
        setSelectedSchedules(allScheduleIds)
    }, [enabledReports])

    const handleDeselectAllSchedules = useCallback(() => setSelectedSchedules([]), [])

    const performSelectedSchedulesGeneration = useCallback(async (startDate?: string, endDate?: string) => {
        try {
            setIsGenerating(true)
            setShowGenerateModal(false)
            setGenerationProgress({ current: 0, total: selectedSchedules.length })
            setFailedSchedules([])

            const payload: Record<string, unknown> = (startDate && endDate) ? { start_date: startDate, end_date: endDate } : {}
            payload.preview_only = true

            // Generate reports sequentially to prevent server-side resource exhaustion
            const results: Array<{ status: 'fulfilled' | 'rejected'; value?: unknown; reason?: unknown; scheduleId: number }> = []

            for (let i = 0; i < selectedSchedules.length; i++) {
                const scheduleId = selectedSchedules[i]
                const enabledReport = enabledReports.find(r => r.schedule_id === scheduleId)

                const schedulePayload = { ...payload, frequency: enabledReport?.frequency }

                try {
                    const result = await api.post(`/reports/schedules/${scheduleId}/generate`, schedulePayload)
                    results.push({ status: 'fulfilled', value: result, scheduleId })
                } catch (error) {
                    results.push({ status: 'rejected', reason: error, scheduleId })
                }

                flushSync(() => {
                    setGenerationProgress({ current: i + 1, total: selectedSchedules.length })
                })
            }

            const succeeded = results.filter(r => r.status === 'fulfilled')
            const failed = results.filter(r => r.status === 'rejected')

            if (failed.length > 0) {
                const failedItems = failed.map(f => ({
                    scheduleId: f.scheduleId,
                    error: (f.reason as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
                        || (f.reason as { message?: string })?.message
                        || 'Unknown error'
                }))
                setFailedSchedules(failedItems)
            }

            if (succeeded.length > 0) {
                const previewResults = succeeded.map(r => {
                    const result = r.value as { data?: { report_id?: number; generated_report_id?: number; report_name?: string; customer_name?: string; report_type?: string } }
                    const reportData = result.data || result
                    return {
                        id: (reportData as { report_id?: number; generated_report_id?: number }).report_id || (reportData as { generated_report_id?: number }).generated_report_id || 0,
                        name: (reportData as { report_name?: string }).report_name || `${(reportData as { customer_name?: string }).customer_name || config?.customer_name || 'Report'} - ${((reportData as { report_type?: string }).report_type || 'Report').replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase())}`,
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
                const errorDetails = failed.map((f, idx) => {
                    const reportName = enabledReports.find(r => r.schedule_id === f.scheduleId)?.report_type || `Schedule ${f.scheduleId}`
                    const errorMsg = (f.reason as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
                        || (f.reason as { message?: string })?.message
                        || 'Unknown error'
                    return `${idx + 1}. ${reportName}: ${errorMsg}`
                }).join('\n')

                toast.error(failed.length === 1 ? 'Report Failed' : 'Some Reports Failed', errorDetails)
            }

            if (failed.length === 0) {
                setSelectedSchedules([])
            }
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string }
            toast.error('Generation Failed', error.response?.data?.message || error.message)
        } finally {
            setIsGenerating(false)
            setGenerationProgress({ current: 0, total: 0 })
        }
    }, [selectedSchedules, enabledReports, config?.customer_name, toast])

    const handleGenerateSelectedSchedules = useCallback(async () => {
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

        const selectedReports = enabledReports.filter(r => selectedSchedules.includes(r.schedule_id!))
        const hasOnDemand = selectedReports.some(r => r.frequency === 'on-demand')

        if (hasOnDemand) {
            const end = new Date()
            const start = new Date()
            start.setDate(end.getDate() - 30)

            setCustomStartDate(start.toISOString().split('T')[0])
            setCustomEndDate(end.toISOString().split('T')[0])
            setShowGenerateModal(true)
        } else {
            await performSelectedSchedulesGeneration()
        }
    }, [selectedSchedules, destination, enabledReports, performSelectedSchedulesGeneration])

    const retryFailedReports = useCallback(async () => {
        if (failedSchedules.length === 0) return
        const failedScheduleIds = failedSchedules.map(f => f.scheduleId)
        setSelectedSchedules(failedScheduleIds)
        await performSelectedSchedulesGeneration(customStartDate, customEndDate)
    }, [failedSchedules, customStartDate, customEndDate, performSelectedSchedulesGeneration])

    const confirmGenerate = useCallback(() => {
        performSelectedSchedulesGeneration(customStartDate, customEndDate)
    }, [customStartDate, customEndDate, performSelectedSchedulesGeneration])

    const handleDeliverReport = useCallback(async () => {
        if (!previewReport) return
        try {
            await api.post(`/reports/${previewReport.id}/deliver`)
            toast.success('Report Delivered', 'Report has been queued for delivery')
            setPreviewReport(null)

            if (batchPreviewReports.length > 0) {
                setBatchPreviewReports(prev => prev.map(r => r.id === previewReport.id ? { ...r, status: 'delivered' } : r))
            } else {
                queryClient.invalidateQueries({ queryKey: ['reports'] })
                navigate('/platform/reports')
            }
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string }
            toast.error('Delivery Failed', error.response?.data?.message || error.message)
            throw err
        }
    }, [previewReport, batchPreviewReports.length, queryClient, navigate, toast])

    const handleDeliverAllReports = useCallback(async () => {
        if (batchPreviewReports.length === 0) return
        try {
            const pendingReports = batchPreviewReports.filter(r => r.status === 'preview')
            await Promise.all(pendingReports.map(report => api.post(`/reports/${report.id}/deliver`)))
            toast.success('Batch Delivery Initiated', `${pendingReports.length} reports have been queued for delivery`)
            setBatchPreviewReports([])
            queryClient.invalidateQueries({ queryKey: ['reports'] })
            navigate('/platform/reports')
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string }
            toast.error('Batch Delivery Failed', error.response?.data?.message || error.message)
            throw err
        }
    }, [batchPreviewReports, queryClient, navigate, toast])

    const handleDiscardReports = useCallback(async () => {
        if (batchPreviewReports.length === 0) return
        try {
            const reportIds = batchPreviewReports.filter(r => r.status === 'preview').map(r => r.id)
            if (reportIds.length > 0) {
                await api.post('/reports/batch-discard', { report_ids: reportIds })
            }
            setBatchPreviewReports([])
        } catch (err: unknown) {
            logger.error('Failed to discard reports:', err)
            setBatchPreviewReports([])
        }
    }, [batchPreviewReports])

    return {
        id,
        navigate,
        isLoading,
        isSaving,
        isGenerating,
        config,
        enabledReports,
        availableReportTypes,
        destination,
        setDestination,
        ccEmails,
        setCcEmails,
        deliveryMethod,
        setDeliveryMethod,
        sharepointPath,
        setSharepointPath,
        isFileBrowserOpen,
        setIsFileBrowserOpen,
        sharepointIntegrationId,
        setSharepointIntegrationId,
        sharepointIntegrations,
        selectedSchedules,
        modifiedSchedules,
        showValidationModal,
        setShowValidationModal,
        validationErrors,
        showGenerateModal,
        setShowGenerateModal,
        customStartDate,
        setCustomStartDate,
        customEndDate,
        setCustomEndDate,
        generationProgress,
        failedSchedules,
        previewReport,
        setPreviewReport,
        batchPreviewReports,
        setBatchPreviewReports,
        handleUpdateReport,
        handleResetSchedule,
        handleSaveConfiguration,
        handleToggleScheduleSelection,
        handleSelectAllSchedules,
        handleDeselectAllSchedules,
        handleGenerateSelectedSchedules,
        performSelectedSchedulesGeneration,
        retryFailedReports,
        confirmGenerate,
        handleDeliverReport,
        handleDeliverAllReports,
        handleDiscardReports,
        fetchReportConfig,
    }
}
