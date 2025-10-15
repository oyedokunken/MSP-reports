import { AnimatedCard } from '@/components/ui/AnimatedCard'

type QuickActionsCardProps = {
  onInfo: (title: string, description?: string) => void
}

export function QuickActionsCard ({ onInfo }: QuickActionsCardProps) {
  return (
    <AnimatedCard className="p-6">
      <h2 className="text-lg font-semibold text-foreground">Quick Actions</h2>
      <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <button
          onClick={() => onInfo('Coming Soon', 'Customer mapping workspace')}
          className="rounded-lg border border-border bg-background px-4 py-3 text-sm font-medium text-foreground hover:bg-muted"
        >
          Review Mappings
        </button>
        <button
          onClick={() => onInfo('Coming Soon', 'Sync engine')}
          className="rounded-lg border border-border bg-background px-4 py-3 text-sm font-medium text-foreground hover:bg-muted"
        >
          Sync All
        </button>
        <button
          onClick={() => onInfo('Coming Soon', 'Report templates')}
          className="rounded-lg border border-border bg-background px-4 py-3 text-sm font-medium text-foreground hover:bg-muted"
        >
          Manage Templates
        </button>
        <button
          onClick={() => onInfo('Coming Soon', 'Delivery destinations')}
          className="rounded-lg border border-border bg-background px-4 py-3 text-sm font-medium text-foreground hover:bg-muted"
        >
          Delivery Settings
        </button>
      </div>
    </AnimatedCard>
  )
}


