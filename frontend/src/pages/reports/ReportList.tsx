import FloatingLayout from '@/components/layout/FloatingLayout'
import { ReportsHeader } from './components/Header'
import { ReportsStats } from './components/Stats'
import { ReportsLayoutSwitch } from './components/LayoutSwitch'
import { ReportListItem } from './components/ReportListItem'
import { ReportGridItem } from './components/ReportGridItem'
import { useReportsState } from './hooks'
import type { ReportItem, Stat } from './types'

export default function ReportList() {
  const initialReports: ReportItem[] = [
    {
      id: 1,
      name: 'Monthly Service Summary',
      customer: 'ACME Corporation',
      schedule: 'Monthly - 1st at 9:00 AM',
      lastGenerated: '2 days ago',
      status: 'completed' as const,
      nextRun: 'in 28 days',
    },
    {
      id: 2,
      name: 'Weekly Uptime Report',
      customer: 'Tech Solutions Inc',
      schedule: 'Weekly - Monday at 8:00 AM',
      lastGenerated: '5 hours ago',
      status: 'completed' as const,
      nextRun: 'in 2 days',
    },
    {
      id: 3,
      name: 'Security Summary',
      customer: 'Global Services Ltd',
      schedule: 'Monthly - 15th at 10:00 AM',
      lastGenerated: 'Never',
      status: 'pending' as const,
      nextRun: 'in 12 days',
    },
    {
      id: 4,
      name: 'Ticket Performance',
      customer: 'Enterprise Systems',
      schedule: 'Weekly - Friday at 5:00 PM',
      lastGenerated: '2 days ago',
      status: 'failed' as const,
      nextRun: 'in 5 days',
    },
  ]

  const initialStats: Stat[] = [
    { label: 'Total Schedules', value: '35', color: 'text-blue-600' },
    { label: 'Active', value: '32', color: 'text-green-600' },
    { label: 'Generated (Month)', value: '156', color: 'text-purple-600' },
    { label: 'Success Rate', value: '99.2%', color: 'text-orange-600' },
  ] // ✅ FIXED: Added closing bracket and semicolon

  const { layoutMode, setLayoutMode, reports, stats } = useReportsState(initialReports, initialStats)

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <ReportsHeader onCreate={() => {}} />

        <ReportsStats stats={stats} />

        <div className="flex items-center justify-between border-b border-border pb-2">
          <nav className="flex gap-6">
            <button className="border-b-2 border-primary pb-3 text-sm font-medium text-primary">Schedules</button>
            <button className="border-b-2 border-transparent pb-3 text-sm font-medium text-muted-foreground hover:text-foreground">
              Generated Reports
            </button>
          </nav>
          <ReportsLayoutSwitch mode={layoutMode} onChange={setLayoutMode} />
        </div>

        {layoutMode === 'list' ? (
          <div className="space-y-4">
            {reports.map((report) => (
              <ReportListItem key={report.id} report={report} />
            ))}
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {reports.map((report) => (
              <ReportGridItem key={report.id} report={report} />
            ))}
          </div>
        )}

        {reports.length === 0 && (
          <div className="rounded-lg border-2 border-dashed border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-12 text-center">
            <span className="mx-auto block h-12 w-12 rounded-lg bg-muted" />
            <h3 className="mt-4 text-lg font-semibold text-foreground">No report schedules yet</h3>
            <p className="mt-2 text-sm text-muted-foreground">
              Get started by creating your first report schedule
            </p>
            <button className="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
              Create Schedule
            </button>
          </div>
        )}
      </div>
    </FloatingLayout>
  )
}
