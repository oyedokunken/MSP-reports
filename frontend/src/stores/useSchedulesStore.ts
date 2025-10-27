import { create } from 'zustand'
import { api } from '@/lib/api'

export interface ReportSchedule {
  report_schedule_id: number
  customer_id: number
  customer_name: string
  report_type: string
  frequency: string
  time_of_day: string | null
  next_scheduled_at: string | null
  last_generated_at: string | null
  active: boolean
}

interface SchedulesState {
  // Data
  schedules: ReportSchedule[]
  isLoading: boolean
  
  // Actions
  fetchSchedules: () => Promise<void>
}

export const useSchedulesStore = create<SchedulesState>((set) => ({
  // Initial state
  schedules: [],
  isLoading: true,

  // Fetch schedules
  fetchSchedules: async () => {
    set({ isLoading: true })
    try {
      const response = await api.get<ReportSchedule[]>('/reports/scheduled')
      const data = (response as any).data || response
      const schedulesList = Array.isArray(data) ? data : []
      
      set({
        schedules: schedulesList,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch schedules:', err)
      set({ isLoading: false })
      throw err
    }
  },
}))
