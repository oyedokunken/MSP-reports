import { create } from 'zustand'
import { customerApi } from '@/services/api/customers'
import type { Customer, UnmappedCustomer, IntegrationCustomer } from '@/types/api/customer'
import { logger } from '@/lib/logger'

// Re-export for backward compatibility
export type { IntegrationCustomer, UnmappedCustomer }
export type MappedCustomer = Customer

interface CustomersState {
  // Data
  customers: MappedCustomer[]
  unmappedCustomers: UnmappedCustomer[]
  unmappedCount: number
  isLoading: boolean

  // Pagination
  currentPage: number
  totalPages: number
  perPage: number
  total: number

  // Unmapped pagination
  unmappedCurrentPage: number
  unmappedTotalPages: number
  unmappedPerPage: number
  unmappedSearchQuery: string

  // Filters
  searchQuery: string
  statusFilters: string[]
  reportFilters: string[]
  integrationFilters: string[]

  // Modal state
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
  fetchCustomers: (page?: number, silent?: boolean, sourceOfTruthOnly?: boolean | null) => Promise<void>
  fetchUnmappedCustomers: (page?: number, search?: string) => Promise<void>
  setUnmappedSearchQuery: (query: string) => void

  // Filter actions
  setSearchQuery: (query: string) => void
  setStatusFilters: (statuses: string[]) => void
  setReportFilters: (filters: string[]) => void
  setIntegrationFilters: (filters: string[]) => void
  clearAllFilters: () => void

  // Modal actions
  openModal: (modal: keyof CustomersState['modals'], customer?: CustomersState['selectedCustomer']) => void
  closeModal: (modal: keyof CustomersState['modals']) => void
  setSelectedUnmappedCustomer: (customer: IntegrationCustomer | null) => void
  setSelectedCustomer: (customer: CustomersState['selectedCustomer']) => void
  updateCustomer: (customerId: number, updates: Partial<MappedCustomer>) => void

  // Computed
  getMappedCustomers: () => MappedCustomer[]
  getUnmappedCustomers: () => UnmappedCustomer[]

  // Dashboard specific
  totalCustomersCount: number
  fetchTotalCustomersCount: () => Promise<void>
}

export const useCustomersStore = create<CustomersState>((set, get) => ({
  // Initial state
  customers: [],
  unmappedCustomers: [],
  unmappedCount: 0,
  isLoading: false,
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

  // Pagination state
  currentPage: 1,
  totalPages: 1,
  perPage: 21,
  total: 0,

  // Unmapped pagination state
  unmappedCurrentPage: 1,
  unmappedTotalPages: 1,
  unmappedPerPage: 21,
  unmappedSearchQuery: '',

  // Fetch customers
  fetchCustomers: async (page: number = 1, silent: boolean = false, sourceOfTruthOnly: boolean | null = null) => {
    if (!silent) set({ isLoading: true })
    try {
      const { searchQuery, statusFilters, reportFilters, integrationFilters } = get()

      const response = await customerApi.getCustomers({
        page,
        search: searchQuery || undefined,
        statuses: statusFilters.length > 0 ? statusFilters : undefined,
        report_filters: reportFilters.length > 0 ? reportFilters : undefined,
        integration_types: integrationFilters.length > 0 ? integrationFilters : undefined,
        source_of_truth_only: sourceOfTruthOnly,
      })

      set({
        customers: response.data,
        currentPage: response.pagination.current_page,
        totalPages: response.pagination.last_page,
        perPage: response.pagination.per_page,
        total: response.pagination.total,
        isLoading: false,
      })

      // Also fetch unmapped customers count
      await get().fetchUnmappedCustomers()
    } catch (err) {
      logger.error('Failed to fetch customers', err)
      set({ isLoading: false })
    }
  },

  // Fetch unmapped customers
  fetchUnmappedCustomers: async (page: number = 1, search?: string) => {
    const s = search !== undefined ? search : get().unmappedSearchQuery
    try {
      const response = await customerApi.getUnmappedCustomers(page, s)

      set({
        unmappedCustomers: response.data,
        unmappedCount: response.summary?.total_unmapped || response.data.length,
        unmappedCurrentPage: response.pagination?.current_page || page,
        unmappedTotalPages: response.pagination?.last_page || 1,
        unmappedPerPage: response.pagination?.per_page || response.data.length,
      })
    } catch (err) {
      logger.error('Failed to fetch unmapped customers', err)
      set({ unmappedCustomers: [], unmappedCount: 0, unmappedCurrentPage: 1, unmappedTotalPages: 1 })
    }
  },

  setUnmappedSearchQuery: (query: string) => {
    set({ unmappedSearchQuery: query, unmappedCurrentPage: 1 })
    get().fetchUnmappedCustomers(1, query)
  },

  // Filter actions
  setSearchQuery: (query: string) => {
    set({ searchQuery: query, currentPage: 1 })
    get().fetchCustomers(1)
  },
  setStatusFilters: (statuses: string[]) => {
    set({ statusFilters: statuses, currentPage: 1 })
    get().fetchCustomers(1)
  },
  setReportFilters: (filters: string[]) => {
    set({ reportFilters: filters, currentPage: 1 })
    get().fetchCustomers(1)
  },
  setIntegrationFilters: (filters: string[]) => {
    set({ integrationFilters: filters, currentPage: 1 })
    get().fetchCustomers(1)
  },
  clearAllFilters: () => {
    set({ statusFilters: [], reportFilters: [], integrationFilters: [], searchQuery: '', currentPage: 1 })
    get().fetchCustomers(1)
  },

  // Modal actions
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
  updateCustomer: (customerId: number, updates: Partial<MappedCustomer>) => {
    set(state => ({
      customers: state.customers.map(c =>
        c.customer_id === customerId ? { ...c, ...updates } : c
      )
    }))
  },

  // Computed
  getMappedCustomers: () => get().customers.filter(c => c.customer_id !== null),
  getUnmappedCustomers: () => get().unmappedCustomers,

  // Dashboard specific
  totalCustomersCount: 0,
  fetchTotalCustomersCount: async () => {
    try {
      // Fetch with no filters and minimal page size just to get the total from metadata
      const response = await customerApi.getCustomers({
        page: 1,
        search: undefined,
        statuses: undefined,
        report_filters: undefined,
        integration_types: undefined
      })
      set({ totalCustomersCount: response.pagination.total })
    } catch (err) {
      logger.error('Failed to fetch total customers count', err)
    }
  }
}))
