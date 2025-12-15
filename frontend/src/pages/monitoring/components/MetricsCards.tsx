import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Cable, FileText, ListChecks, TrendingUp, TrendingDown, CheckCircle, XCircle } from 'lucide-react'
import type { IntegrationMetrics, ReportMetrics, QueueMetrics, SystemStatus } from '@/stores/useMonitoringStore'
import { SystemStatusMetricCard } from './SystemStatusMetricCard'

interface MetricsCardsProps {
  systemStatus: SystemStatus | null
  integrationMetrics: IntegrationMetrics | null
  reportMetrics: ReportMetrics | null
  queueMetrics: QueueMetrics | null
}

export function MetricsCards({ systemStatus, integrationMetrics, reportMetrics, queueMetrics }: MetricsCardsProps) {


  // Calculate overall integration metrics
  const totalSyncs = integrationMetrics?.integrations.reduce((sum, int) => sum + int.metrics.total_syncs, 0) || 0
  const successfulSyncs = integrationMetrics?.integrations.reduce((sum, int) => sum + int.metrics.successful_syncs, 0) || 0
  const failedSyncs = integrationMetrics?.integrations.reduce((sum, int) => sum + int.metrics.failed_syncs, 0) || 0
  const overallSuccessRate = totalSyncs > 0 ? (successfulSyncs / totalSyncs) * 100 : 0

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      {/* System Status */}
      <SystemStatusMetricCard status={systemStatus} />
      {/* Integration Metrics */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base flex items-center gap-2">
            <Cable className="h-4 w-4" />
            Integrations
          </CardTitle>
          <CardDescription>Last {integrationMetrics?.period_days || 7} days</CardDescription>
        </CardHeader>
        <CardContent>
          {integrationMetrics ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Total Syncs</span>
                <span className="text-lg font-bold">{totalSyncs}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Success Rate</span>
                <div className="flex items-center gap-2">
                  {overallSuccessRate >= 90 ? (
                    <TrendingUp className="h-4 w-4 text-green-500" />
                  ) : (
                    <TrendingDown className="h-4 w-4 text-orange-500" />
                  )}
                  <span className="text-lg font-bold">{overallSuccessRate.toFixed(1)}%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Successful</span>
                <span className="text-lg font-bold text-green-600">{successfulSyncs}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Failed</span>
                <div className="flex items-center gap-2">
                  {failedSyncs > 0 && (
                    <XCircle className="h-4 w-4 text-red-500" />
                  )}
                  <span className="text-lg font-bold">{failedSyncs}</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Active Integrations</span>
                <span className="text-sm font-medium">{integrationMetrics.integrations.filter(i => i.status === 'active').length}</span>
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">Loading...</p>
          )}
        </CardContent>
      </Card>

      {/* Report Metrics */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base flex items-center gap-2">
            <FileText className="h-4 w-4" />
            Reports
          </CardTitle>
          <CardDescription>Last {reportMetrics?.period_days || 7} days</CardDescription>
        </CardHeader>
        <CardContent>
          {reportMetrics ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Total Generated</span>
                <span className="text-lg font-bold">{reportMetrics.metrics.total_reports}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Success Rate</span>
                <div className="flex items-center gap-2">
                  {reportMetrics.metrics.success_rate >= 95 ? (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  ) : (
                    <XCircle className="h-4 w-4 text-orange-500" />
                  )}
                  <span className="text-lg font-bold">{reportMetrics.metrics.success_rate.toFixed(1)}%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Completed</span>
                <span className="text-lg font-bold text-green-600">{reportMetrics.metrics.completed_reports}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Failed</span>
                <span className="text-lg font-bold">{reportMetrics.metrics.failed_reports}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Delivery Rate</span>
                <span className="text-sm font-medium">{reportMetrics.metrics.delivery_rate.toFixed(1)}%</span>
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">Loading...</p>
          )}
        </CardContent>
      </Card>

      {/* Queue Metrics */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base flex items-center gap-2">
            <ListChecks className="h-4 w-4" />
            Queue
          </CardTitle>
          <CardDescription>Background job processing</CardDescription>
        </CardHeader>
        <CardContent>
          {queueMetrics ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Pending Jobs</span>
                <span className="text-lg font-bold">{queueMetrics.pending_jobs}</span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Failed Jobs</span>
                <div className="flex items-center gap-2">
                  {queueMetrics.failed_jobs > 0 && (
                    <XCircle className="h-4 w-4 text-red-500" />
                  )}
                  <span className="text-lg font-bold">{queueMetrics.failed_jobs}</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Workers Status</span>
                <div className="flex items-center gap-2">
                  {queueMetrics.workers_status === 'running' ? (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  ) : (
                    <XCircle className="h-4 w-4 text-red-500" />
                  )}
                  <span className="text-sm font-medium capitalize">{queueMetrics.workers_status}</span>
                </div>
              </div>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">Loading...</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
