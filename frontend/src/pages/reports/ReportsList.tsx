import FloatingLayout from '@/components/layout/FloatingLayout'
import { ReportsHeader, ReportsFilters, ReportsGrid } from './components'
import { Pagination } from '@/pages/customers/components/Pagination'
import { useReportsStore } from '@/stores/useReportsStore'
import { useReportsList } from './hooks'

export default function ReportsList() {
  const {
    navigate,
    customerId,
    customerName,
    isLoading,
    downloading,
    deleting,
    searchQuery,
    statusFilters,
    reportTypeFilters,
    filteredReports,
    uniqueReportTypes,
    setSearchQuery,
    setStatusFilters,
    setReportTypeFilters,
    handleDownload,
    handleDelete
  } = useReportsList()

  // Pagination from store
  const currentPage = useReportsStore(state => state.currentPage)
  const totalPages = useReportsStore(state => state.totalPages)
  const total = useReportsStore(state => state.total)
  const perPage = useReportsStore(state => state.perPage)
  const fetchReports = useReportsStore(state => state.fetchReports)

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <ReportsHeader
          customerId={customerId}
          customerName={customerName}
          onViewAll={() => navigate('/reports')}
        />

        <ReportsFilters
          searchQuery={searchQuery}
          statusFilters={statusFilters}
          reportTypeFilters={reportTypeFilters}
          uniqueReportTypes={uniqueReportTypes}
          onSearchChange={setSearchQuery}
          onStatusFiltersChange={setStatusFilters}
          onReportTypeFiltersChange={setReportTypeFilters}
        />

        <ReportsGrid
          reports={filteredReports}
          isLoading={isLoading}
          downloading={downloading}
          onDownload={handleDownload}
          onDelete={handleDelete}
          deleting={deleting}
        />

        {/* Pagination */}
        {!isLoading && total > perPage && (
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            total={total}
            onPageChange={(page) => fetchReports(customerId, page)}
            itemLabel="reports"
            perPage={perPage}
          />
        )}
      </div>
    </FloatingLayout>
  )
}
