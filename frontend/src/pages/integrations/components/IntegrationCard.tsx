import { AnimatedCard } from '@/components/ui/AnimatedCard'
import { Cable, RefreshCw } from 'lucide-react'
import { LoadingSpinner } from '@/components/ui/LoadingStates'
import type { Integration } from '../types'
import { getStatusIcon, getStatusText } from '../utils'

type IntegrationCardProps = {
  integration: Integration
  isSyncing: boolean
  progress: number
  onSync: (id: number, name: string) => void
}

export function IntegrationCard ({ integration, isSyncing, progress, onSync }: IntegrationCardProps) {
  const statusInfo = getStatusText(integration.status, integration.syncStatus)
  return (
    <AnimatedCard
      hoverLift={!isSyncing}
      className={`p-6 transition-all duration-300 ${isSyncing ? 'ring-2 ring-primary/50 shadow-lg bg-primary/5' : ''}`}
    >
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-3">
          <div className="rounded-lg bg-primary/10 p-2">
            <Cable className="h-6 w-6 text-primary" />
          </div>
          <div>
            <h3 className="font-semibold text-foreground">{integration.name}</h3>
            <p className="text-xs text-muted-foreground">{integration.type}</p>
          </div>
        </div>
        <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${statusInfo.color}`}>
          {statusInfo.text}
        </span>
      </div>

      <div className="mt-6 space-y-3">
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Last Sync</span>
          <span className="font-medium text-foreground">{integration.lastSync}</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Customers</span>
          <span className="font-medium text-foreground">{integration.customers}</span>
        </div>
        <div className="flex items-center gap-2 pt-2">
          {getStatusIcon(integration.status, integration.syncStatus)}
          <span className="text-sm text-muted-foreground">
            {integration.syncStatus === 'success' ? 'Synced successfully' : 'Sync failed - check logs'}
          </span>
        </div>

        {isSyncing && (
          <div className="mt-3 space-y-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted-foreground">Syncing...</span>
              <span className="font-medium text-primary">{Math.round(progress)}%</span>
            </div>
            <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
              <div className="bg-primary h-2 rounded-full transition-all duration-300 ease-out" style={{ width: `${progress}%` }} />
            </div>
          </div>
        )}
      </div>

      <div className="mt-4 flex gap-2 border-t border-border pt-4">
        <button
          onClick={() => onSync(integration.id, integration.name)}
          disabled={isSyncing}
          className={`flex-1 flex items-center justify-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium transition-all ${
            isSyncing ? 'bg-primary text-primary-foreground border-primary' : 'bg-background text-foreground hover:bg-muted'
          } disabled:opacity-50 disabled:cursor-not-allowed`}
        >
          {isSyncing ? <LoadingSpinner size="sm" /> : <RefreshCw className="h-4 w-4" />}
          {isSyncing ? 'Syncing...' : 'Sync Now'}
        </button>
        <button className="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted">
          Configure
        </button>
      </div>
    </AnimatedCard>
  )
}


