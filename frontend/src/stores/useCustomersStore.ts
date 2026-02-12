import { create } from 'zustand'
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

  // Actions
  setUnmappedSearchQuery: (query: string) => void
  setUnmappedPage: (page: number) => void
  setSearchQuery: (query: string) => void
  setCurrentPage: (page: number) => void
  setStatusFilters: (statuses: string[]) => void
  setReportFilters: (filters: string[]) => void
  setIntegrationFilters: (filters: string[]) => void
  clearAllFilters: () => void

  openModal: (modal: keyof CustomersState['modals'], customer?: CustomersState['selectedCustomer']) => void
  closeModal: (modal: keyof CustomersState['modals']) => void
  setSelectedUnmappedCustomer: (customer: IntegrationCustomer | null) => void
  setSelectedCustomer: (customer: CustomersState['selectedCustomer']) => void
}

export const useCustomersStore = create<CustomersState>((set) => ({
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

  setUnmappedSearchQuery: (query: string) => set({ unmappedSearchQuery: query, unmappedCurrentPage: 1 }),
  setUnmappedPage: (page: number) => set({ unmappedCurrentPage: page }),
  setSearchQuery: (query: string) => set({ searchQuery: query, currentPage: 1 }),
  setCurrentPage: (page: number) => set({ currentPage: page }),
  setStatusFilters: (statuses: string[]) => set({ statusFilters: statuses, currentPage: 1 }),
  setReportFilters: (filters: string[]) => set({ reportFilters: filters, currentPage: 1 }),
  setIntegrationFilters: (filters: string[]) => set({ integrationFilters: filters, currentPage: 1 }),
  clearAllFilters: () => set({ statusFilters: [], reportFilters: [], integrationFilters: [], searchQuery: '', currentPage: 1 }),

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
