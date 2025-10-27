import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useMonitoringStore } from '@/stores/useMonitoringStore'
import {
  MonitoringHeader,
  SystemStatusCard,
  MetricsCards,
} from './components'

export default function Monitoring() {
  const [isRefreshing, setIsRefreshing] = useState(false)
  
  // Zustand store - individual selectors for optimal performance
  const systemStatus = useMonitoringStore(state => state.systemStatus)
  const integrationMetrics = useMonitoringStore(state => state.integrationMetrics)
  const reportMetrics = useMonitoringStore(state => state.reportMetrics)
  const queueMetrics = useMonitoringStore(state => state.queueMetrics)
  const isLoading = useMonitoringStore(state => state.isLoading)
  const lastUpdated = useMonitoringStore(state => state.lastUpdated)
  const fetchAllMetrics = useMonitoringStore(state => state.fetchAllMetrics)

  // Fetch data on mount
  useEffect(() => {
    fetchAllMetrics()
  }, [fetchAllMetrics])

  // Auto-refresh every 30 seconds
  useEffect(() => {
    const interval = setInterval(() => {
      fetchAllMetrics()
    }, 30000)

    return () => clearInterval(interval)
  }, [fetchAllMetrics])

  const handleRefresh = async () => {
    setIsRefreshing(true)
    await fetchAllMetrics()
    setIsRefreshing(false)
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <MonitoringHeader
          lastUpdated={lastUpdated}
          onRefresh={handleRefresh}
          isRefreshing={isRefreshing || isLoading}
        />

        <SystemStatusCard status={systemStatus} />

        <MetricsCards
          integrationMetrics={integrationMetrics}
          reportMetrics={reportMetrics}
          queueMetrics={queueMetrics}
        />
      </div>
    </FloatingLayout>
  )
}
