import { useEffect } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { useReportsStore } from '@/stores/useReportsStore'
import { ReportsHeader, ReportsFilters, ReportsGrid } from './components'
import { Pagination } from '@/pages/customers/components/Pagination'

export default function ReportsList() {
  const toast = useToast()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const customerId = searchParams.get('customer')
  
  // Zustand store - individual selectors for optimal performance
  const isLoading = useReportsStore(state => state.isLoading)
  const customerName = useReportsStore(state => state.customerName)
  const downloading = useReportsStore(state => state.downloading)
  const searchQuery = useReportsStore(state => state.searchQuery)
  const statusFilter = useReportsStore(state => state.statusFilter)
  const reportTypeFilter = useReportsStore(state => state.reportTypeFilter)
  const currentPage = useReportsStore(state => state.currentPage)
  const totalPages = useReportsStore(state => state.totalPages)
  const total = useReportsStore(state => state.total)
  const perPage = useReportsStore(state => state.perPage)
  const fetchReports = useReportsStore(state => state.fetchReports)
  const downloadReport = useReportsStore(state => state.downloadReport)
  const setSearchQuery = useReportsStore(state => state.setSearchQuery)
  const setStatusFilter = useReportsStore(state => state.setStatusFilter)
  const setReportTypeFilter = useReportsStore(state => state.setReportTypeFilter)
  const getFilteredReports = useReportsStore(state => state.getFilteredReports)
  const getUniqueReportTypes = useReportsStore(state => state.getUniqueReportTypes)

  // Fetch reports on mount or when customerId changes
  useEffect(() => {
    fetchReports(customerId, 1).catch(err => {
      toast.error('Failed to load report history', err.response?.data?.message || err.message)
    })
  }, [customerId, fetchReports])

  // Get filtered data
  const filteredReports = getFilteredReports()
  const uniqueReportTypes = getUniqueReportTypes()

  // Handler functions
  const handleDownload = async (reportId: number) => {
    const result = await downloadReport(reportId)
    if (result.success) {
      toast.success('Download Started', 'PDF report is downloading...')
    } else {
      toast.error('Download Failed', result.error)
    }
  }

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
