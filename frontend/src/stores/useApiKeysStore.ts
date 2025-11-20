import { create } from 'zustand'
import { api } from '@/lib/api'

export interface ApiKey {
  api_key_id: number
  company_id: number
  display_name: string
  key_preview?: string
  last_used_at: string | null
  permissions: Record<string, Record<string, boolean>>
  ip_whitelist: string[] | null
  allowed_endpoints: string[] | null
  revoked: boolean
  revoked_at: string | null
  created_at: string
  updated_at: string
}

interface ApiKeysState {
  apiKeys: ApiKey[]
  isLoading: boolean
  error: string | null
  fetchApiKeys: () => Promise<void>
}

export const useApiKeysStore = create<ApiKeysState>((set) => ({
  apiKeys: [],
  isLoading: false,
  error: null,

  fetchApiKeys: async () => {
    set({ isLoading: true, error: null })
    try {
      const response = await api.get('/api-keys')
      const data = (response as any).data || response
      const apiKeysList = Array.isArray(data) ? data : data.data || []
      
      set({ apiKeys: apiKeysList, isLoading: false })
    } catch (error: any) {
      set({ error: error.message, isLoading: false })
    }
  },
}))
