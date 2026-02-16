import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useSchedulesStore } from '@/stores/useSchedulesStore'
import { SchedulesHeader, SchedulesTable, SchedulesFolders, type ViewMode } from './components'
import { SelectCustomerModal } from './modals/SelectCustomerModal'
import { DeleteScheduleModal } from './modals/DeleteScheduleModal'
import { Pagination } from '@/pages/customers/components/Pagination'
import type { ReportSchedule } from './types/schedule'

export default function ScheduleList() {
  const navigate = useNavigate()
  const toast = useToast()
  const [selectCustomerOpen, setSelectCustomerOpen] = useState(false)
  const [deleteModalOpen, setDeleteModalOpen] = useState(false)
  const [scheduleToDelete, setScheduleToDelete] = useState<ReportSchedule | null>(null)
  const [viewMode, setViewMode] = useState<ViewMode>('folders')

  const schedules = useSchedulesStore(state => state.schedules)
  const isLoading = useSchedulesStore(state => state.isLoading)
  const currentPage = useSchedulesStore(state => state.currentPage)
  const perPage = useSchedulesStore(state => state.perPage)
  const searchQuery = useSchedulesStore(state => state.searchQuery)
  const statusFilter = useSchedulesStore(state => state.statusFilter)
  const frequencyFilter = useSchedulesStore(state => state.frequencyFilter)
  const fetchSchedules = useSchedulesStore(state => state.fetchSchedules)
  const setPage = useSchedulesStore(state => state.setPage)
  const setSearchQuery = useSchedulesStore(state => state.setSearchQuery)
  const setStatusFilter = useSchedulesStore(state => state.setStatusFilter)
  const setFrequencyFilter = useSchedulesStore(state => state.setFrequencyFilter)
  const getFilteredSchedules = useSchedulesStore(state => state.getFilteredSchedules)
  const getUniqueFrequencies = useSchedulesStore(state => state.getUniqueFrequencies)
  const deleteSchedule = useSchedulesStore(state => state.deleteSchedule)

  useEffect(() => {
    fetchSchedules().catch(err => {
      toast.error('Failed to load scheduled reports', err.response?.data?.message || err.message)
    })
  }, [fetchSchedules, toast])

  const handleCreateSchedule = () => {
    setSelectCustomerOpen(true)
  }

  const handleConfigure = (customerId: number) => {
    navigate(`/platform/customers/${customerId}/configure-reports`)
  }

  const handleDeleteClick = (scheduleId: number) => {
    const schedule = schedules.find(s => s.report_schedule_id === scheduleId)
    if (schedule) {
      setScheduleToDelete(schedule)
      setDeleteModalOpen(true)
    }
  }

  const handleDeleteConfirm = async () => {
    if (!scheduleToDelete) return
    
    await deleteSchedule(scheduleToDelete.report_schedule_id)
  }

  return (
    <>
      <div className="space-y-6">
        <SchedulesHeader
          onCreateSchedule={handleCreateSchedule}
          searchQuery={searchQuery}
          statusFilter={statusFilter}
          frequencyFilter={frequencyFilter}
          uniqueFrequencies={getUniqueFrequencies()}
          onSearchChange={setSearchQuery}
          onStatusChange={setStatusFilter}
          onFrequencyChange={setFrequencyFilter}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
        />

        {viewMode === 'table' && (
          <SchedulesTable
            schedules={schedules}
            isLoading={isLoading}
            onConfigure={handleConfigure}
            onDelete={handleDeleteClick}
            onCreateSchedule={handleCreateSchedule}
          />
        )}

        {viewMode === 'folders' && (
          <SchedulesFolders
            schedules={schedules}
            isLoading={isLoading}
            onConfigure={handleConfigure}
            onDelete={handleDeleteClick}
            onCreateSchedule={handleCreateSchedule}
            searchQuery={searchQuery}
          />
        )}


        {!isLoading && getFilteredSchedules().length > perPage && (
          <Pagination
            currentPage={currentPage}
            totalPages={Math.ceil(getFilteredSchedules().length / perPage)}
            total={getFilteredSchedules().length}
            onPageChange={setPage}
            itemLabel="schedules"
            perPage={perPage}
          />
        )}
      </div>

      <SelectCustomerModal
        open={selectCustomerOpen}
        onOpenChange={setSelectCustomerOpen}
      />

      <DeleteScheduleModal
        open={deleteModalOpen}
        onOpenChange={setDeleteModalOpen}
        schedule={scheduleToDelete}
        onConfirm={handleDeleteConfirm}
      />
    </>
  )
}
