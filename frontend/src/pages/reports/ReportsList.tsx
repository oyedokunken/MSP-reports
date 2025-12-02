import FloatingLayout from '@/components/layout/FloatingLayout'
import { ReportsHeader, ReportsFilters, ReportsGrid } from './components'
import { useReportsList } from './hooks'

export default function ReportsList() {
  const {
    navigate,
    customerId,
    customerName,
    isLoading,
    downloading,
    searchQuery,
    statusFilter,
    reportTypeFilter,
    filteredReports,
    uniqueReportTypes,
    setSearchQuery,
    setStatusFilter,
    setReportTypeFilter,
    handleDownload
  } = useReportsList()

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
          statusFilter={statusFilter}
          reportTypeFilter={reportTypeFilter}
          uniqueReportTypes={uniqueReportTypes}
          onSearchChange={setSearchQuery}
          onStatusChange={setStatusFilter}
          onReportTypeChange={setReportTypeFilter}
        />

        <ReportsGrid
          reports={filteredReports}
          isLoading={isLoading}
          downloading={downloading}
          onDownload={handleDownload}
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
