import { create } from 'zustand'
import { api } from '@/lib/api'
import type {
  LicenseListResponse,
  LicenseUsageResponse,
  License,
  UserLicenseStatus,
  LicenseData,
  LicenseUsage
} from '@/types/api/license'

// Re-export for backward compatibility
export type { License, UserLicenseStatus, LicenseData, LicenseUsage }


interface LicensesState {
  // Data
  licenseData: LicenseData | null
  usage: LicenseUsage | null
  isLoading: boolean
  error: string | null

  // Actions
  fetchLicenses: () => Promise<void>
  fetchUsage: () => Promise<void>
}

export const useLicensesStore = create<LicensesState>((set) => ({
  // Initial state
  licenseData: null,
  usage: null,
  isLoading: true,
  error: null,

  // Fetch licenses
  fetchLicenses: async () => {
    set({ isLoading: true, error: null })
    try {
      const response = await api.get('/licenses') as unknown as LicenseListResponse

      set({
        licenseData: response.data,
        isLoading: false,
      })
    } catch (error) {
      console.error('Failed to fetch licenses:', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to load licenses'
      set({
        error: errorMessage,
        isLoading: false,
      })
    }
  },

  // Fetch license usage
  fetchUsage: async () => {
    try {
      const response = await api.get('/licenses/usage') as unknown as LicenseUsageResponse

      set({
        usage: response.data,
      })
    } catch (error) {
      console.error('Failed to fetch license usage:', error)
    }
  },
}))
