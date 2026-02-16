import { api } from '@/lib/api'
import { logger } from '@/lib/logger'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ArrowLeft, Calendar, Mail, Save, Bell, Eye, Plus, Share2, Folder, RotateCw } from 'lucide-react'
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
import { useConfigureReportsPage } from './useConfigureReportsPage'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'

export default function ConfigureReports() {
  const {
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
    retryFailedReports,
    confirmGenerate,
    handleDeliverReport,
    handleDeliverAllReports,
    handleDiscardReports,
  } = useConfigureReportsPage()

  const { user } = useAuth()
  const canConfigureTemplates = hasPermission(user, 'reports', 'configure')

  if (isLoading) {
    return (
      <ConfigureReportsLoading />
    )
  }

  if (!config) {
    return (
      <ConfigureReportsNotFound onBack={() => navigate(-1)} />
    )
  }

  return (
    <>
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
                    {canConfigureTemplates && (
                      <Button
                        onClick={() => navigate('/platform/templates')}
                        size="sm"
                        className="gap-2"
                      >
                        <Plus className="h-4 w-4" />
                        Browse Templates
                      </Button>
                    )}
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
                          onNavigateToTemplates={canConfigureTemplates ? () => navigate('/platform/templates') : undefined}
                        />
                      )
                    })}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>

          <div className="space-y-6">
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
                <div className="space-y-3">
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

            {/* <Card className="bg-primary/5 border-primary/20">
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
            </Card> */}
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
              logger.error('Discard failed', err)
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

    </>
  )
}
