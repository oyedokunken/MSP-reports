import { AnimatedCard, PulsingDot } from '@/components/ui/AnimatedCard'
import { Activity } from 'lucide-react'
import { ActivityCardSkeleton, EmptyState } from '@/components/ui/LoadingStates'
import type { RecentActivity } from '../types'

type RecentActivityCardProps = {
  isLoading: boolean
  items: RecentActivity[]
}

export function RecentActivityCard ({ isLoading, items }: RecentActivityCardProps) {
  return (
    <AnimatedCard className="p-6">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-foreground">Recent Activity</h2>
        <button className="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
          View all
        </button>
      </div>

      <div className="mt-4">
        {isLoading ? (
          <ActivityCardSkeleton />
        ) : items.length === 0 ? (
          <EmptyState
            icon={Activity}
            title="No activity yet"
            description="Once you start syncing data and generating reports, activity will show up here."
          />
        ) : (
          <div className="divide-y divide-border">
            {items.map((item) => (
              <div key={item.id} className="flex items-center justify-between py-3">
                <div className="flex items-center gap-3">
                  <PulsingDot color={item.severity} />
                  <div>
                    <p className="text-sm font-medium text-foreground">{item.title}</p>
                    <p className="text-xs text-muted-foreground">{item.time}</p>
                  </div>
                </div>
                <span className="text-xs rounded-full bg-muted px-2 py-1">
                  {item.type}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </AnimatedCard>
  )
}


