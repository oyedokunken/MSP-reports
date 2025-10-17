import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ArrowLeft, Calendar, Clock, Mail, Save, Plus, Trash2, Bell, Play, Zap } from 'lucide-react'
import { api } from '@/lib/api'

interface ReportType {
  id: string
  name: string
  description: string
}

interface EnabledReport {
  report_type: string
  schedule_id?: number
  frequency?: string
  time_of_day?: string
  active?: boolean
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

const AVAILABLE_REPORT_TYPES: ReportType[] = [
  { id: 'monthly_uptime', name: 'Monthly Uptime Report', description: 'Monthly uptime statistics and availability' },
  { id: 'weekly_backup', name: 'Weekly Backup Report', description: 'Weekly backup status and success rates' },
  { id: 'security_summary', name: 'Security Summary', description: 'Security events and threat analysis' },
  { id: 'performance_metrics', name: 'Performance Metrics', description: 'System performance and resource usage' },
]

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

  useEffect(() => {
    fetchReportConfig()
  }, [id])

  const fetchReportConfig = async () => {
    try {
      setIsLoading(true)
      const response = await api.get<{ data: ReportConfig }>(`/customers/${id}/reports`)
      const data = (response as any).data || response
      const reportData = data.data || data
      
      setConfig(reportData)
      setEnabledReports(reportData.enabled_reports || [])
      setDestination(reportData.delivery?.destination || '')
      setCcEmails(reportData.delivery?.cc?.join(', ') || '')
    } catch (err: any) {
      toast.error('Failed to load report configuration', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const handleAddReport = () => {
    setEnabledReports([
      ...enabledReports,
      {
        report_type: 'monthly_uptime',
        frequency: 'monthly',
        time_of_day: '09:00',
        active: true
      }
    ])
  }

  const handleRemoveReport = (index: number) => {
    setEnabledReports(enabledReports.filter((_, i) => i !== index))
  }

  const handleUpdateReport = (index: number, field: string, value: any) => {
    const updated = [...enabledReports]
    updated[index] = { ...updated[index], [field]: value }
    setEnabledReports(updated)
  }

  const handleSaveConfiguration = async () => {
    try {
      setIsSaving(true)
      
      const ccArray = ccEmails
        .split(',')
        .map(email => email.trim())
        .filter(email => email.length > 0)

      // Update reports configuration
      await api.patch(`/customers/${id}/reports`, {
        enabled_reports: enabledReports,
        delivery: {
          method: 'email',
          destination,
          cc: ccArray
        }
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
      
      // Optionally navigate to reports list
      setTimeout(() => {
        navigate('/reports')
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
        <div className="flex items-center justify-center h-64">
          <Spinner className="h-8 w-8" />
        </div>
      </FloatingLayout>
    )
  }

  if (!config) {
    return (
      <FloatingLayout>
        <div className="flex flex-col items-center justify-center h-64">
          <p className="text-lg font-medium text-muted-foreground">Customer not found</p>
          <Button onClick={() => navigate('/schedules')} className="mt-4">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Schedules
          </Button>
        </div>
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={() => navigate('/schedules')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold text-foreground">Configure Automatic Reports</h1>
            <p className="mt-2 text-muted-foreground">
              Set up automated report generation and delivery schedules
            </p>
          </div>
        </div>

        {/* Enabled Reports */}
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
                        {/* Report Type */}
                        <div>
                          <Label htmlFor={`report-type-${index}`}>Report Type</Label>
                          <Select
                            value={report.report_type}
                            onValueChange={(value) => handleUpdateReport(index, 'report_type', value)}
                          >
                            <SelectTrigger id={`report-type-${index}`} className="mt-1">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              {AVAILABLE_REPORT_TYPES.map((type) => (
                                <SelectItem key={type.id} value={type.id}>
                                  {type.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>

                        {/* Frequency */}
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

                        {/* Time of Day */}
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

                        {/* Active Status */}
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

                      {/* Remove Button */}
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleRemoveReport(index)}
                        className="text-destructive hover:text-destructive"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>

                    {/* Report Description */}
                    <p className="text-xs text-muted-foreground">
                      {AVAILABLE_REPORT_TYPES.find(t => t.id === report.report_type)?.description}
                    </p>

                    {/* Schedule ID Badge */}
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

        {/* Delivery Configuration */}
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

        {/* System Settings */}
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

        {/* Action Buttons */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Zap className="h-5 w-5" />
              Quick Actions
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
    </FloatingLayout>
  )
}
