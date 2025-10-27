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
  selectedCustomer: any | null
  
  // Actions
  fetchCustomers: () => Promise<void>
  
  // Filter actions
  setSearchQuery: (query: string) => void
  setStatusFilter: (status: string) => void
  setReportFilter: (filter: string) => void
  
  // Modal actions
  openModal: (modal: keyof CustomersState['modals'], customer?: any) => void
  closeModal: (modal: keyof CustomersState['modals']) => void
  setSelectedUnmappedCustomer: (customer: IntegrationCustomer | null) => void
  setSelectedCustomer: (customer: any | null) => void
  
  // Computed
  getFilteredCustomers: () => MappedCustomer[]
  getMappedCustomers: () => MappedCustomer[]
  getUnmappedCustomers: () => MappedCustomer[]
}

export const useCustomersStore = create<CustomersState>((set, get) => ({
  // Initial state
  customers: [],
  isLoading: true,
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

  // Fetch customers
  fetchCustomers: async () => {
    set({ isLoading: true })
    try {
      const response = await api.get('/customers')
      const data = (response as any).data || response
      const customerData = data.data || data
      
      set({
        customers: customerData,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch customers:', err)
      set({ isLoading: false })
      throw err
    }
  },

  // Filter actions
  setSearchQuery: (query: string) => set({ searchQuery: query }),
  setStatusFilter: (status: string) => set({ statusFilter: status }),
  setReportFilter: (filter: string) => set({ reportFilter: filter }),

  // Modal actions
  openModal: (modal, customer) => {
    set(state => ({
      modals: { ...state.modals, [modal]: true },
      selectedCustomer: customer || state.selectedCustomer,
    }))
  },

  closeModal: modal => {
    set(state => ({
      modals: { ...state.modals, [modal]: false },
    }))
  },

  setSelectedUnmappedCustomer: (customer: IntegrationCustomer | null) => {
    set({ selectedUnmappedCustomer: customer })
  },

  setSelectedCustomer: (customer: any | null) => {
    set({ selectedCustomer: customer })
  },

  // Computed - get filtered customers
  getFilteredCustomers: () => {
    const { customers, searchQuery, statusFilter, reportFilter } = get()
    
    return customers.filter(c => {
      // Only show mapped customers
      if (c.customer_id === null) return false
      
      // Search filter
      if (searchQuery && !c.canonical_name?.toLowerCase().includes(searchQuery.toLowerCase())) {
        return false
      }
      
      // Status filter
      if (statusFilter !== 'all' && c.status !== statusFilter) {
        return false
      }
      
      // Report configuration filter
      if (reportFilter === 'configured' && (!c.report_schedules_count || c.report_schedules_count === 0)) {
        return false
      }
      if (reportFilter === 'not-configured' && c.report_schedules_count && c.report_schedules_count > 0) {
        return false
      }
      
      return true
    })
  },

  // Computed - get mapped customers
  getMappedCustomers: () => {
    return get().getFilteredCustomers()
  },

  // Computed - get unmapped customers
  getUnmappedCustomers: () => {
    const { customers } = get()
    return customers.filter(c => c.customer_id === null)
  },
}))
