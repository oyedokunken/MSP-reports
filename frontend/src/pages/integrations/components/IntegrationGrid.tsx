import { StatCardSkeleton, EmptyState } from '@/components/ui/LoadingStates'
import { Cable } from 'lucide-react'
import type { Integration } from '../types'
import { IntegrationCard } from './IntegrationCard'

type IntegrationGridProps = {
  isLoading: boolean
  items: Integration[]
  syncingId: number | null
  progress: number
  onSync: (id: number, name: string) => void
  onEmptyAction: () => void
}

export function IntegrationGrid ({ isLoading, items, syncingId, progress, onSync, onEmptyAction }: IntegrationGridProps) {
  return (
    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      {isLoading ? (
        <>
          <StatCardSkeleton />
          <StatCardSkeleton />
          <StatCardSkeleton />
        </>
      ) : items.length === 0 ? (
        <div className="col-span-full">
          <EmptyState
            icon={Cable}
            title="No Integrations Yet"
            description="Connect your first integration to start syncing data and generating reports"
            actionLabel="Add Integration"
            onAction={onEmptyAction}
          />
        </div>
      ) : (
        items.map(integration => (
          <IntegrationCard
            key={integration.id}
            integration={integration}
            isSyncing={syncingId === integration.id}
            progress={progress}
            onSync={onSync}
          />
        ))
      )}

      {!isLoading && items.length > 0 && (
        <button className="rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-[hsl(var(--auth-card))] p-6 hover:bg-muted/50 transition-colors flex flex-col items-center justify-center min-h-[250px]">
          <Cable className="h-12 w-12 text-muted-foreground mb-3" />
          <p className="text-sm font-medium text-foreground">Add New Integration</p>
          <p className="text-xs text-muted-foreground mt-1">Connect another system</p>
        </button>
      )}
    </div>
  )
}


