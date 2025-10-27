import { Button } from '@/components/ui/button'
import { RefreshCw } from 'lucide-react'

interface MonitoringHeaderProps {
  lastUpdated: Date | null
  onRefresh: () => void
  isRefreshing: boolean
}

export function MonitoringHeader({ lastUpdated, onRefresh, isRefreshing }: MonitoringHeaderProps) {
  const formatTime = (date: Date | null) => {
    if (!date) return 'Never'
    return date.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    })
  }

  return (
    <div className="flex items-center justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">System Monitoring</h1>
        <p className="mt-2 text-muted-foreground">
          Real-time system health and performance metrics
        </p>
      </div>
      <div className="flex items-center gap-3">
        <span className="text-sm text-muted-foreground">
          Last updated: {formatTime(lastUpdated)}
        </span>
        <Button
          onClick={onRefresh}
          disabled={isRefreshing}
          variant="outline"
          size="sm"
        >
          <RefreshCw className={`h-4 w-4 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>
    </div>
  )
}
