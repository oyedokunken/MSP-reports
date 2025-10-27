import { create } from 'zustand'
import { api } from '@/lib/api'

export interface License {
  license_id: number
  license_type: string
  product_sku: string
  status: 'active' | 'expired' | 'suspended'
  expiry_date: string | null
  quantity: number | null
  metadata: {
    description?: string
    includes?: string[]
    deployment_type?: string
    customer?: string
    [key: string]: any
  }
  created_at: string
  updated_at: string
  company_id: number
}

export interface UserLicenseStatus {
  licensed: boolean
  current_users: number
  max_users: number
  remaining: number
}

export interface LicenseData {
  active_licenses: License[]
  expiring_soon: License[]
  user_license_status: UserLicenseStatus
  license_enforcement_enabled: boolean
}

export interface LicenseUsage {
  integrations: {
    active: number
    licensed: number
  }
  users: {
    licensed: boolean
    current_users: number
    max_users: number
    remaining: number
  }
  retention: {
    max_months: number
    licensed: boolean
  }
  license_enforcement_enabled: boolean
}

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
      const response = await api.get('/licenses')
      const apiResponse = (response as any).data || response
      const data: LicenseData = apiResponse.data || apiResponse
      
      set({
        licenseData: data,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch licenses:', err)
      set({
        error: err.response?.data?.message || err.message || 'Failed to load licenses',
        isLoading: false,
      })
    }
  },

  // Fetch license usage
  fetchUsage: async () => {
    try {
      const response = await api.get('/licenses/usage')
      const apiResponse = (response as any).data || response
      const data: LicenseUsage = apiResponse.data || apiResponse
      
      set({
        usage: data,
      })
    } catch (err: any) {
      console.error('Failed to fetch license usage:', err)
    }
  },
}))
