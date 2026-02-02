import { RefreshCw, Clock, Target, AlertTriangle, Shield } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AnimatedStatCard } from '@/components/ui/AnimatedCard'

import type { AuditLogStats } from '../types'

interface AuditLogsHeaderProps {
  onRefresh: () => void
  isRefreshing: boolean
  totalLogs: number
  stats: AuditLogStats | null
}

export function AuditLogsHeader({
  onRefresh,
  isRefreshing,
  totalLogs,
  stats,
}: AuditLogsHeaderProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
              Audit Logs
            </h1>
            <Button
              variant="ghost"
              size="icon"
              onClick={onRefresh}
              disabled={isRefreshing}
              className="mt-1 h-8 w-8 rounded-full hover:bg-muted"
              title="Refresh logs"
            >
              <RefreshCw className={`h-4 w-4 text-muted-foreground ${isRefreshing ? 'animate-spin' : ''}`} />
            </Button>
          </div>
          <p className="mt-2 text-muted-foreground">
            Monitor and review {totalLogs.toLocaleString()} system events, security activities, and user actions across the platform.
          </p>
        </div>
      </div>

      {stats && (
        <div className="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <AnimatedStatCard
            name="Total Logs"
            value={stats.total_logs.toLocaleString()}
            icon={Shield}
          />
          <AnimatedStatCard
            name="Today"
            value={stats.logs_today.toLocaleString()}
            icon={Clock}
          />
          <AnimatedStatCard
            name="Unique Actors"
            value={stats.unique_actors.toLocaleString()}
            icon={Target}
          />
          <AnimatedStatCard
            name="Critical Events"
            value={stats.critical_events.toLocaleString()}
            icon={AlertTriangle}
          />
        </div>
      )}
    </div>
  )
}
