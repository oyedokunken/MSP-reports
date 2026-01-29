import { create } from 'zustand'
import { api } from '@/lib/api'
import { logger } from '@/lib/logger'
import type {
  LicenseListResponse,
  LicenseUsageResponse,
  License,
  UserLicenseStatus,
  LicenseData,
  LicenseUsage
} from '@/types/api/license'

export type { License, UserLicenseStatus, LicenseData, LicenseUsage }


interface LicensesState {
  licenseData: LicenseData | null
  usage: LicenseUsage | null
  isLoading: boolean
  error: string | null

  fetchLicenses: () => Promise<void>
  fetchUsage: () => Promise<void>
}

export const useLicensesStore = create<LicensesState>((set) => ({
  licenseData: null,
  usage: null,
  isLoading: false,
  error: null,

  fetchLicenses: async () => {
    set({ isLoading: true, error: null })
    try {
      const response = await api.get<LicenseListResponse>('/licenses')

      set({
        licenseData: response.data,
        isLoading: false,
      })
    } catch (error) {
      logger.apiError('/licenses', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to load licenses'
      set({
        error: errorMessage,
        isLoading: false,
      })
    }
  },

  fetchUsage: async () => {
    try {
      const response = await api.get<LicenseUsageResponse>('/licenses/usage')

      set({
        usage: response.data,
      })
    } catch (error) {
      logger.apiError('/licenses/usage', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to load license usage'
      set({ error: errorMessage })
    }
  },
}))
