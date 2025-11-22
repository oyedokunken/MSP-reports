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
import { ArrowLeft, FileText, Mail, Save, Play } from 'lucide-react'
import { api } from '@/lib/api'

interface ReportConfig {
  enabled_reports: Array<{
    report_type: string
    schedule_id?: number
  }>
  report_branding: {
    customer_logo_url?: string
    report_title?: string
    footer_notes?: string
  }
  delivery: {
    method: string
    destination: string
    cc: string[]
  }
  timezone: string | null
  retention_days: number | null
  format: any
}

export default function CustomerReports() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const toast = useToast()
  
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isGenerating, setIsGenerating] = useState(false)
  const [config, setConfig] = useState<ReportConfig | null>(null)
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
      
      // Ensure enabled_reports is always an array
      const configData = {
        ...reportData,
        enabled_reports: reportData.enabled_reports || [],
        delivery: reportData.delivery || { method: 'email', destination: '', cc: [] },
        report_branding: reportData.report_branding || {}
      }
      
      setConfig(configData)
      setDestination(configData.delivery?.destination || '')
      setCcEmails(configData.delivery?.cc?.join(', ') || '')
    } catch (err: any) {
      toast.error('Failed to load report configuration', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

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
    } catch (err: any) {
      toast.error('Save Failed', err.response?.data?.message || err.message)
    } finally {
      setIsSaving(false)
    }
  }

  const handleGenerateReport = async () => {
    try {
      setIsGenerating(true)
      await api.post(`/reports/generate/${id}`)
      toast.success('Report Generated', 'Report has been generated successfully')
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
            <CardContent className="space-y-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </CardContent>
          </Card>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <Card key={i}>
                <CardContent className="pt-6">
                  <div className="space-y-4">
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-3/4" />
                    <Skeleton className="h-9 w-full" />
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </FloatingLayout>
    )
  }

  if (!config) {
    return (
      <FloatingLayout>
        <div className="flex flex-col items-center justify-center h-64">
          <p className="text-lg font-medium text-muted-foreground">Customer not found</p>
          <Button onClick={() => navigate('/customers')} className="mt-4">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Customers
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
          <Button variant="ghost" size="sm" onClick={() => navigate('/customers')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold text-foreground">Customer Report Configuration</h1>
            <p className="mt-2 text-muted-foreground">
              Configure report settings and delivery options
            </p>
          </div>
        </div>

        {/* Enabled Reports */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Enabled Reports
            </CardTitle>
            <CardDescription>
              Reports configured for this customer
            </CardDescription>
          </CardHeader>
          <CardContent>
            {!config?.enabled_reports || config.enabled_reports.length === 0 ? (
              <p className="text-sm text-muted-foreground">No reports enabled for this customer</p>
            ) : (
              <div className="space-y-2">
                {config.enabled_reports.map((report, idx) => (
                  <div key={idx} className="flex items-center justify-between p-3 rounded-lg border border-border">
                    <div>
                      <p className="font-medium">{report.report_type}</p>
                      {report.schedule_id && (
                        <p className="text-xs text-muted-foreground">Schedule ID: {report.schedule_id}</p>
                      )}
                    </div>
                    <Badge variant="default">Enabled</Badge>
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
              Delivery Configuration
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

            <div className="flex gap-2 pt-4">
              <Button onClick={handleSaveDestination} disabled={isSaving}>
                {isSaving ? (
                  <>
                    <Spinner className="h-4 w-4 mr-2" />
                    Saving...
                  </>
                ) : (
                  <>
                    <Save className="h-4 w-4 mr-2" />
                    Save Destination
                  </>
                )}
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Report Settings */}
        <Card>
          <CardHeader>
            <CardTitle>Report Settings</CardTitle>
            <CardDescription>
              Additional configuration options
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
              <div>
                <p className="text-muted-foreground mb-1">Delivery Method</p>
                <p className="font-medium capitalize">{config.delivery?.method || 'email'}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Actions */}
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
            <CardDescription>
              Generate reports on-demand
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button 
              onClick={handleGenerateReport} 
              disabled={isGenerating || config.enabled_reports.length === 0}
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
            {config.enabled_reports.length === 0 && (
              <p className="text-xs text-muted-foreground mt-2">
                Enable at least one report type to generate reports
              </p>
            )}
          </CardContent>
        </Card>
      </div>
    </FloatingLayout>
  )
}
