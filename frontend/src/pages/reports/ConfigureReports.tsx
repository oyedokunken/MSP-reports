import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ArrowLeft, Calendar, Clock, Mail, Save, Plus, Trash2, Bell, Play, Zap, AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'
import { Spinner } from '@/components/ui/spinner'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'

interface ReportType {
  id: string
  name: string
  description: string
  integration_source?: string
}

interface EnabledReport {
  report_type: string
  schedule_id?: number
  frequency?: string
  time_of_day?: string
  active?: boolean
  _old_schedule_id?: number // Track old schedule ID when report_type changes
  include_sections?: string[] // For comprehensive reports
}

interface ReportConfig {
  enabled_reports: EnabledReport[]
  delivery: {
    method: string
    destination: string
    cc: string[]
  }
  timezone: string | null
  retention_days: number | null
}


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
  const [config, setConfig] = useState<ReportConfig | null>(null)
  const [enabledReports, setEnabledReports] = useState<EnabledReport[]>([])
  const [destination, setDestination] = useState('')
  const [ccEmails, setCcEmails] = useState('')
  const [availableReportTypes, setAvailableReportTypes] = useState<ReportType[]>([])
  const [showValidationModal, setShowValidationModal] = useState(false)
  const [validationErrors, setValidationErrors] = useState<string[]>([])

  useEffect(() => {
    fetchAvailableReports()
    fetchReportConfig()
  }, [id])

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

  const fetchAvailableReports = async () => {
    try {
      const response = await api.get(`/customers/${id}/available-reports`)
      const data = (response as any).data || response
      const reportData = data.data || data
      
      console.log('Available reports for customer:', reportData)
      
      const reports = (reportData.available_reports || []).map((r: any) => ({
        id: r.report_type,
        name: r.name,
        description: r.description,
        integration_source: r.integration_source
      }))
      
      setAvailableReportTypes(reports)
    } catch (error) {
      console.error('Failed to fetch available reports:', error)
      toast.error('Failed to load available reports')
    }
  }

  const fetchReportConfig = async () => {
    try {
      setIsLoading(true)
      const response = await api.get<{ data: ReportConfig }>(`/customers/${id}/reports`)
      const data = (response as any).data || response
      const reportData = data.data || data
      
      console.log('Fetched report config:', reportData)
      
      setConfig(reportData)
      const schedules = reportData.report_schedules || []
      console.log('Schedules from backend:', schedules)
      
      const mappedSchedules = schedules.map((s: any) => ({
        report_type: s.report_type,
        schedule_id: s.report_schedule_id,
        frequency: s.frequency,
        time_of_day: s.time_of_day,
        active: s.active
      }))
      
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
    } catch (err: any) {
      console.error('Failed to load report configuration:', err)
      toast.error('Failed to load report configuration', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const handleAddReport = () => {
    // If only one report available, use it as default
    const defaultReportType = availableReportTypes.length === 1 
      ? availableReportTypes[0].id 
      : (availableReportTypes.find(r => r.id === 'uptime_monthly')?.id || availableReportTypes[0]?.id || 'uptime_monthly');
    
    const newReport: any = {
      report_type: defaultReportType,
      frequency: 'monthly',
      time_of_day: '09:00',
      active: true
    };
    
    // If it's a comprehensive report, set default sections
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
    
    // If the report has a schedule_id, delete it from the backend
    if (report.schedule_id) {
      try {
        await api.delete(`/reports/schedules/${report.schedule_id}`)
        toast.success('Deleted', 'Report schedule deleted successfully')
      } catch (err: any) {
        toast.error('Delete Failed', err.response?.data?.message || err.message)
        return 
      }
    }
    
    setEnabledReports(enabledReports.filter((_, i) => i !== index))
  }

  const handleUpdateReport = (index: number, field: string, value: any) => {
    const updated = [...enabledReports]
    
    // If changing report_type, remove the old schedule_id so it creates a new one
    if (field === 'report_type' && updated[index].schedule_id) {
      const oldScheduleId = updated[index].schedule_id
      updated[index] = { 
        ...updated[index], 
        [field]: value,
        schedule_id: undefined, // Remove schedule_id so backend creates new one
        _old_schedule_id: oldScheduleId // Track old ID for deletion
      }
      
      // If changing TO comprehensive report, set default sections
      if (value === 'monthly_comprehensive') {
        updated[index].include_sections = ['uptime', 'tickets', 'backups', 'security']
      }
    } else {
      updated[index] = { ...updated[index], [field]: value }
    }
    
    setEnabledReports(updated)
  }

  // Validate configuration before saving
  const validateConfiguration = (): string[] => {
    const errors: string[] = []
    
    // Check if there are any reports configured
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
      
      // Delete old schedules if report_type was changed
      const oldSchedulesToDelete = enabledReports
        .filter(report => (report as any)._old_schedule_id)
        .map(report => (report as any)._old_schedule_id)
      
      for (const oldScheduleId of oldSchedulesToDelete) {
        try {
          await api.delete(`/reports/schedules/${oldScheduleId}`)
        } catch (err) {
          console.error('Failed to delete old schedule:', err)
          // Continue anyway
        }
      }
      
      const ccArray = ccEmails
        .split(',')
        .map(email => email.trim())
        .filter(email => email.length > 0)

      const schedules = enabledReports.map(report => {
        const scheduleConfig: any = {}
        
        // Add day_of_month for monthly reports
        if (report.frequency === 'monthly' && (report as any).day_of_month) {
          scheduleConfig.day_of_month = (report as any).day_of_month
        }
        
        // Add day_of_week for weekly reports
        if (report.frequency === 'weekly' && (report as any).day_of_week !== undefined) {
          scheduleConfig.day_of_week = (report as any).day_of_week
        }
        
        // Add include_sections for comprehensive reports
        if (report.report_type === 'monthly_comprehensive' && (report as any).include_sections) {
          scheduleConfig.include_sections = (report as any).include_sections
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
    } catch (err: any) {
      toast.error('Save Failed', err.response?.data?.message || err.message)
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
      const data = (response as any).data || response
      
      toast.success('Report Generated!', `Successfully generated ${data.data?.length || 1} report(s)`)
      
      setTimeout(() => {
        navigate('/platform/reports')
      }, 1500)
    } catch (err: any) {
      toast.error('Generation Failed', err.response?.data?.message || err.message)
    } finally {
      setIsGenerating(false)
    }
  }

  if (isLoading) {
    return (
      <FloatingLayout>
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Skeleton className="h-9 w-20" />
              <div>
                <Skeleton className="h-8 w-64" />
                <Skeleton className="h-4 w-48 mt-2" />
              </div>
            </div>
          </div>
          <Card>
            <CardHeader>
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-4 w-full mt-2" />
            </CardHeader>
            <CardContent className="space-y-6">
              {[1, 2, 3].map((i) => (
                <div key={i} className="space-y-4 p-4 border rounded-lg">
                  <div className="flex items-center justify-between">
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-6 w-12" />
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-full" />
                  </div>
                </div>
              ))}\n            </CardContent>
          </Card>
        </div>
      </FloatingLayout>
    )
  }

  if (!config) {
    return (
      <FloatingLayout>
        <div className="flex flex-col items-center justify-center h-64">
          <p className="text-lg font-medium text-muted-foreground">Customer not found</p>
          <Button onClick={() => navigate(-1)} className="mt-4">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Go Back1
          </Button>
        </div>
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
                  Configure which reports to generate automatically and when
                </CardDescription>
              </div>
              <Button onClick={handleAddReport} size="sm">
                <Plus className="h-4 w-4 mr-2" />
                Add Report
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            {enabledReports.length === 0 ? (
              <div className="text-center py-8">
                <Calendar className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <p className="text-sm text-muted-foreground">No automatic reports configured</p>
                <Button onClick={handleAddReport} variant="outline" className="mt-4">
                  <Plus className="h-4 w-4 mr-2" />
                  Add Your First Report
                </Button>
              </div>
            ) : (
              <div className="space-y-4">
                {enabledReports.map((report, index) => (
                  <div key={index} className="p-4 rounded-lg border border-border space-y-4">
                    <div className="flex items-start justify-between">
                      <div className="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <Label htmlFor={`report-type-${index}`}>Report Type</Label>
                          <Select
                            value={report.report_type || ''}
                            onValueChange={(value) => handleUpdateReport(index, 'report_type', value)}
                          >
                            <SelectTrigger id={`report-type-${index}`} className="mt-1">
                              <SelectValue placeholder="Select report type">
                                {report.report_type && availableReportTypes.find(t => t.id === report.report_type)?.name}
                              </SelectValue>
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

                        <div>
                          <Label htmlFor={`frequency-${index}`}>Frequency</Label>
                          <Select
                            value={report.frequency || 'monthly'}
                            onValueChange={(value) => handleUpdateReport(index, 'frequency', value)}
                          >
                            <SelectTrigger id={`frequency-${index}`} className="mt-1">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              {FREQUENCIES.map((freq) => (
                                <SelectItem key={freq.value} value={freq.value}>
                                  {freq.label}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>

                        {report.frequency !== 'on-demand' && (
                          <div>
                            <Label htmlFor={`time-${index}`}>Time of Day</Label>
                            <Input
                              id={`time-${index}`}
                              type="time"
                              value={report.time_of_day || '09:00'}
                              onChange={(e) => handleUpdateReport(index, 'time_of_day', e.target.value)}
                              className="mt-1"
                            />
                          </div>
                        )}

                        {report.frequency === 'monthly' && (
                          <div>
                            <Label htmlFor={`day-${index}`}>Day of Month</Label>
                            <Select
                              value={String((report as any).day_of_month || '1')}
                              onValueChange={(value) => handleUpdateReport(index, 'day_of_month', parseInt(value))}
                            >
                              <SelectTrigger id={`day-${index}`} className="mt-1">
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
                            <Label htmlFor={`weekday-${index}`}>Day of Week</Label>
                            <Select
                              value={String((report as any).day_of_week || '1')}
                              onValueChange={(value) => handleUpdateReport(index, 'day_of_week', parseInt(value))}
                            >
                              <SelectTrigger id={`weekday-${index}`} className="mt-1">
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

                        <div className="flex items-center gap-2 pt-6">
                          <Switch
                            id={`active-${index}`}
                            checked={report.active !== false}
                            onCheckedChange={(checked: boolean) => handleUpdateReport(index, 'active', checked)}
                          />
                          <Label htmlFor={`active-${index}`} className="cursor-pointer">
                            {report.active !== false ? 'Active' : 'Inactive'}
                          </Label>
                        </div>
                      </div>

                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleRemoveReport(index)}
                        className="text-destructive hover:text-destructive"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>

                    <p className="text-xs text-muted-foreground">
                      {availableReportTypes.find(t => t.id === report.report_type)?.description}
                    </p>

                    {/* Section selector for Monthly Comprehensive Report */}
                    {report.report_type === 'monthly_comprehensive' && (
                      <div className="mt-4 p-3 bg-muted/50 rounded-md">
                        <Label className="text-sm font-medium mb-2 block">Include Sections:</Label>
                        <div className="grid grid-cols-2 gap-2">
                          {[
                            { id: 'uptime', label: 'System Uptime', icon: '📊' },
                            { id: 'tickets', label: 'Support Tickets', icon: '🎫' },
                            { id: 'backups', label: 'Backup Status', icon: '💾' },
                            { id: 'security', label: 'Security Alerts', icon: '🔒' },
                          ].map((section) => {
                            const includeSections = (report as any).include_sections || ['uptime', 'tickets', 'backups', 'security'];
                            const isChecked = includeSections.includes(section.id);
                            
                            return (
                              <div key={section.id} className="flex items-center space-x-2">
                                <input
                                  type="checkbox"
                                  id={`section-${index}-${section.id}`}
                                  checked={isChecked}
                                  onChange={(e) => {
                                    const currentSections = (report as any).include_sections || ['uptime', 'tickets', 'backups', 'security'];
                                    const newSections = e.target.checked
                                      ? [...currentSections, section.id]
                                      : currentSections.filter((s: string) => s !== section.id);
                                    handleUpdateReport(index, 'include_sections', newSections);
                                  }}
                                  className="h-4 w-4 rounded border-gray-300"
                                />
                                <Label 
                                  htmlFor={`section-${index}-${section.id}`}
                                  className="text-sm cursor-pointer flex items-center gap-1"
                                >
                                  <span>{section.icon}</span>
                                  <span>{section.label}</span>
                                </Label>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    )}

                    {report.schedule_id && (
                      <Badge variant="secondary" className="text-xs text-white">
                        Schedule ID: {report.schedule_id}
                      </Badge>
                    )}
                  </div>
                ))}
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
            </CardTitle>
            <CardDescription>
              Save your configuration or generate a report immediately
            </CardDescription>
          </CardHeader>
          <CardContent>
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
                onClick={handleGenerateNow} 
                disabled={isGenerating || isSaving || enabledReports.length === 0}
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
                    Generate Report Now
                  </>
                )}
              </Button>
              
              <Button variant="outline" onClick={() => navigate('/schedules')} size="lg">
                Cancel
              </Button>
            </div>
            
            {enabledReports.length === 0 && (
              <p className="text-xs text-muted-foreground mt-3">
                💡 Add at least one report to enable immediate generation
              </p>
            )}
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
    </FloatingLayout>
  )
}
