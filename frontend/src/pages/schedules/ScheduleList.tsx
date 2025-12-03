import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { useSchedulesStore } from '@/stores/useSchedulesStore'
import { SchedulesHeader, SchedulesGrid } from './components'
import { SelectCustomerModal } from './modals/SelectCustomerModal'
import { Pagination } from '@/pages/customers/components/Pagination'

export default function ScheduleList() {
  const navigate = useNavigate()
  const toast = useToast()
  const [selectCustomerOpen, setSelectCustomerOpen] = useState(false)
  
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

  useEffect(() => {
    fetchSchedules().catch(err => {
      toast.error('Failed to load scheduled reports', err.response?.data?.message || err.message)
    })
  }, [fetchSchedules])

  const handleCreateSchedule = () => {
    setSelectCustomerOpen(true)
  }

  const handleConfigure = (customerId: number) => {
    navigate(`/platform/customers/${customerId}/configure-reports`)
  }

  return (
    <FloatingLayout>
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
        />
        
        <SchedulesGrid
          schedules={schedules}
          isLoading={isLoading}
          onConfigure={handleConfigure}
          onCreateSchedule={handleCreateSchedule}
        />

        {/* Pagination */}
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
    </FloatingLayout>
  )
}
