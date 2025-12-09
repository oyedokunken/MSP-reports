import { create } from 'zustand'
import { customerApi } from '@/services/api/customers'
import type { Customer, UnmappedCustomer, IntegrationCustomer } from '@/types/api/customer'

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

  // Filters
  searchQuery: string
  statusFilter: string
  reportFilter: string
  integrationFilter: string

  // Modal state
  modals: {
    map: boolean
    edit: boolean
    integrations: boolean
    merge: boolean
    split: boolean
  }
  selectedUnmappedCustomer: IntegrationCustomer | null
  selectedCustomer: {
    customer_id: number
    name: string
    status: string
    integration_count: number
  } | null

  // Actions
  fetchCustomers: (page?: number, silent?: boolean) => Promise<void>
  fetchUnmappedCustomers: () => Promise<void>

  // Filter actions
  setSearchQuery: (query: string) => void
  setStatusFilter: (status: string) => void
  setReportFilter: (filter: string) => void
  setIntegrationFilter: (filter: string) => void

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
  statusFilter: 'all',
  reportFilter: 'all',
  integrationFilter: 'all',
  modals: {
    map: false,
    edit: false,
    integrations: false,
    merge: false,
    split: false,
  },
  selectedUnmappedCustomer: null,
  selectedCustomer: null,

  // Pagination state
  currentPage: 1,
  totalPages: 1,
  perPage: 21,
  total: 0,

  // Fetch customers
  fetchCustomers: async (page: number = 1, silent: boolean = false) => {
    if (!silent) set({ isLoading: true })
    try {
      const { searchQuery, statusFilter, reportFilter, integrationFilter } = get()

      const response = await customerApi.getCustomers({
        page,
        search: searchQuery || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        report_filter: reportFilter !== 'all' ? reportFilter : undefined,
        integration_type: integrationFilter !== 'all' ? integrationFilter : undefined,
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
      console.error('Failed to fetch customers:', err)
      set({ isLoading: false })
    }
  },

  // Fetch unmapped customers
  fetchUnmappedCustomers: async () => {
    try {
      const response = await customerApi.getUnmappedCustomers()

      set({
        unmappedCustomers: response.data,
        unmappedCount: response.summary.total_unmapped,
      })
    } catch (err) {
      console.error('Failed to fetch unmapped customers:', err)
      set({ unmappedCustomers: [], unmappedCount: 0 })
    }
  },

  // Filter actions
  setSearchQuery: (query: string) => {
    set({ searchQuery: query, currentPage: 1 })
    get().fetchCustomers(1)
  },
  setStatusFilter: (status: string) => {
    set({ statusFilter: status, currentPage: 1 })
    get().fetchCustomers(1)
  },
  setReportFilter: (filter: string) => {
    set({ reportFilter: filter, currentPage: 1 })
    get().fetchCustomers(1)
  },
  setIntegrationFilter: (filter: string) => {
    set({ integrationFilter: filter, currentPage: 1 })
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
        status: undefined,
        report_filter: undefined
      })
      set({ totalCustomersCount: response.pagination.total })
    } catch (err) {
      console.error('Failed to fetch total customers count:', err)
    }
  }
}))
