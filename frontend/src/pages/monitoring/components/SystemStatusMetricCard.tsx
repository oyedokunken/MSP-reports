import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Activity, CheckCircle, XCircle } from 'lucide-react'
import type { SystemStatus } from '@/stores/useMonitoringStore'

interface SystemStatusMetricCardProps {
  status: SystemStatus | null
}

export function SystemStatusMetricCard({ status }: SystemStatusMetricCardProps) {
  if (!status) {
    return (
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base flex items-center gap-2">
            <Activity className="h-4 w-4" />
            System Status
          </CardTitle>
          <CardDescription>Overall health</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">Loading...</p>
        </CardContent>
      </Card>
    )
  }

  // Determine overall health
  const overallHealth = status.integrations.health === 'healthy' && 
                        status.reports.health === 'healthy' && 
                        status.queue.health === 'healthy'
    ? 'healthy'
    : 'degraded'

  const healthyCount = [
    status.system.database.status === 'healthy',
    status.system.redis.status === 'healthy',
    status.queue.health === 'healthy'
  ].filter(Boolean).length

  const totalChecks = 3

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-base flex items-center gap-2">
          <Activity className="h-4 w-4" />
          System Status
        </CardTitle>
        <CardDescription>Overall health</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Health Status</span>
            <div className="flex items-center gap-2">
              {overallHealth === 'healthy' ? (
                <CheckCircle className="h-4 w-4 text-green-500" />
              ) : (
                <XCircle className="h-4 w-4 text-orange-500" />
              )}
              <span className="text-lg font-bold capitalize">{overallHealth}</span>
            </div>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Services</span>
            <span className="text-lg font-bold">{healthyCount}/{totalChecks}</span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Database</span>
            {status.system.database.status === 'healthy' ? (
              <CheckCircle className="h-4 w-4 text-green-500" />
            ) : (
              <XCircle className="h-4 w-4 text-red-500" />
            )}
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Redis</span>
            {status.system.redis.status === 'healthy' ? (
              <CheckCircle className="h-4 w-4 text-green-500" />
            ) : (
              <XCircle className="h-4 w-4 text-red-500" />
            )}
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Uptime</span>
            <span className="text-sm font-medium">{status.system.uptime.message}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
