import { RefreshCw, Clock, Target, AlertTriangle, Shield } from 'lucide-react'
import { Button } from '@/components/ui/button'

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
            <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
              Audit Logs
            </h1>
            <Button
              variant="ghost"
              size="icon"
              onClick={onRefresh}
              disabled={isRefreshing}
              className="mt-1 h-8 w-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800"
              title="Refresh logs"
            >
              <RefreshCw className={`h-4 w-4 text-gray-400 ${isRefreshing ? 'animate-spin' : ''}`} />
            </Button>
          </div>
          <p className="mt-2 text-muted-foreground">
            Monitor and review {totalLogs.toLocaleString()} system events, security activities, and user actions across the platform.
          </p>
        </div>
      </div>

      {stats && (
        <div className="grid w-full grid-cols-2 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-medium text-gray-500 uppercase tracking-wider">Total</span>
              <Shield className="h-4 w-4 text-purple-500" />
            </div>
            <div className="text-2xl font-bold text-gray-900 dark:text-white">
              {stats.total_logs.toLocaleString()}
            </div>
          </div>

          <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-medium text-gray-500 uppercase tracking-wider">Today</span>
              <Clock className="h-4 w-4 text-blue-500" />
            </div>
            <div className="text-2xl font-bold text-gray-900 dark:text-white">
              {stats.logs_today.toLocaleString()}
            </div>
          </div>

          <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-medium text-gray-500 uppercase tracking-wider">Actors</span>
              <Target className="h-4 w-4 text-emerald-500" />
            </div>
            <div className="text-2xl font-bold text-gray-900 dark:text-white">
              {stats.unique_actors.toLocaleString()}
            </div>
          </div>

          <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-medium text-gray-500 uppercase tracking-wider">Critical</span>
              <AlertTriangle className="h-4 w-4 text-amber-500" />
            </div>
            <div className="text-2xl font-bold text-gray-900 dark:text-white">
              {stats.critical_events.toLocaleString()}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
