import { AnimatedCard } from '@/components/ui/AnimatedCard'
import { FileText, Calendar } from 'lucide-react'
import { EmptyState, StatCardSkeleton } from '@/components/ui/LoadingStates'
import type { UpcomingReport } from '../types'

type UpcomingReportsProps = {
  isLoading: boolean
  reports: UpcomingReport[]
  onInfo: (title: string, description?: string) => void
}

export function UpcomingReports ({ isLoading, reports, onInfo }: UpcomingReportsProps) {
  return (
    <AnimatedCard className="p-6">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-foreground">Upcoming Reports</h2>
        <button
          className="text-sm text-muted-foreground hover:text-foreground"
          onClick={() => onInfo('Navigate', 'Go to Reports list')}
        >
          View all
        </button>
      </div>

      <div className="mt-4 space-y-3">
        {isLoading ? (
          <>
            <StatCardSkeleton />
            <StatCardSkeleton />
            <StatCardSkeleton />
          </>
        ) : reports.length === 0 ? (
          <EmptyState icon={FileText} title="No upcoming reports" description="Create a report schedule to see it here." />
        ) : (
          reports.map((r) => (
            <div key={r.id} className="flex items-center justify-between rounded-lg border border-border p-4">
              <div className="flex items-center gap-3">
                <div className="rounded-lg bg-primary/10 p-2">
                  <FileText className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <p className="font-medium text-foreground">{r.name}</p>
                  <p className="text-xs text-muted-foreground">{r.customer}</p>
                </div>
              </div>
              <div className="flex items-center gap-3 text-sm">
                <div className="flex items-center gap-1 text-muted-foreground">
                  <Calendar className="h-4 w-4" /> {r.nextRun}
                </div>
                <span className="text-xs rounded-full bg-muted px-2 py-1 capitalize">{r.status}</span>
              </div>
            </div>
          ))
        )}
      </div>
    </AnimatedCard>
  )
}


