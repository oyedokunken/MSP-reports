import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { useSchedulesStore } from '@/stores/useSchedulesStore'
import { SchedulesHeader, SchedulesGrid } from './components'

export default function ScheduleList() {
  const navigate = useNavigate()
  const toast = useToast()
  
  const schedules = useSchedulesStore(state => state.schedules)
  const isLoading = useSchedulesStore(state => state.isLoading)
  const fetchSchedules = useSchedulesStore(state => state.fetchSchedules)

  useEffect(() => {
    fetchSchedules().catch(err => {
      toast.error('Failed to load scheduled reports', err.response?.data?.message || err.message)
    })
  }, [fetchSchedules])

  const handleCreateSchedule = () => {
    navigate('/platform/customers')
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
      </div>
    </FloatingLayout>
  )
}
