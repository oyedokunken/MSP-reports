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
  websiteUrl: string

  // Branding assets
  logoUrl: string | null
  faviconUrl: string | null

  // Email delivery
  fromEmail: string
  fromName: string
  supportEmail: string
  supportPhone: string

  // SMTP settings
  smtpHost: string
  smtpPort: number
  smtpEncryption: string
  smtpUsername: string
  smtpPassword?: string

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
  setWebsiteUrl: (url: string) => void
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
  setSmtpHost: (host: string) => void
  setSmtpPort: (port: number) => void
  setSmtpEncryption: (enc: string) => void
  setSmtpUsername: (user: string) => void
  setSmtpPassword: (pass: string) => void

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
  websiteUrl: '',
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
  smtpHost: '',
  smtpPort: 587,
  smtpEncryption: 'tls',
  smtpUsername: '',
  smtpPassword: '',

  // Actions
  setCompanyName: (name: string) => set({ companyName: name }),
  setPrimaryHex: (hex: string) => set({ primaryHex: hex }),
  setSecondaryHex: (hex: string) => set({ secondaryHex: hex }),
  setContactEmail: (email: string) => set({ contactEmail: email }),
  setContactPhone: (phone: string) => set({ contactPhone: phone }),
  setAddress: (address: string) => set({ address: address }),
  setWebsiteUrl: (url: string) => set({ websiteUrl: url }),
  setLogoUrl: (url: string | null) => set({ logoUrl: url }),
  setFaviconUrl: (url: string | null) => set({ faviconUrl: url }),
  setFromEmail: (email: string) => set({ fromEmail: email }),
  setFromName: (name: string) => set({ fromName: name }),
  setSupportEmail: (email: string) => set({ supportEmail: email }),
  setSupportPhone: (phone: string) => set({ supportPhone: phone }),
  setSmtpHost: (host: string) => set({ smtpHost: host }),
  setSmtpPort: (port: number) => set({ smtpPort: port }),
  setSmtpEncryption: (enc: string) => set({ smtpEncryption: enc }),
  setSmtpUsername: (user: string) => set({ smtpUsername: user }),
  setSmtpPassword: (pass: string) => set({ smtpPassword: pass }),
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
      websiteUrl: (branding as { website_url?: string }).website_url || '',
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
