import { RefreshCw, Download, Shield } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface AuditLogsHeaderProps {
  onRefresh: () => void
  onExport: () => void
  isRefreshing: boolean
  totalLogs: number
}

export function AuditLogsHeader({
  onRefresh,
  onExport,
  isRefreshing,
  totalLogs,
}: AuditLogsHeaderProps) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-3">
        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 shadow-lg">
          <Shield className="h-6 w-6 text-white" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Audit Logs
          </h1>
          <p className="text-sm text-gray-600 dark:text-gray-400">
            {totalLogs.toLocaleString()} total events
          </p>
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          onClick={onRefresh}
          disabled={isRefreshing}
          className="gap-2"
        >
          <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
        
        <Button
          variant="default"
          onClick={onExport}
          className="gap-2"
        >
          <Download className="h-4 w-4" />
          Export
        </Button>
      </div>
    </div>
  )
}
