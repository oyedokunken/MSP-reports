import { useEffect, useState, useCallback } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ArrowLeft, Calendar, Clock, Mail, Save, Bell, Play, Zap, Filter, ExternalLink, Plus } from 'lucide-react'
import { api } from '@/lib/api'
import { Spinner } from '@/components/ui/spinner'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { ConfigureReportsLoading, ConfigureReportsNotFound } from './components/ConfigureReportsStates'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"

import type { ReportConfig, ReportType, EnabledReport } from './types'


const FREQUENCIES = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
  { value: 'on-demand', label: 'On-Demand Only' },
]

export default function ConfigureReports() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()

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
  const [availableReportTypes, setAvailableReportTypes] = useState<ReportType[]>([])
  const [showValidationModal, setShowValidationModal] = useState(false)
  const [validationErrors, setValidationErrors] = useState<string[]>([])
  const [showGenerateModal, setShowGenerateModal] = useState(false)
  const [customStartDate, setCustomStartDate] = useState('')
  const [customEndDate, setCustomEndDate] = useState('')



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

      console.log('Available reports for customer:', reportData)

      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const reports = (reportData.available_reports || []).map((r: any) => ({
        id: r.report_type || r.id,
        name: r.name,
        description: r.description,
        integration_source: r.integration_source,
        frequency_options: r.frequency_options || ['on-demand']
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

      console.log('Fetched report config:', reportData)

      setConfig(reportData)
      const schedules = reportData.report_schedules || []
      console.log('Schedules from backend:', schedules)

      const mappedSchedules = schedules.map((s) => {
        console.log('Processing schedule:', {
          id: s.report_schedule_id,
          schedule_config: s.schedule_config,
          included_sections_from_config: s.schedule_config?.included_sections,
          include_sections_direct: s.include_sections
        });
        
        return {
          report_type: s.report_type,
          schedule_id: s.report_schedule_id,
          frequency: s.frequency,
          time_of_day: s.time_of_day,
          active: s.active,
          day_of_month: s.day_of_month,
          day_of_week: s.day_of_week,
          include_sections: s.schedule_config?.included_sections || s.include_sections,
          _old_schedule_id: undefined
        };
      })

      console.log('Mapped schedules:', mappedSchedules)
      setEnabledReports(mappedSchedules)

      let emailDestination = ''
      let ccEmailsList: string[] = []

      console.log('Full reportData:', reportData)

      if (reportData.email_destination) {
        console.log('Using email from root level:', reportData.email_destination)
        emailDestination = reportData.email_destination
        ccEmailsList = reportData.cc_emails || []
      }
      else if (schedules.length > 0 && schedules[0].email_destination) {
        console.log('Using email from schedule:', schedules[0].email_destination)
        emailDestination = schedules[0].email_destination
        ccEmailsList = schedules[0].cc_emails || []
      }
      else if (reportData.delivery?.destination) {
        console.log('Using email from delivery config')
        emailDestination = reportData.delivery.destination
        ccEmailsList = reportData.delivery.cc || []
      }

      console.log('Final email destination:', emailDestination)
      console.log('Final CC emails:', ccEmailsList)

      setDestination(emailDestination)
      setCcEmails(Array.isArray(ccEmailsList) ? ccEmailsList.join(', ') : '')
      setOriginalDestination(emailDestination)
      setOriginalCcEmails(Array.isArray(ccEmailsList) ? ccEmailsList.join(', ') : '')
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

    updated[index] = { ...updated[index], [field]: value } as EnabledReport
    setEnabledReports(updated)
  }

  // Validate configuration before saving
  const validateConfiguration = (): string[] => {
    const errors: string[] = []

    if (enabledReports.length === 0) {
      errors.push('No reports configured. Please add at least one report schedule.')
    }

    // Check if email destination is provided when reports are configured
    if (enabledReports.length > 0 && !destination.trim()) {
      errors.push('Email destination is required for report delivery.')
    }

    // Validate email format if provided
    if (destination.trim()) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      if (!emailRegex.test(destination.trim())) {
        errors.push('Please enter a valid email address for the destination.')
      }
    }

    // Validate CC emails if provided
    if (ccEmails.trim()) {
      const ccArray = ccEmails.split(',').map(e => e.trim()).filter(e => e)
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      const invalidCCs = ccArray.filter(email => !emailRegex.test(email))
      if (invalidCCs.length > 0) {
        errors.push(`Invalid CC email(s): ${invalidCCs.join(', ')}`)
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

      // Update email destination if changed
      if (destination !== originalDestination || ccEmails !== originalCcEmails) {
        const ccArray = ccEmails
          .split(',')
          .map(email => email.trim())
          .filter(email => email.length > 0)

        updatePromises.push(
          api.patch(`/customers/${id}/report-destination`, {
            method: 'email',
            destination,
            cc: ccArray
          })
        )
      }

      // Execute all updates in parallel
      await Promise.all(updatePromises)

      // Clear modified schedules tracking
      setModifiedSchedules(new Set())
      setOriginalDestination(destination)
      setOriginalCcEmails(ccEmails)

      toast.success('Saved', `Updated ${modifiedSchedules.size} schedule(s) successfully`)
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

      const payload = (startDate && endDate) ? { start_date: startDate, end_date: endDate } : {}
      
      // Generate reports for all selected schedules
      const promises = selectedSchedules.map(scheduleId => 
        api.post(`/reports/schedules/${scheduleId}/generate`, payload)
      )

      await Promise.all(promises)
      
      toast.success('Reports Generated!', `Successfully generated ${selectedSchedules.length} report(s)`)
      
      // Clear selection
      setSelectedSchedules([])

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

  const confirmGenerate = () => {
    performSelectedSchedulesGeneration(customStartDate, customEndDate)
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
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={() => navigate(-1)}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold text-foreground">Configure Automatic Reports</h1>
            <p className="mt-2 text-muted-foreground">
              Set up automated report generation and delivery schedules
            </p>
          </div>
        </div>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <Bell className="h-5 w-5" />
                  Automatic Report Schedules
                </CardTitle>
                <CardDescription>
                  Report schedules created from templates. Use the Templates page to create new schedules.
                </CardDescription>
              </div>
              {/* <Button
                variant="outline"
                size="sm"
                onClick={() => navigate('/platform/templates')}
                className="flex items-center gap-2"
              >
                <Plus className="h-4 w-4" />
                Create Template
              </Button> */}
            </div>
          </CardHeader>
          <CardContent>
            {enabledReports.length === 0 ? (
              <div className="text-center py-8">
                <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <p className="text-sm text-muted-foreground mb-2">No report schedules configured</p>
                <p className="text-xs text-muted-foreground mb-4">
                  Go to the Templates page to create report schedules for this customer
                </p>
                <div className="flex justify-center">
                  <Button
                    onClick={() => navigate('/platform/templates')}
                    className="flex items-center gap-2"
                  >
                    <Plus className="h-4 w-4" />
                    Create Template
                  </Button>
                </div>
              </div>
            ) : (
              <div className="grid gap-4 md:grid-cols-2">
                {enabledReports.map((report, index) => {
                  const hasFilteredSections = report.include_sections && report.include_sections.length > 0
                  
                  return (
                  <Card 
                    key={index} 
                    className={`relative ${
                      hasFilteredSections 
                        ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20' 
                        : ''
                    }`}
                  >
                    <CardHeader className="pb-3">
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex-1">
                          <CardTitle className="text-base flex items-center gap-2">
                            {availableReportTypes.find(t => t.id === report.report_type)?.name || 'Report'}
                            <Badge variant={report.active !== false ? "default" : "secondary"} className="text-xs">
                              {report.active !== false ? 'Active' : 'Inactive'}
                            </Badge>
                          </CardTitle>
                          {report.schedule_id && (
                            <CardDescription className="text-xs mt-1">
                              Schedule ID: {report.schedule_id}
                            </CardDescription>
                          )}
                        </div>
                        <div className="flex items-center gap-1">
                          {report.schedule_id && (
                            <label 
                              htmlFor={`select-schedule-${index}`}
                              className="flex items-center justify-center h-8 w-8 cursor-pointer hover:bg-muted rounded-md"
                            >
                              <input
                                type="checkbox"
                                id={`select-schedule-${index}`}
                                checked={selectedSchedules.includes(report.schedule_id)}
                                onChange={() => handleToggleScheduleSelection(report.schedule_id!)}
                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                              />
                            </label>
                          )}
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => navigate('/platform/templates')}
                            className="text-primary hover:text-primary h-8 w-8 p-0"
                            title="Go to Templates"
                          >
                            <ExternalLink className="h-4 w-4" />
                          </Button>
                        </div>
                      </div>
                      {hasFilteredSections && (
                        <Badge variant="outline" className="bg-blue-100 text-blue-700 border-blue-300 dark:bg-blue-900 dark:text-blue-300 w-fit mt-2">
                          <Filter className="h-3 w-3 mr-1" />
                          {report.include_sections?.length} Filtered Sections
                        </Badge>
                      )}
                    </CardHeader>
                    <CardContent className="space-y-3">
                      <div className="grid gap-3">
                        <div>
                          <Label htmlFor={`report-type-${index}`} className="text-xs">Report Type</Label>
                          <Select
                            value={report.report_type || ''}
                            onValueChange={(value) => handleUpdateReport(index, 'report_type', value)}
                          >
                            <SelectTrigger id={`report-type-${index}`} className="mt-1 h-9">
                              <SelectValue placeholder="Select report type" />
                            </SelectTrigger>
                            <SelectContent>
                              {availableReportTypes.length === 0 ? (
                                <div className="p-2 text-sm text-muted-foreground">
                                  No reports available. Please link an integration first.
                                </div>
                              ) : (
                                availableReportTypes.map((type) => (
                                  <SelectItem key={type.id} value={type.id}>
                                    <div className="flex items-center justify-between w-full">
                                      <span>{type.name}</span>
                                      {type.integration_source && (
                                        <Badge variant="outline" className="ml-2 text-xs">
                                          {type.integration_source}
                                        </Badge>
                                      )}
                                    </div>
                                  </SelectItem>
                                ))
                              )}
                            </SelectContent>
                          </Select>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                          <div>
                            <Label htmlFor={`frequency-${index}`} className="text-xs">Frequency</Label>
                            <Select
                              value={report.frequency || 'monthly'}
                              onValueChange={(value) => handleUpdateReport(index, 'frequency', value)}
                            >
                              <SelectTrigger id={`frequency-${index}`} className="mt-1 h-9">
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                {(() => {
                                  const selectedReport = availableReportTypes.find(t => t.id === report.report_type)
                                  const allowedFrequencies = selectedReport?.frequency_options || ['on-demand']
                                  return FREQUENCIES
                                    .filter(freq => allowedFrequencies.includes(freq.value))
                                    .map((freq) => (
                                      <SelectItem key={freq.value} value={freq.value}>
                                        {freq.label}
                                      </SelectItem>
                                    ))
                                })()}
                              </SelectContent>
                            </Select>
                          </div>

                          {report.frequency !== 'on-demand' && (
                            <div>
                              <Label htmlFor={`time-${index}`} className="text-xs">Time</Label>
                              <Input
                                id={`time-${index}`}
                                type="time"
                                value={report.time_of_day || '09:00'}
                                onChange={(e) => handleUpdateReport(index, 'time_of_day', e.target.value)}
                                className="mt-1 h-9"
                              />
                            </div>
                          )}
                        </div>

                        {report.frequency === 'monthly' && (
                          <div>
                            <Label htmlFor={`day-${index}`} className="text-xs">Day of Month</Label>
                            <Select
                              value={String(report.day_of_month || '1')}
                              onValueChange={(value) => handleUpdateReport(index, 'day_of_month', parseInt(value))}
                            >
                              <SelectTrigger id={`day-${index}`} className="mt-1 h-9">
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                {Array.from({ length: 31 }, (_, i) => i + 1).map((day) => (
                                  <SelectItem key={day} value={String(day)}>
                                    {day}{day === 1 ? 'st' : day === 2 ? 'nd' : day === 3 ? 'rd' : 'th'}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </div>
                        )}

                        {report.frequency === 'weekly' && (
                          <div>
                            <Label htmlFor={`weekday-${index}`} className="text-xs">Day of Week</Label>
                            <Select
                              value={String(report.day_of_week || '1')}
                              onValueChange={(value) => handleUpdateReport(index, 'day_of_week', parseInt(value))}
                            >
                              <SelectTrigger id={`weekday-${index}`} className="mt-1 h-9">
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="1">Monday</SelectItem>
                                <SelectItem value="2">Tuesday</SelectItem>
                                <SelectItem value="3">Wednesday</SelectItem>
                                <SelectItem value="4">Thursday</SelectItem>
                                <SelectItem value="5">Friday</SelectItem>
                                <SelectItem value="6">Saturday</SelectItem>
                                <SelectItem value="0">Sunday</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                        )}

                        <div className="flex items-center gap-2 pt-1">
                          <Switch
                            id={`active-${index}`}
                            checked={report.active !== false}
                            onCheckedChange={(checked: boolean) => handleUpdateReport(index, 'active', checked)}
                          />
                          <Label htmlFor={`active-${index}`} className="cursor-pointer text-xs">
                            Schedule {report.active !== false ? 'Active' : 'Inactive'}
                          </Label>
                        </div>
                      </div>

                      {availableReportTypes.find(t => t.id === report.report_type)?.description && (
                        <p className="text-xs text-muted-foreground pt-2 border-t">
                          {availableReportTypes.find(t => t.id === report.report_type)?.description}
                        </p>
                      )}

                      {report.report_type === 'monthly_comprehensive' && (
                        <div className="p-2 bg-muted/50 rounded-md border-t">
                          <Label className="text-xs font-medium mb-1 block">📋 Included Reports:</Label>
                          <p className="text-xs text-muted-foreground mb-2">
                            Auto-includes all monthly summaries from active integrations
                          </p>
                          <div className="space-y-1">
                            {config && (
                              <div className="grid grid-cols-1 gap-1">
                                {availableReportTypes
                                  .filter(rt => rt.id.includes('monthly_summary'))
                                  .map(rt => (
                                    <div key={rt.id} className="flex items-center gap-1.5 text-xs">
                                      <span className="text-green-500">✓</span>
                                      <span className="flex-1">{rt.name}</span>
                                      {rt.integration_source && (
                                        <Badge variant="outline" className="text-xs h-5">
                                          {rt.integration_source}
                                        </Badge>
                                      )}
                                    </div>
                                  ))}
                                {availableReportTypes.filter(rt => rt.id.includes('monthly_summary')).length === 0 && (
                                  <div className="text-xs text-muted-foreground italic">
                                    No summaries available
                                  </div>
                                )}
                              </div>
                            )}
                          </div>
                        </div>
                      )}

                      {/* Filtered Sections Display */}
                      {hasFilteredSections && (
                        <div className="p-2 bg-blue-50 dark:bg-blue-950/30 rounded-md border border-blue-200 dark:border-blue-800">
                          <div className="flex items-center gap-2 mb-2">
                            <Filter className="h-3 w-3 text-blue-600 dark:text-blue-400" />
                            <Label className="text-xs font-medium text-blue-900 dark:text-blue-100">
                              Filtered Sections ({report.include_sections?.length})
                            </Label>
                          </div>
                          <div className="flex flex-wrap gap-1">
                            {report.include_sections?.map((section) => (
                              <Badge 
                                key={section} 
                                variant="outline"
                                className="text-xs bg-white dark:bg-blue-900/50 border-blue-200 dark:border-blue-700 text-blue-900 dark:text-blue-100"
                              >
                                {section.replace(/_/g, ' ')}
                              </Badge>
                            ))}
                          </div>
                        </div>
                      )}
                    </CardContent>
                  </Card>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Mail className="h-5 w-5" />
              Delivery Settings
            </CardTitle>
            <CardDescription>
              Configure where reports should be delivered
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label htmlFor="destination">Email Destination</Label>
              <Input
                id="destination"
                type="email"
                placeholder="reports@customer.com"
                value={destination}
                onChange={(e) => setDestination(e.target.value)}
                className="mt-1"
              />
              <p className="text-xs text-muted-foreground mt-1">
                Primary email address for report delivery
              </p>
            </div>

            <div>
              <Label htmlFor="cc">CC Emails (comma-separated)</Label>
              <Input
                id="cc"
                type="text"
                placeholder="manager@customer.com, admin@customer.com"
                value={ccEmails}
                onChange={(e) => setCcEmails(e.target.value)}
                className="mt-1"
              />
              <p className="text-xs text-muted-foreground mt-1">
                Additional recipients (separate with commas)
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Clock className="h-5 w-5" />
              System Settings
            </CardTitle>
            <CardDescription>
              Company-wide settings inherited by this customer
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-muted-foreground mb-1">Timezone</p>
                <p className="font-medium">{config.timezone || 'UTC'}</p>
              </div>
              <div>
                <p className="text-muted-foreground mb-1">Retention Period</p>
                <p className="font-medium">{config.retention_days || 1095} days</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Zap className="h-5 w-5" />
              Actions
            </CardTitle>
            <CardDescription>
              Save your configuration or generate selected reports
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {/* Selection Controls */}
              {enabledReports.length > 0 && (
                <div className="flex items-center gap-2 p-3 bg-muted/50 rounded-md">
                  <span className="text-sm font-medium">
                    {selectedSchedules.length} of {enabledReports.filter(r => r.schedule_id).length} selected
                  </span>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleSelectAllSchedules}
                    disabled={selectedSchedules.length === enabledReports.filter(r => r.schedule_id).length}
                  >
                    Select All
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleDeselectAllSchedules}
                    disabled={selectedSchedules.length === 0}
                  >
                    Deselect All
                  </Button>
                </div>
              )}

              {/* Action Buttons */}
              <div className="flex flex-wrap gap-3">
                <Button onClick={handleSaveConfiguration} disabled={isSaving || isGenerating} size="lg">
                  {isSaving ? (
                    <>
                      <Spinner className="h-4 w-4 mr-2" />
                      Saving...
                    </>
                  ) : (
                    <>
                      <Save className="h-4 w-4 mr-2" />
                      Save Configuration
                    </>
                  )}
                </Button>

                <Button
                  onClick={handleGenerateSelectedSchedules}
                  disabled={isGenerating || isSaving || selectedSchedules.length === 0}
                  variant="secondary"
                  size="lg"
                  className="text-white"
                >
                  {isGenerating ? (
                    <>
                      <Spinner className="h-4 w-4 mr-2" />
                      Generating...
                    </>
                  ) : (
                    <>
                      <Play className="h-4 w-4 mr-2" />
                      Generate Selected Reports ({selectedSchedules.length})
                    </>
                  )}
                </Button>

                <Button variant="outline" onClick={() => navigate('/schedules')} size="lg">
                  Cancel
                </Button>
              </div>

              {enabledReports.length > 0 && (
                <p className="text-xs text-muted-foreground">
                  💡 Select schedules using the checkboxes, then click "Generate Selected Reports" to generate them immediately
                </p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

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
            <DialogTitle>Generate Report</DialogTitle>
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
                  Generating...
                </>
              ) : (
                <>
                  <Play className="h-4 w-4 mr-2" />
                  Generate
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>


    </FloatingLayout>
  )
}
