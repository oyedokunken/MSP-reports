import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { FileText, Cable, Users, Activity } from 'lucide-react'
import { getIntegrationStatusBadge, getIntegrationStatusIcon, getIntegrationStatusText } from './utils'
import { useQuickSync } from './hooks'
import type { Integration, UpcomingReport, RecentActivity, Stat } from './types'
import { DashboardHeaderActions } from './components/DashboardHeaderActions'
import { RecentActivityCard } from './components/RecentActivityCard'
import { QuickActionsCard } from './components/QuickActionsCard'
import { IntegrationsSummary } from './components/IntegrationsSummary'
import { UpcomingReports } from './components/UpcomingReports'
import { StatsGrid } from './components/StatsGrid'

export default function Dashboard() {
  const toast = useToast()
  const [isLoading, setIsLoading] = useState(true)
  

  useEffect(() => {
    const t = setTimeout(() => setIsLoading(false), 1200)
    return () => clearTimeout(t)
  }, [])

  // Mock data (replace with API calls later)
  const integrations: Integration[] = [
    { id: 1, name: 'Autotask PSA', type: 'autotask', status: 'active' as const, lastSync: '2h ago', customers: 24, syncStatus: 'success' as const },
    { id: 2, name: 'Datto RMM', type: 'datto', status: 'active' as const, lastSync: '1h ago', customers: 18, syncStatus: 'success' as const },
    { id: 3, name: 'ConnectWise Manage', type: 'connectwise', status: 'error' as const, lastSync: '5h ago', customers: 0, syncStatus: 'failed' as const },
  ]

  const reports: UpcomingReport[] = [
    { id: 1, name: 'Monthly Service Summary', customer: 'ACME Corporation', nextRun: 'Nov 1, 9:00 AM', status: 'scheduled' as const },
    { id: 2, name: 'Weekly Uptime Report', customer: 'Tech Solutions Inc', nextRun: 'Mon, 8:00 AM', status: 'scheduled' as const },
    { id: 3, name: 'Security Summary', customer: 'Global Services Ltd', nextRun: 'Oct 15, 10:00 AM', status: 'pending' as const },
  ]

  const recentActivity: RecentActivity[] = [
    { id: 1, type: 'sync', title: 'Datto RMM sync completed', time: '10m ago', severity: 'green' as const },
    { id: 2, type: 'report', title: 'Monthly Service Summary generated for ACME', time: '2h ago', severity: 'green' as const },
    { id: 3, type: 'alert', title: 'ConnectWise Manage failed to sync', time: '5h ago', severity: 'red' as const },
    { id: 4, type: 'map', title: '2 customers awaiting mapping review', time: '1d ago', severity: 'yellow' as const },
  ]

  const stats: Stat[] = [
    { label: 'Customers', value: '42', icon: Users, trend: { value: '+6 this month', isPositive: true } },
    { label: 'Integrations', value: '3', icon: Cable, trend: { value: '1 warning', isPositive: false } },
    { label: 'Reports (30d)', value: '156', icon: FileText, trend: { value: '99.2% success', isPositive: true } },
    { label: 'Active Syncs', value: '2', icon: Activity, trend: { value: 'Low queue', isPositive: true } },
  ]

  const { syncingId, progress, handleQuickSync } = useQuickSync(toast.success, toast.error)

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Dashboard</h1>
            <p className="mt-2 text-muted-foreground">Overview of your environment, reports and integrations</p>
          </div>
          <DashboardHeaderActions onToastSuccess={toast.success} onToastInfo={toast.info} />
        </div>

        <StatsGrid isLoading={isLoading} stats={stats} />

        <div className="grid gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <RecentActivityCard isLoading={isLoading} items={recentActivity} />
            <QuickActionsCard onInfo={toast.info} />
          </div>
          <div className="space-y-6">
            <IntegrationsSummary
              isLoading={isLoading}
              integrations={integrations}
              isSyncingId={syncingId}
              syncProgress={progress}
              onSync={handleQuickSync}
              onInfo={toast.info}
              getStatusBadge={getIntegrationStatusBadge}
              getStatusText={getIntegrationStatusText}
              getStatusIcon={getIntegrationStatusIcon}
            />
            <UpcomingReports isLoading={isLoading} reports={reports} onInfo={toast.info} />
          </div>
        </div>
      </div>
    </FloatingLayout>
  )
}

