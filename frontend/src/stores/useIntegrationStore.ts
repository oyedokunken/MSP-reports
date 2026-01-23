import { create } from 'zustand'
import type { Integration } from '@/pages/integrations/types/integration'
import { PAGINATION } from '@/lib/constants'

interface IntegrationState {
  // Search, Filter & Pagination (client-side)
  searchQuery: string
  statusFilters: string[]
  integrationTypeFilters: string[]
  currentPage: number
  perPage: number

  // Modal state
  modals: {
    add: boolean
    edit: boolean
    delete: boolean
    syncLogs: boolean
    health: boolean
    rateLimit: boolean
    historicalImport: boolean
    viewHistoricalJobs: boolean
  }
  selectedIntegration: Integration | null

  // Actions
  setPage: (page: number) => void
  setSearchQuery: (query: string) => void
  setStatusFilters: (statuses: string[]) => void
  setIntegrationTypeFilters: (types: string[]) => void

  // Modal actions
  openModal: (modal: keyof IntegrationState['modals'], integration?: Integration) => void
  closeModal: (modal: keyof IntegrationState['modals']) => void
  setSelectedIntegration: (integration: Integration | null) => void
}

export const useIntegrationStore = create<IntegrationState>((set) => ({
  // Initial state
  searchQuery: '',
  statusFilters: [],
  integrationTypeFilters: [],
  currentPage: 1,
  perPage: PAGINATION.INTEGRATIONS_PER_PAGE,

  modals: {
    add: false,
    edit: false,
    delete: false,
    syncLogs: false,
    health: false,
    rateLimit: false,
    historicalImport: false,
    viewHistoricalJobs: false,
  },
  selectedIntegration: null,

  // Actions
  setPage: (page: number) => set({ currentPage: page }),
  setSearchQuery: (query: string) => set({ searchQuery: query, currentPage: 1 }),
  setStatusFilters: (statuses: string[]) => set({ statusFilters: statuses, currentPage: 1 }),
  setIntegrationTypeFilters: (types: string[]) => set({ integrationTypeFilters: types, currentPage: 1 }),

  // Modal actions
  openModal: (modal, integration) => {
    set(state => ({
      modals: { ...state.modals, [modal]: true },
      selectedIntegration: integration || state.selectedIntegration,
    }))
  },

  closeModal: modal => {
    set(state => ({
      modals: { ...state.modals, [modal]: false },
    }))
  },

  setSelectedIntegration: integration => {
    set({ selectedIntegration: integration })
  },
}))
