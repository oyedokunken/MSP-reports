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
  const allSchedules = useSchedulesStore(state => state.allSchedules)
  const isLoading = useSchedulesStore(state => state.isLoading)
  const currentPage = useSchedulesStore(state => state.currentPage)
  const perPage = useSchedulesStore(state => state.perPage)
  const fetchSchedules = useSchedulesStore(state => state.fetchSchedules)
  const setPage = useSchedulesStore(state => state.setPage)

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
        <SchedulesHeader onCreateSchedule={handleCreateSchedule} />
        
        <SchedulesGrid
          schedules={schedules}
          isLoading={isLoading}
          onConfigure={handleConfigure}
          onCreateSchedule={handleCreateSchedule}
        />

        {/* Pagination */}
        {!isLoading && allSchedules.length > perPage && (
          <Pagination
            currentPage={currentPage}
            totalPages={Math.ceil(allSchedules.length / perPage)}
            total={allSchedules.length}
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
