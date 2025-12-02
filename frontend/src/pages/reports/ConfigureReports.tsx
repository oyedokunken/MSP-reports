import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ArrowLeft, Calendar, Clock, Mail, Save, Plus, Trash2, Bell, Play, Zap } from 'lucide-react'
import { Spinner } from '@/components/ui/spinner'
import { useConfigureReports } from './hooks'
import { ConfigureReportsLoading, ConfigureReportsNotFound } from './components/ConfigureReportsStates'
import { FREQUENCIES } from './utils'

export default function ConfigureReports() {
  const {
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
  } = useConfigureReports()

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
    </FloatingLayout>
  )
}
