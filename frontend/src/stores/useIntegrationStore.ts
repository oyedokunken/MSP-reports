import { create } from 'zustand'
import { api } from '@/lib/api'
import { transformIntegrations } from '@/pages/integrations/utils/transformIntegrations'
import type { Integration } from '@/pages/integrations/types/integration'
import type { IntegrationListResponse } from '@/types/api/integration'



interface IntegrationState {
  // Data
  integrations: Integration[]
  allIntegrations: Integration[] // Store all for client-side pagination
  availableTypes: string[]
  sourceOfTruthId: number | null
  isLoading: boolean

  // Search, Filter & Pagination (client-side)
  searchQuery: string
  statusFilters: string[]
  integrationTypeFilters: string[]
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
  fetchIntegrations: (silent?: boolean, force?: boolean) => Promise<void>
  setSourceOfTruth: (id: number) => Promise<{ success: boolean; error?: string }>
  syncIntegration: (id: number) => Promise<{ success: boolean; error?: string }>
  setPage: (page: number) => void
  setSearchQuery: (query: string) => void
  setStatusFilters: (statuses: string[]) => void
  setIntegrationTypeFilters: (types: string[]) => void
  getFilteredIntegrations: () => Integration[]
  getUniqueStatuses: () => string[]
  getUniqueTypes: () => string[]

  // Modal actions
  openModal: (modal: keyof IntegrationState['modals'], integration?: Integration) => void
  closeModal: (modal: keyof IntegrationState['modals']) => void
  setSelectedIntegration: (integration: Integration | null) => void
}

// Cache timestamp
let lastFetchTime = 0;
const CACHE_DURATION = 30000; // 30 seconds cache

export const useIntegrationStore = create<IntegrationState>((set, get) => ({
  // Initial state
  integrations: [],
  allIntegrations: [],
  availableTypes: [],
  sourceOfTruthId: null,
  isLoading: true,
  searchQuery: '',
  statusFilters: [],
  integrationTypeFilters: [],
  currentPage: 1,
  perPage: 9,
  syncingId: null,
  syncProgress: 0,
  settingSourceOfTruthId: null,
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

  // Fetch integrations with caching
  fetchIntegrations: async (silent = false, force = false) => {
    // Check cache - if data was fetched less than 30 seconds ago, skip
    const now = Date.now();
    const state = get();
    if (!force && state.integrations.length > 0 && now - lastFetchTime < CACHE_DURATION) {
      return;
    }

    if (!silent) set({ isLoading: true })

    try {
      const response = await api.get('/integrations') as unknown as IntegrationListResponse

      const availableTypes = response.available_types || []
      const transformed = transformIntegrations(response) || []
      const sourceOfTruth = response.configured?.find(int => int.is_source_of_truth)
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

      lastFetchTime = now; // Update cache timestamp
    } catch (error) {
      console.error('Failed to fetch integrations:', error)
      set({ isLoading: false })
    }
  },

  // Set source of truth
  setSourceOfTruth: async (id: number) => {
    set({ settingSourceOfTruthId: id })
    try {
      await api.patch('/company/source-integration', { integration_id: id })
      set({ sourceOfTruthId: id, settingSourceOfTruthId: null })
      await get().fetchIntegrations(true, true) // refresh silently
      return { success: true }
    } catch (error) {
      set({ settingSourceOfTruthId: null })
      const errorMessage = error instanceof Error ? error.message : 'Failed to set source of truth'
      return {
        success: false,
        error: errorMessage,
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
      // Capture initial state to detect changes - use allIntegrations to ensure we find it
      const initialIntegration = get().allIntegrations.find(i => i.id === integrationId)
      const initialLastSync = initialIntegration?.lastSync
      let previousUnmappedCount = initialIntegration?.customers || 0
      let stableCountCycles = 0

      await api.get(`/integrations/${integrationId}/sync`)
      clearInterval(interval)
      set({ syncProgress: 100 })

      // short delay for animation
      await new Promise(resolve => setTimeout(resolve, 300))

      set({ syncingId: null, syncProgress: 0 })

      // Start polling for updates (sync is processed asynchronously in backend)
      const pollInterval = setInterval(async () => {
        await get().fetchIntegrations(true, true)

        // Check allIntegrations (not paginated) to ensure we find the integration
        const currentIntegration = get().allIntegrations.find(i => i.id === integrationId)
        const currentLastSync = currentIntegration?.lastSync
        const currentUnmappedCount = currentIntegration?.customers || 0

        // Check 1: Has the main sync job finished? (Timestamp updated)
        let isSyncUpdated = false
        if (!initialLastSync && currentLastSync) isSyncUpdated = true
        else if (initialLastSync && currentLastSync && currentLastSync !== initialLastSync) isSyncUpdated = true

        // Check 2: Is the unmapped count changing? (Auto-match activity)
        if (currentUnmappedCount !== previousUnmappedCount) {
          stableCountCycles = 0 // Reset stability counter
        } else {
          stableCountCycles++
        }

        // Stop Condition: Sync is done AND Count is stable for 2 cycles (4 seconds)
        if (isSyncUpdated && stableCountCycles >= 2) {
          clearInterval(pollInterval)
        }

        previousUnmappedCount = currentUnmappedCount

      }, 2000) // Poll every 2 seconds

      // Stop polling after 60 seconds (safety timeout)
      setTimeout(() => {
        clearInterval(pollInterval)
      }, 60000)

      // Initial refresh
      await get().fetchIntegrations(true, true)

      return { success: true }
    } catch (error) {
      clearInterval(interval)
      set({ syncingId: null, syncProgress: 0 })
      const errorMessage = error instanceof Error ? error.message : 'Sync failed'
      return {
        success: false,
        error: errorMessage,
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

  // Pagination action
  setPage: (page: number) => {
    const { perPage } = get()
    const filtered = get().getFilteredIntegrations()
    const startIndex = (page - 1) * perPage
    const endIndex = startIndex + perPage
    const paginatedIntegrations = filtered.slice(startIndex, endIndex)

    set({
      currentPage: page,
      integrations: paginatedIntegrations
    })
  },

  // Search action
  setSearchQuery: (query: string) => {
    set({ searchQuery: query, currentPage: 1 })
    // Re-paginate with filtered results
    const { perPage } = get()
    const filtered = get().getFilteredIntegrations()
    const paginatedIntegrations = filtered.slice(0, perPage)
    set({ integrations: paginatedIntegrations })
  },

  // Status filter action
  setStatusFilters: (statuses: string[]) => {
    set({ statusFilters: statuses, currentPage: 1 })
    const { perPage } = get()
    const filtered = get().getFilteredIntegrations()
    const paginatedIntegrations = filtered.slice(0, perPage)
    set({ integrations: paginatedIntegrations })
  },

  // Integration type filter action
  setIntegrationTypeFilters: (types: string[]) => {
    set({ integrationTypeFilters: types, currentPage: 1 })
    const { perPage } = get()
    const filtered = get().getFilteredIntegrations()
    const paginatedIntegrations = filtered.slice(0, perPage)
    set({ integrations: paginatedIntegrations })
  },

  // Get filtered integrations based on search, status, and type
  getFilteredIntegrations: () => {
    const { allIntegrations, searchQuery, statusFilters, integrationTypeFilters } = get()

    return allIntegrations.filter(int => {
      // Search filter
      const matchesSearch = !searchQuery.trim() ||
        int.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        int.type.toLowerCase().includes(searchQuery.toLowerCase())

      // Status filter
      const matchesStatus = statusFilters.length === 0 ||
        statusFilters.some(status => int.status.toLowerCase() === status.toLowerCase())

      // Integration type filter
      const matchesType = integrationTypeFilters.length === 0 ||
        integrationTypeFilters.some(type => int.type.toLowerCase() === type.toLowerCase())

      return matchesSearch && matchesStatus && matchesType
    })
  },

  // Get unique statuses for filter dropdown
  getUniqueStatuses: () => {
    const { allIntegrations } = get()
    const statuses = [...new Set(allIntegrations.map(int => int.status))]
    return statuses.sort()
  },

  // Get unique integration types for filter dropdown
  getUniqueTypes: () => {
    const { allIntegrations } = get()
    const types = [...new Set(allIntegrations.map(int => int.type))]
    return types.sort()
  },
}))
