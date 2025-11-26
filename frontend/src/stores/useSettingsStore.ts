import { create } from 'zustand'
import type { CompanyBranding } from '@/types/branding'

interface SettingsState {
  // Company info
  companyName: string
  primaryHex: string
  contactEmail: string
  contactPhone: string
  address: string
  
  // Email delivery
  fromEmail: string
  fromName: string
  
  // API key
  apiKeyMasked: string
  lastGenerated: string | null
  
  // Modal state
  changePasswordModalOpen: boolean
  
  // Actions
  setCompanyName: (name: string) => void
  setPrimaryHex: (hex: string) => void
  setContactEmail: (email: string) => void
  setContactPhone: (phone: string) => void
  setAddress: (address: string) => void
  setFromEmail: (email: string) => void
  setFromName: (name: string) => void
  setLastGenerated: (key: string | null) => void
  setChangePasswordModalOpen: (open: boolean) => void
  
  // Initialize from branding
  initializeFromBranding: (branding: CompanyBranding) => void
  
  // Computed
  isValidHex: () => boolean
}

export const useSettingsStore = create<SettingsState>((set, get) => ({
  // Initial state
  companyName: '',
  primaryHex: '#3b82f6',
  contactEmail: '',
  contactPhone: '',
  address: '',
  fromEmail: '',
  fromName: '',
  apiKeyMasked: '•'.repeat(26),
  lastGenerated: null,
  changePasswordModalOpen: false,

  // Actions
  setCompanyName: (name: string) => set({ companyName: name }),
  setPrimaryHex: (hex: string) => set({ primaryHex: hex }),
  setContactEmail: (email: string) => set({ contactEmail: email }),
  setContactPhone: (phone: string) => set({ contactPhone: phone }),
  setAddress: (address: string) => set({ address: address }),
  setFromEmail: (email: string) => set({ fromEmail: email }),
  setFromName: (name: string) => set({ fromName: name }),
  setLastGenerated: (key: string | null) => set({ lastGenerated: key }),
  setChangePasswordModalOpen: (open: boolean) => set({ changePasswordModalOpen: open }),

  // Initialize from branding
  initializeFromBranding: (branding: CompanyBranding) => {
    set({
      companyName: branding.company_name || '',
      primaryHex: branding.primary_color || '#3b82f6',
      fromEmail: branding.email_from_address || '',
      fromName: branding.email_from_name || '',
    })
  },

  // Computed - validate hex color
  isValidHex: () => {
    const { primaryHex } = get()
    return /^#([0-9a-f]{6})$/i.test(primaryHex)
  },
}))
