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
  allSchedules: ReportSchedule[]
  isLoading: boolean
  
  // Search & Pagination
  searchQuery: string
  currentPage: number
  perPage: number
  
  // Actions
  fetchSchedules: () => Promise<void>
  setPage: (page: number) => void
  setSearchQuery: (query: string) => void
  getFilteredSchedules: () => ReportSchedule[]
}

export const useSchedulesStore = create<SchedulesState>((set, get) => ({
  // Initial state
  schedules: [],
  allSchedules: [],
  isLoading: true,
  searchQuery: '',
  currentPage: 1,
  perPage: 9,

  // Fetch schedules
  fetchSchedules: async () => {
    set({ isLoading: true })
    try {
      const response = await api.get<ReportSchedule[]>('/reports/scheduled')
      const data = (response as any).data || response
      const schedulesList = Array.isArray(data) ? data : []
      
      const { currentPage, perPage } = get()
      const startIndex = (currentPage - 1) * perPage
      const endIndex = startIndex + perPage
      const paginatedSchedules = schedulesList.slice(startIndex, endIndex)
      
      set({
        schedules: paginatedSchedules,
        allSchedules: schedulesList,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch schedules:', err)
      set({ isLoading: false })
      throw err
    }
  },

  // Pagination action
  setPage: (page: number) => {
    const { perPage } = get()
    const filtered = get().getFilteredSchedules()
    const startIndex = (page - 1) * perPage
    const endIndex = startIndex + perPage
    const paginatedSchedules = filtered.slice(startIndex, endIndex)
    
    set({ 
      currentPage: page,
      schedules: paginatedSchedules
    })
  },

  // Search action
  setSearchQuery: (query: string) => {
    set({ searchQuery: query, currentPage: 1 })
    const { perPage } = get()
    const filtered = get().getFilteredSchedules()
    const paginatedSchedules = filtered.slice(0, perPage)
    set({ schedules: paginatedSchedules })
  },

  // Get filtered schedules based on search
  getFilteredSchedules: () => {
    const { allSchedules, searchQuery } = get()
    if (!searchQuery.trim()) return allSchedules
    
    const query = searchQuery.toLowerCase()
    return allSchedules.filter(schedule => 
      schedule.customer_name.toLowerCase().includes(query) ||
      schedule.report_type.toLowerCase().includes(query) ||
      schedule.frequency.toLowerCase().includes(query)
    )
  },
}))
