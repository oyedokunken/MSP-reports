import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { ReportsHeader, ReportsFilters, ReportsGrid } from './components'
import { Pagination } from '@/pages/customers/components/Pagination'
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
    handleDelete,
    pagination,
    setPage
  } = useReportsList()

  const [reportToDelete, setReportToDelete] = useState<number | null>(null)

  const { current_page: currentPage, last_page: totalPages, total, per_page: perPage } = pagination

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
          onDelete={setReportToDelete}
          deleting={deleting}
        />

        {/* Pagination */}
        {!isLoading && total > perPage && (
          <Pagination
            currentPage={currentPage}
            totalPages={totalPages}
            total={total}
            onPageChange={setPage}
            itemLabel="reports"
            perPage={perPage}
          />
        )}
      </div>

      <ConfirmationModal
        isOpen={!!reportToDelete}
        onClose={() => setReportToDelete(null)}
        onConfirm={() => {
          if (reportToDelete) {
            handleDelete(reportToDelete)
            setReportToDelete(null)
          }
        }}
        title="Delete Report"
        description="Are you sure you want to delete this report? This action cannot be undone."
        confirmText="Delete Report"
        cancelText="Cancel"
        type="danger"
      />
    </FloatingLayout>
  )
}
