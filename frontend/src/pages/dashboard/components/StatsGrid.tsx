import { AnimatedStatCard } from '@/components/ui/AnimatedCard'
import { StatCardSkeleton } from '@/components/ui/LoadingStates'
import type { Stat } from '../types'

type StatsGridProps = {
  isLoading: boolean
  stats: Stat[]
}

export function StatsGrid({ isLoading, stats }: StatsGridProps) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-tour="dashboard-stats">
      {isLoading
        ? [1, 2, 3, 4].map((i) => <StatCardSkeleton key={i} />)
        : stats.map((s) => (
          <AnimatedStatCard key={s.label} name={s.label} value={s.value} icon={s.icon} trend={s.trend} />
        ))}
    </div>
  )
}