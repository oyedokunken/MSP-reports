import { useEffect, useState } from 'react'
import {
  AuditLogsHeader,
  AuditLogsTable,
  AuditLogsControls,
  Pagination
} from './components'
import { useAuditLogsStore, type AuditLogsState } from '../../stores/useAuditLogsStore'

export default function AuditLogs() {
  const [isRefreshing, setIsRefreshing] = useState(false)

  const auditLogs = useAuditLogsStore((state: AuditLogsState) => state.auditLogs)
  const isLoading = useAuditLogsStore((state: AuditLogsState) => state.isLoading)
  const error = useAuditLogsStore((state: AuditLogsState) => state.error)
  const currentPage = useAuditLogsStore((state: AuditLogsState) => state.currentPage)
  const totalPages = useAuditLogsStore((state: AuditLogsState) => state.totalPages)
  const totalLogs = useAuditLogsStore((state: AuditLogsState) => state.totalLogs)
  const filters = useAuditLogsStore((state: AuditLogsState) => state.filters)
  const stats = useAuditLogsStore((state: AuditLogsState) => state.stats)
  const fetchAuditLogs = useAuditLogsStore((state: AuditLogsState) => state.fetchAuditLogs)
  const fetchStats = useAuditLogsStore((state: AuditLogsState) => state.fetchStats)
  const setPage = useAuditLogsStore((state: AuditLogsState) => state.setPage)
  const setFilters = useAuditLogsStore((state: AuditLogsState) => state.setFilters)
  const exportLogs = useAuditLogsStore((state: AuditLogsState) => state.exportLogs)

  useEffect(() => {
    fetchAuditLogs()
    fetchStats()
  }, [fetchAuditLogs, fetchStats])

  const handleRefresh = async () => {
    setIsRefreshing(true)
    await Promise.all([fetchAuditLogs(), fetchStats()])
    setIsRefreshing(false)
  }

  const handleExport = async () => {
    await exportLogs()
  }

  return (
    <div className="space-y-6">
        <AuditLogsHeader
          onRefresh={handleRefresh}
          isRefreshing={isRefreshing || isLoading}
          totalLogs={totalLogs}
          stats={stats}
        />

        <div className="flex flex-col gap-6">
          <AuditLogsControls
            filters={filters}
            onFiltersChange={setFilters}
            onExport={handleExport}
          />

          <AuditLogsTable
            logs={auditLogs}
            isLoading={isLoading}
            error={error}
          />

          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            total={totalLogs}
            onPageChange={setPage}
            itemLabel="events"
          />
        </div>
      </div>
    )
}
