import { createContext, useContext, useState, useEffect, type ReactNode } from 'react'
import type { CompanyBranding, BrandingState } from '@/types/branding'
import { api } from '@/lib/api'
import { mockFetchBranding } from '@/lib/mockData'

// Use mock branding in development
const USE_MOCK_BRANDING = import.meta.env.DEV

interface BrandingContextType extends BrandingState {
  applyBranding: (branding: CompanyBranding) => void
  refreshBranding: () => Promise<void>
}

const BrandingContext = createContext<BrandingContextType | undefined>(undefined)

// Default branding when not loaded
const defaultBranding: CompanyBranding = {
  enabled: false,
  company_name: 'Reporting Platform',
  logo_url: null,
  primary_color: '#1e40af',
  secondary_color: '#64748b',
  favicon_url: null,
  email_from_name: 'Reporting Platform',
  email_from_address: 'noreply@example.com',
  support_email: 'support@example.com',
  support_phone: null,
  footer_text: '© 2025 Reporting Platform',
  report_header_text: 'Monthly Service Report',
}

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<BrandingState>({
    branding: defaultBranding,
    isLoading: true,
  })

  // Load branding on mount
  useEffect(() => {
    refreshBranding()
  }, [])

  // Apply branding to CSS variables and document
  const applyBranding = (branding: CompanyBranding) => {
    if (!branding.enabled) {
      return
    }

    const root = document.documentElement

    // Convert hex to HSL for CSS variables
    const hexToHSL = (hex: string) => {
      const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
      if (!result) return '221 83% 53%' // default primary

      const r = parseInt(result[1], 16) / 255
      const g = parseInt(result[2], 16) / 255
      const b = parseInt(result[3], 16) / 255

      const max = Math.max(r, g, b)
      const min = Math.min(r, g, b)
      let h = 0
      let s = 0
      const l = (max + min) / 2

      if (max !== min) {
        const d = max - min
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
        switch (max) {
          case r:
            h = ((g - b) / d + (g < b ? 6 : 0)) / 6
            break
          case g:
            h = ((b - r) / d + 2) / 6
            break
          case b:
            h = ((r - g) / d + 4) / 6
            break
        }
      }

      return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`
    }

    // Apply primary color
    if (branding.primary_color) {
      root.style.setProperty('--primary', hexToHSL(branding.primary_color))
    }

    // Apply secondary color
    if (branding.secondary_color) {
      root.style.setProperty('--secondary', hexToHSL(branding.secondary_color))
    }

    // Update document title
    document.title = branding.company_name || 'Reporting Platform'

    // Update favicon if provided
    if (branding.favicon_url) {
      const link = document.querySelector("link[rel~='icon']") as HTMLLinkElement
      if (link) {
        link.href = branding.favicon_url
      } else {
        const newLink = document.createElement('link')
        newLink.rel = 'icon'
        newLink.href = branding.favicon_url
        document.head.appendChild(newLink)
      }
    }
  }

  const refreshBranding = async () => {
    try {
      setState((prev) => ({ ...prev, isLoading: true }))

      let branding: CompanyBranding

      if (USE_MOCK_BRANDING) {
        // Use mock branding in development
        branding = await mockFetchBranding()
      } else {
        // Use real API in production
        const response = await api.get<CompanyBranding>('/branding')

        if (response.success && response.data) {
          branding = response.data
        } else {
          branding = defaultBranding
        }
      }

      applyBranding(branding)

      setState({
        branding,
        isLoading: false,
      })
    } catch (error) {
      console.error('Failed to load branding:', error)
      setState({
        branding: defaultBranding,
        isLoading: false,
      })
    }
  }

  return (
    <BrandingContext.Provider
      value={{
        ...state,
        applyBranding,
        refreshBranding,
      }}
    >
      {children}
    </BrandingContext.Provider>
  )
}

export function useBranding() {
  const context = useContext(BrandingContext)
  if (context === undefined) {
    throw new Error('useBranding must be used within a BrandingProvider')
  }
  return context
}
