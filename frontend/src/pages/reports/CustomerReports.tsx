import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { ArrowLeft, FileText, Mail, Save, Play } from 'lucide-react'
import { Spinner } from '@/components/ui/spinner'
import { useCustomerReports } from './hooks'
import { CustomerReportsLoading, CustomerReportsNotFound } from './components/CustomerReportsStates'

export default function CustomerReports() {
  const {
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
  } = useCustomerReports()

  if (isLoading) {
    return (
      <FloatingLayout>
        <CustomerReportsLoading />
      </FloatingLayout>
    )
  }

  if (!config) {
    return (
      <FloatingLayout>
        <CustomerReportsNotFound onBack={() => navigate('/customers')} />
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
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
