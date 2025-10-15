import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import type { Integration } from './types'
import { useIntegrationSync } from './hooks'
import { IntegrationHeader } from './components/IntegrationHeader'
import { IntegrationGrid } from './components/IntegrationGrid'

export default function IntegrationList() {
  const toast = useToast()
  const { syncingId, progress, handleSync } = useIntegrationSync(toast.success, toast.error)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const timer = setTimeout(() => setIsLoading(false), 1200)
    return () => clearTimeout(timer)
  }, [])

  // handleSync provided by hook

  // Mock data - will be replaced with real API calls
  const integrations: Integration[] = [
    {
      id: 1,
      name: 'Autotask PSA',
      type: 'autotask',
      status: 'active' as const,
      lastSync: '2 hours ago',
      syncStatus: 'success' as const,
      customers: 24,
    },
    {
      id: 2,
      name: 'Datto RMM',
      type: 'datto',
      status: 'active' as const,
      lastSync: '1 hour ago',
      syncStatus: 'success' as const,
      customers: 18,
    },
    {
      id: 3,
      name: 'ConnectWise Manage',
      type: 'connectwise',
      status: 'error' as const,
      lastSync: '5 hours ago',
      syncStatus: 'failed' as const,
      customers: 0,
    },
  ]

  // status helpers moved to utils

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <IntegrationHeader onAdd={() => toast.info('Coming Soon', 'Integration setup wizard')} />
        <IntegrationGrid
          isLoading={isLoading}
          items={integrations}
          syncingId={syncingId}
          progress={progress}
          onSync={handleSync}
          onEmptyAction={() => toast.info('Coming Soon', 'Integration setup wizard')}
        />
      </div>
    </FloatingLayout>
  )
}
