import { create } from 'zustand'
import { customerApi } from '@/services/api/customers'
import type { IntegrationCustomer } from '@/types/api/customer'

export type { IntegrationCustomer }

interface CustomersState {
  // Client State / UI State
  currentPage: number
  unmappedCurrentPage: number
  unmappedSearchQuery: string
  searchQuery: string
  statusFilters: string[]
  reportFilters: string[]
  integrationFilters: string[]

  modals: {
    map: boolean
    edit: boolean
    integrations: boolean
    merge: boolean
    split: boolean
    applyTemplate: boolean
    delivery: boolean
  }
  selectedUnmappedCustomer: IntegrationCustomer | null
  selectedCustomer: {
    customer_id: number
    name: string
    status: string
    integration_count: number
  } | null

  // Data State
  totalCustomersCount: number
  unmappedCount: number
  isLoading: boolean
  error: string | null

  // Actions
  setUnmappedSearchQuery: (query: string) => void
  setUnmappedPage: (page: number) => void
  setSearchQuery: (query: string) => void
  setCurrentPage: (page: number) => void
  setStatusFilters: (statuses: string[]) => void
  setReportFilters: (filters: string[]) => void
  setIntegrationFilters: (filters: string[]) => void
  clearAllFilters: () => void

  fetchTotalCustomersCount: () => Promise<void>
  fetchUnmappedCustomers: () => Promise<void>

  openModal: (modal: keyof CustomersState['modals'], customer?: CustomersState['selectedCustomer']) => void
  closeModal: (modal: keyof CustomersState['modals']) => void
  setSelectedUnmappedCustomer: (customer: IntegrationCustomer | null) => void
  setSelectedCustomer: (customer: CustomersState['selectedCustomer']) => void
}

export const useCustomersStore = create<CustomersState>((set, get) => ({
  currentPage: 1,
  unmappedCurrentPage: 1,
  unmappedSearchQuery: '',
  searchQuery: '',
  statusFilters: [],
  reportFilters: [],
  integrationFilters: [],
  modals: {
    map: false,
    edit: false,
    integrations: false,
    merge: false,
    split: false,
    applyTemplate: false,
    delivery: false,
  },
  selectedUnmappedCustomer: null,
  selectedCustomer: null,

  totalCustomersCount: 0,
  unmappedCount: 0,
  isLoading: false,
  error: null,

  setUnmappedSearchQuery: (query: string) => set({ unmappedSearchQuery: query, unmappedCurrentPage: 1 }),
  setUnmappedPage: (page: number) => set({ unmappedCurrentPage: page }),
  setSearchQuery: (query: string) => set({ searchQuery: query, currentPage: 1 }),
  setCurrentPage: (page: number) => set({ currentPage: page }),
  setStatusFilters: (statuses: string[]) => set({ statusFilters: statuses, currentPage: 1 }),
  setReportFilters: (filters: string[]) => set({ reportFilters: filters, currentPage: 1 }),
  setIntegrationFilters: (filters: string[]) => set({ integrationFilters: filters, currentPage: 1 }),
  clearAllFilters: () => set({ statusFilters: [], reportFilters: [], integrationFilters: [], searchQuery: '', currentPage: 1 }),

  fetchTotalCustomersCount: async () => {
    try {
      const response = await customerApi.getCustomers({
        search: get().searchQuery,
        statuses: get().statusFilters,
        report_filters: get().reportFilters,
        integration_types: get().integrationFilters,
        per_page: 1
      })
      set({ totalCustomersCount: response.pagination.total })
    } catch (error) {
      console.error('Failed to fetch total customers count:', error)
    }
  },

  fetchUnmappedCustomers: async () => {
    try {
      const response = await customerApi.getUnmappedCustomers(
        get().unmappedCurrentPage,
        get().unmappedSearchQuery,
        true, // skip suggestions for performance
        1 // per_page=1 for performance
      )
      set({ unmappedCount: response.pagination.total })
    } catch (error) {
      console.error('Failed to fetch unmapped customers count:', error)
    }
  },

  openModal: (modal, customer) => {
    set(state => ({
      modals: { ...state.modals, [modal]: true },
      selectedCustomer: customer ?? state.selectedCustomer,
    }))
  },
  closeModal: modal => {
    set(state => ({
      modals: { ...state.modals, [modal]: false },
    }))
  },
  setSelectedUnmappedCustomer: customer => set({ selectedUnmappedCustomer: customer }),
  setSelectedCustomer: customer => set({ selectedCustomer: customer }),
}))
