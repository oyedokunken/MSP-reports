import { create } from 'zustand'
import type { CompanyBranding } from '@/types/branding'

interface SettingsState {
  // Company info
  companyName: string
  primaryHex: string
  secondaryHex: string
  contactEmail: string
  contactPhone: string
  address: string
  
  // Branding assets
  logoUrl: string | null
  faviconUrl: string | null
  
  // Email delivery
  fromEmail: string
  fromName: string
  supportEmail: string
  supportPhone: string
  
  // Report branding
  footerText: string
  reportHeaderText: string
  
  // API key
  apiKeyMasked: string
  lastGenerated: string | null
  
  // Modal state
  changePasswordModalOpen: boolean
  
  // Actions
  setCompanyName: (name: string) => void
  setPrimaryHex: (hex: string) => void
  setSecondaryHex: (hex: string) => void
  setContactEmail: (email: string) => void
  setContactPhone: (phone: string) => void
  setAddress: (address: string) => void
  setLogoUrl: (url: string | null) => void
  setFaviconUrl: (url: string | null) => void
  setFromEmail: (email: string) => void
  setFromName: (name: string) => void
  setSupportEmail: (email: string) => void
  setSupportPhone: (phone: string) => void
  setFooterText: (text: string) => void
  setReportHeaderText: (text: string) => void
  setLastGenerated: (key: string | null) => void
  setChangePasswordModalOpen: (open: boolean) => void
  
  // Initialize from branding
  initializeFromBranding: (branding: CompanyBranding) => void
  
  // Computed
  isValidHex: () => boolean
  isValidSecondaryHex: () => boolean
}

export const useSettingsStore = create<SettingsState>((set, get) => ({
  // Initial state
  companyName: '',
  primaryHex: '#3b82f6',
  secondaryHex: '#7c3aed',
  contactEmail: '',
  contactPhone: '',
  address: '',
  logoUrl: null,
  faviconUrl: null,
  fromEmail: '',
  fromName: '',
  supportEmail: '',
  supportPhone: '',
  footerText: '',
  reportHeaderText: 'Monthly Service Report',
  apiKeyMasked: '•'.repeat(26),
  lastGenerated: null,
  changePasswordModalOpen: false,

  // Actions
  setCompanyName: (name: string) => set({ companyName: name }),
  setPrimaryHex: (hex: string) => set({ primaryHex: hex }),
  setSecondaryHex: (hex: string) => set({ secondaryHex: hex }),
  setContactEmail: (email: string) => set({ contactEmail: email }),
  setContactPhone: (phone: string) => set({ contactPhone: phone }),
  setAddress: (address: string) => set({ address: address }),
  setLogoUrl: (url: string | null) => set({ logoUrl: url }),
  setFaviconUrl: (url: string | null) => set({ faviconUrl: url }),
  setFromEmail: (email: string) => set({ fromEmail: email }),
  setFromName: (name: string) => set({ fromName: name }),
  setSupportEmail: (email: string) => set({ supportEmail: email }),
  setSupportPhone: (phone: string) => set({ supportPhone: phone }),
  setFooterText: (text: string) => set({ footerText: text }),
  setReportHeaderText: (text: string) => set({ reportHeaderText: text }),
  setLastGenerated: (key: string | null) => set({ lastGenerated: key }),
  setChangePasswordModalOpen: (open: boolean) => set({ changePasswordModalOpen: open }),

  // Initialize from branding
  initializeFromBranding: (branding: CompanyBranding) => {
    set({
      companyName: branding.company_name || '',
      primaryHex: branding.primary_color || '#3b82f6',
      secondaryHex: branding.secondary_color || '#7c3aed',
      logoUrl: branding.logo_url || null,
      faviconUrl: branding.favicon_url || null,
      fromEmail: branding.email_from_address || '',
      fromName: branding.email_from_name || '',
      supportEmail: branding.support_email || '',
      supportPhone: branding.support_phone || '',
      footerText: branding.footer_text || '',
      reportHeaderText: branding.report_header_text || 'Monthly Service Report',
    })
  },

  // Computed - validate hex colors
  isValidHex: () => {
    const { primaryHex } = get()
    return /^#([0-9a-f]{6})$/i.test(primaryHex)
  },
  
  isValidSecondaryHex: () => {
    const { secondaryHex } = get()
    return /^#([0-9a-f]{6})$/i.test(secondaryHex)
  },
}))
