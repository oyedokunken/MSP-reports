import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { ReportsHeader, ReportsFilters, ReportsGrid, ReportsTable, BulkGenerateModal, type ViewMode } from './components'
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
    handleBatchDelete,
    selectedReportIds,
    toggleReportSelection,
    pagination,
    setPage,
    isBulkDeleting
  } = useReportsList()

  const [reportToDelete, setReportToDelete] = useState<number | null>(null)
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false)
  const [showBulkGenerateModal, setShowBulkGenerateModal] = useState(false)
  const [viewMode, setViewMode] = useState<ViewMode>('grid')

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
          onBulkDelete={() => setShowBulkDeleteConfirm(true)}
          onBulkGenerate={() => setShowBulkGenerateModal(true)}
          customerId={customerId}
          selectedCount={selectedReportIds.length}
          isDeleting={isBulkDeleting}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
        />

        {viewMode === 'grid' ? (
          <ReportsGrid
            reports={filteredReports}
            isLoading={isLoading}
            downloading={downloading}
            onDownload={handleDownload}
            onDelete={setReportToDelete}
            deleting={deleting}
            selectedIds={selectedReportIds}
            onToggleSelection={toggleReportSelection}
          />
        ) : (
          <ReportsTable
            reports={filteredReports}
            isLoading={isLoading}
            downloading={downloading}
            onDownload={handleDownload}
            onDelete={setReportToDelete}
            deleting={deleting}
            selectedIds={selectedReportIds}
            onToggleSelection={toggleReportSelection}
          />
        )}

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

      {/* Individual Delete Confirmation */}
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

      {/* Bulk Delete Confirmation */}
      <ConfirmationModal
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={() => {
          handleBatchDelete()
          setShowBulkDeleteConfirm(false)
        }}
        title="Bulk Delete Reports"
        description={`Are you sure you want to delete ${selectedReportIds.length} selected reports? This action cannot be undone.`}
        confirmText={`Delete ${selectedReportIds.length} Reports`}
        cancelText="Cancel"
        type="danger"
      />

      {/* Bulk Generate Modal */}
      <BulkGenerateModal
        open={showBulkGenerateModal}
        onOpenChange={setShowBulkGenerateModal}
      />
    </FloatingLayout>
  )
}

