import { create } from 'zustand'
import { api } from '@/lib/api'
import { transformIntegrations } from '@/pages/integrations/utils/transformIntegrations'
import type { Integration } from '@/pages/integrations/types/integration'

interface IntegrationState {
  // Data
  integrations: Integration[]
  allIntegrations: Integration[] // Store all for client-side pagination
  availableTypes: string[]
  sourceOfTruthId: number | null
  isLoading: boolean

  // Pagination (client-side)
  currentPage: number
  perPage: number
  
  // Sync state
  syncingId: number | null
  syncProgress: number
  
  // Source of truth setting state
  settingSourceOfTruthId: number | null

  // Modal state
  modals: {
    add: boolean
    viewCustomers: boolean
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
  fetchIntegrations: (silent?: boolean) => Promise<void>
  setSourceOfTruth: (id: number) => Promise<{ success: boolean; error?: string }>
  syncIntegration: (id: number) => Promise<{ success: boolean; error?: string }>
  setPage: (page: number) => void

  // Modal actions
  openModal: (modal: keyof IntegrationState['modals'], integration?: Integration) => void
  closeModal: (modal: keyof IntegrationState['modals']) => void
  setSelectedIntegration: (integration: Integration | null) => void
}

export const useIntegrationStore = create<IntegrationState>((set, get) => ({
  // Initial state
  integrations: [],
  allIntegrations: [],
  availableTypes: [],
  sourceOfTruthId: null,
  isLoading: true,
  currentPage: 1,
  perPage: 21,
  syncingId: null,
  syncProgress: 0,
  settingSourceOfTruthId: null,
  modals: {
    add: false,
    viewCustomers: false,
    edit: false,
    delete: false,
    syncLogs: false,
    health: false,
    rateLimit: false,
    historicalImport: false,
    viewHistoricalJobs: false,
  },
  selectedIntegration: null,

  // Fetch integrations
  fetchIntegrations: async (silent = false) => {
    if (!silent) set({ isLoading: true })

    try {
      const response = await api.get('/integrations')
      const data = (response as any).data || response

      const availableTypes = data.available_types || []
      const transformed = transformIntegrations(data) || []
      const sourceOfTruth = data.configured?.find((int: any) => int.is_source_of_truth)
      const sourceOfTruthId = sourceOfTruth ? sourceOfTruth.integration_id : null

      const { currentPage, perPage } = get()
      const startIndex = (currentPage - 1) * perPage
      const endIndex = startIndex + perPage
      const paginatedIntegrations = transformed.slice(startIndex, endIndex)

      set({
        integrations: paginatedIntegrations,
        allIntegrations: transformed,
        availableTypes,
        sourceOfTruthId,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch integrations:', err)
      set({ isLoading: false })
    }
  },

  // Set source of truth
  setSourceOfTruth: async (id: number) => {
    set({ settingSourceOfTruthId: id })
    try {
      await api.patch('/company/source-integration', { integration_id: id })
      set({ sourceOfTruthId: id, settingSourceOfTruthId: null })
      await get().fetchIntegrations(true) // refresh silently
      return { success: true }
    } catch (err: any) {
      set({ settingSourceOfTruthId: null })
      return {
        success: false,
        error: err.response?.data?.message || err.message,
      }
    }
  },

  // Sync integration
  syncIntegration: async (integrationId: number) => {
    set({ syncingId: integrationId, syncProgress: 0 })

    // Progress animation
    const interval = setInterval(() => {
      set(state => ({
        syncProgress: Math.min(state.syncProgress + Math.random() * 15, 90),
      }))
    }, 300)

    try {
      await api.get(`/integrations/${integrationId}/sync`)
      clearInterval(interval)
      set({ syncProgress: 100 })

      // short delay for animation
      await new Promise(resolve => setTimeout(resolve, 300))

      set({ syncingId: null, syncProgress: 0 })

      // Start polling for updates (sync is processed asynchronously in backend)
      const pollInterval = setInterval(async () => {
        await get().fetchIntegrations(true)
      }, 2000) // Poll every 2 seconds

      // Stop polling after 30 seconds
      setTimeout(() => {
        clearInterval(pollInterval)
      }, 30000)

      // Initial refresh
      await get().fetchIntegrations(true)

      return { success: true }
    } catch (err: any) {
      clearInterval(interval)
      set({ syncingId: null, syncProgress: 0 })
      return {
        success: false,
        error: err.response?.data?.message || err.message,
      }
    }
  },

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
