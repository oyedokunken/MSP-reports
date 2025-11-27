import { create } from 'zustand'
import { api } from '@/lib/api'

export interface IntegrationCustomer {
  integration_customer_id: number
  integration_type: string
  display_name: string
  external_id: string
  name: string
  active: boolean
  last_seen_at: string | null
}

export interface MappedCustomer {
  customer_id: number | null
  canonical_name: string | null
  status: string | null
  report_destination: string | null
  integrations: IntegrationCustomer[]
  report_schedules_count?: number
  has_reports_configured?: boolean
}

interface CustomersState {
  // Data
  customers: MappedCustomer[]
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
  
  // Modal state
  modals: {
    map: boolean
    edit: boolean
    integrations: boolean
    merge: boolean
    split: boolean
  }
  selectedUnmappedCustomer: IntegrationCustomer | null
  selectedCustomer: MappedCustomer | null
  
  // Actions
  fetchCustomers: (page?: number) => Promise<void>
  
  // Filter actions
  setSearchQuery: (query: string) => void
  setStatusFilter: (status: string) => void
  setReportFilter: (filter: string) => void
  
  // Modal actions
  openModal: (modal: keyof CustomersState['modals'], customer?: MappedCustomer) => void
  closeModal: (modal: keyof CustomersState['modals']) => void
  setSelectedUnmappedCustomer: (customer: IntegrationCustomer | null) => void
  setSelectedCustomer: (customer: MappedCustomer | null) => void
  
  // Computed
  getMappedCustomers: () => MappedCustomer[]
  getUnmappedCustomers: () => MappedCustomer[]
}

export const useCustomersStore = create<CustomersState>((set, get) => ({
  // Initial state
  customers: [],
  isLoading: false,
  searchQuery: '',
  statusFilter: 'all',
  reportFilter: 'all',
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
  fetchCustomers: async (page: number = 1) => {
    set({ isLoading: true })
    try {
      const { searchQuery, statusFilter, reportFilter } = get()
      
      const params = new URLSearchParams({ page: page.toString() })
      if (searchQuery) params.append('search', searchQuery)
      if (statusFilter !== 'all') params.append('status', statusFilter)
      if (reportFilter !== 'all') params.append('report_filter', reportFilter)
      
      const response = await api.get(`/customers?${params.toString()}`)
      const data = response as any
      const pagination = data.pagination || data
      const customerData = data.data || []
      
      set({
        customers: customerData,
        currentPage: pagination.current_page || page,
        totalPages: pagination.last_page || 1,
        perPage: pagination.per_page || get().perPage,
        total: pagination.total || customerData.length,
        isLoading: false,
      })
    } catch (err) {
      console.error('Failed to fetch customers:', err)
      set({ isLoading: false })
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

  // Computed
  getMappedCustomers: () => get().customers.filter(c => c.customer_id !== null),
  getUnmappedCustomers: () => get().customers.filter(c => c.customer_id === null),
}))
