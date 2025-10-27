import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Activity, Database, Zap, HardDrive, Clock, CheckCircle, XCircle } from 'lucide-react'
import type { SystemStatus } from '@/stores/useMonitoringStore'

interface SystemStatusCardProps {
  status: SystemStatus | null
}

export function SystemStatusCard({ status }: SystemStatusCardProps) {
  if (!status) return null

  const getStatusBadge = (statusValue: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive', label: string, color: string }> = {
      healthy: { variant: 'default', label: 'Healthy', color: 'bg-green-500' },
      degraded: { variant: 'secondary', label: 'Degraded', color: 'bg-orange-500' },
      down: { variant: 'destructive', label: 'Down', color: 'bg-red-500' },
    }
    const config = variants[statusValue] || variants.healthy
    return <Badge variant={config.variant} className={config.color}>{config.label}</Badge>
  }

  const formatBytes = (bytes: number) => {
    const gb = bytes / (1024 * 1024 * 1024)
    return `${gb.toFixed(2)} GB`
  }

  const calculateDiskPercentage = () => {
    if (!status.system.storage.total_bytes) return 0
    return ((status.system.storage.free_bytes / status.system.storage.total_bytes) * 100).toFixed(2)
  }

  // Determine overall health
  const overallHealth = status.integrations.health === 'healthy' && 
                        status.reports.health === 'healthy' && 
                        status.queue.health === 'healthy'
    ? 'healthy'
    : 'degraded'

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Activity className="h-5 w-5" />
              System Status
            </CardTitle>
            <CardDescription>Overall system health</CardDescription>
          </div>
          {getStatusBadge(overallHealth)}
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <div className="flex items-center gap-3">
            {status.system.database.status === 'healthy' ? (
              <CheckCircle className="h-5 w-5 text-green-500" />
            ) : (
              <XCircle className="h-5 w-5 text-red-500" />
            )}
            <div>
              <Database className="h-4 w-4 text-muted-foreground mb-1" />
              <p className="text-sm font-medium">Database</p>
              <p className="text-xs text-muted-foreground">
                {status.system.database.message}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {status.system.redis.status === 'healthy' ? (
              <CheckCircle className="h-5 w-5 text-green-500" />
            ) : (
              <XCircle className="h-5 w-5 text-red-500" />
            )}
            <div>
              <Zap className="h-4 w-4 text-muted-foreground mb-1" />
              <p className="text-sm font-medium">Redis</p>
              <p className="text-xs text-muted-foreground">
                {status.system.redis.message}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {status.queue.health === 'healthy' ? (
              <CheckCircle className="h-5 w-5 text-green-500" />
            ) : (
              <XCircle className="h-5 w-5 text-red-500" />
            )}
            <div>
              <Activity className="h-4 w-4 text-muted-foreground mb-1" />
              <p className="text-sm font-medium">Queue</p>
              <p className="text-xs text-muted-foreground">
                {status.queue.pending_jobs} pending, {status.queue.failed_jobs} failed
              </p>
            </div>
          </div>

          <div>
            <HardDrive className="h-4 w-4 text-muted-foreground mb-1" />
            <p className="text-sm font-medium">Disk Space</p>
            <p className="text-xs text-muted-foreground">
              {calculateDiskPercentage()}% free ({formatBytes(status.system.storage.free_bytes)})
            </p>
          </div>

          <div>
            <Clock className="h-4 w-4 text-muted-foreground mb-1" />
            <p className="text-sm font-medium">Uptime</p>
            <p className="text-xs text-muted-foreground">
              {status.system.uptime.message}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
