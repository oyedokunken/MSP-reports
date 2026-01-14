import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { AuditLogsHeader } from './components/AuditLogsHeader'
import { AuditLogsTable } from './components/AuditLogsTable'
import { AuditLogsFilters } from './components/AuditLogsFilters'
import { useAuditLogsStore, type AuditLogsState } from '../../stores/useAuditLogsStore'

export default function AuditLogs() {
  const [isRefreshing, setIsRefreshing] = useState(false)

  // Zustand store - individual selectors for optimal performance
  const auditLogs = useAuditLogsStore((state: AuditLogsState) => state.auditLogs)
  const isLoading = useAuditLogsStore((state: AuditLogsState) => state.isLoading)
  const error = useAuditLogsStore((state: AuditLogsState) => state.error)
  const currentPage = useAuditLogsStore((state: AuditLogsState) => state.currentPage)
  const totalPages = useAuditLogsStore((state: AuditLogsState) => state.totalPages)
  const totalLogs = useAuditLogsStore((state: AuditLogsState) => state.totalLogs)
  const filters = useAuditLogsStore((state: AuditLogsState) => state.filters)
  const fetchAuditLogs = useAuditLogsStore((state: AuditLogsState) => state.fetchAuditLogs)
  const setPage = useAuditLogsStore((state: AuditLogsState) => state.setPage)
  const setFilters = useAuditLogsStore((state: AuditLogsState) => state.setFilters)
  const exportLogs = useAuditLogsStore((state: AuditLogsState) => state.exportLogs)

  // Fetch data on mount
  useEffect(() => {
    fetchAuditLogs()
  }, [fetchAuditLogs])

  // Refetch when filters change
  useEffect(() => {
    fetchAuditLogs()
  }, [filters, fetchAuditLogs])

  const handleRefresh = async () => {
    setIsRefreshing(true)
    await fetchAuditLogs()
    setIsRefreshing(false)
  }

  const handleExport = async () => {
    await exportLogs()
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <AuditLogsHeader
          onRefresh={handleRefresh}
          onExport={handleExport}
          isRefreshing={isRefreshing || isLoading}
          totalLogs={totalLogs}
        />

        <AuditLogsFilters
          filters={filters}
          onFiltersChange={setFilters}
        />

        <AuditLogsTable
          logs={auditLogs}
          isLoading={isLoading}
          error={error}
          currentPage={currentPage}
          totalPages={totalPages}
          totalLogs={totalLogs}
          onPageChange={setPage}
        />
      </div>
    </FloatingLayout>
  )
}
