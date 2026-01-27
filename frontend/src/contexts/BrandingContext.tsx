import { logger } from '@/lib/logger'
import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react'
import type { WhiteLabelSettings } from '@/types/auth'

interface BrandingContextType {
  branding: WhiteLabelSettings | null
  setBranding: (branding: WhiteLabelSettings | null) => void
  applyBranding: () => void
}

const defaultBranding: WhiteLabelSettings = {
  enabled: false,
  company_name: 'Reporting Platform',
  logo_url: null,
  primary_color: '#1a56db',
  secondary_color: '#7c3aed',
  favicon_url: null,
  email_from_name: 'Reports',
  email_from_address: 'reports@example.com',
  support_email: 'support@example.com',
  support_phone: '',
  footer_text: '© 2025 Reporting Platform',
  report_header_text: 'Service Report',
}

// Helper function to calculate luminance and determine if text should be black or white
const getContrastColor = (hex: string): string => {
  // Remove # if present
  hex = hex.replace('#', '')

  // Convert to RGB
  const r = parseInt(hex.substring(0, 2), 16) / 255
  const g = parseInt(hex.substring(2, 4), 16) / 255
  const b = parseInt(hex.substring(4, 6), 16) / 255

  // Calculate relative luminance using WCAG formula
  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b

  // Return black for light backgrounds, white for dark backgrounds
  // Threshold of 0.5 works well for most cases
  return luminance > 0.5 ? '#000000' : '#ffffff'
}

// Helper function to adjust brightness of a hex color
const adjustBrightness = (hex: string, percent: number): string => {
  // Remove # if present
  hex = hex.replace('#', '')

  // Convert to RGB
  let r = parseInt(hex.substring(0, 2), 16)
  let g = parseInt(hex.substring(2, 4), 16)
  let b = parseInt(hex.substring(4, 6), 16)

  // Adjust brightness
  r = Math.max(0, Math.min(255, r + (r * percent / 100)))
  g = Math.max(0, Math.min(255, g + (g * percent / 100)))
  b = Math.max(0, Math.min(255, b + (b * percent / 100)))

  // Convert back to hex
  const rr = Math.round(r).toString(16).padStart(2, '0')
  const gg = Math.round(g).toString(16).padStart(2, '0')
  const bb = Math.round(b).toString(16).padStart(2, '0')

  return `#${rr}${gg}${bb}`
}

// Helper function to convert hex to HSL format for Tailwind
const hexToHSL = (hex: string): string => {
  // Remove # if present
  hex = hex.replace('#', '')

  // Convert hex to RGB
  const r = parseInt(hex.substring(0, 2), 16) / 255
  const g = parseInt(hex.substring(2, 4), 16) / 255
  const b = parseInt(hex.substring(4, 6), 16) / 255

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

  // Convert to degrees and percentages
  h = Math.round(h * 360)
  s = Math.round(s * 100)
  const lPercent = Math.round(l * 100)

  // Return in Tailwind's HSL format (without hsl() wrapper)
  return `${h} ${s}% ${lPercent}%`
}

const BrandingContext = createContext<BrandingContextType | undefined>(undefined)

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBrandingState] = useState<WhiteLabelSettings | null>(null)

  const setBranding = (newBranding: WhiteLabelSettings | null) => {
    setBrandingState(newBranding)
    if (newBranding) {
      localStorage.setItem('branding', JSON.stringify(newBranding))
    } else {
      localStorage.removeItem('branding')
    }
  }

  const applyBranding = useCallback(() => {
    const currentBranding = branding || defaultBranding

    // Apply CSS variables for colors
    const root = document.documentElement

    // Use actual brand colors from API
    const primaryColor = currentBranding.primary_color
    const secondaryColor = currentBranding.secondary_color

    root.style.setProperty('--brand-primary', primaryColor)
    root.style.setProperty('--brand-secondary', secondaryColor)

    // Convert hex to HSL for Tailwind compatibility
    const primaryHSL = hexToHSL(primaryColor)
    const secondaryHSL = hexToHSL(secondaryColor)

    // Calculate contrast colors for text
    const primaryForeground = getContrastColor(primaryColor)
    const secondaryForeground = getContrastColor(secondaryColor)

    // Convert foreground colors to HSL
    const primaryForegroundHSL = hexToHSL(primaryForeground)
    const secondaryForegroundHSL = hexToHSL(secondaryForeground)

    // Update Tailwind's primary and secondary colors
    root.style.setProperty('--primary', primaryHSL)
    root.style.setProperty('--primary-foreground', primaryForegroundHSL)
    root.style.setProperty('--secondary', secondaryHSL)
    root.style.setProperty('--secondary-foreground', secondaryForegroundHSL)

    // Update sidebar colors (for active/hover states)
    root.style.setProperty('--sidebar-primary', primaryHSL)
    root.style.setProperty('--sidebar-primary-foreground', primaryForegroundHSL)
    root.style.setProperty('--sidebar-accent', primaryHSL)
    root.style.setProperty('--sidebar-accent-foreground', primaryForegroundHSL)
    root.style.setProperty('--sidebar-ring', primaryHSL)

    // Also update dark mode sidebar colors
    root.style.setProperty('--sidebar-primary-dark', primaryHSL)
    root.style.setProperty('--sidebar-accent-dark', primaryHSL)

    // Create gradient using only primary color (lighter to darker)
    const gradient = `linear-gradient(135deg, ${primaryColor} 0%, ${adjustBrightness(primaryColor, -20)} 100%)`
    root.style.setProperty('--brand-gradient', gradient)

    // Update page title
    document.title = currentBranding.company_name

    // Update favicon
    if (currentBranding.favicon_url) {
      let link: HTMLLinkElement | null = document.querySelector("link[rel~='icon']")
      if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
      }
      link.href = currentBranding.favicon_url
    }

    // Update meta theme color
    let metaTheme: HTMLMetaElement | null = document.querySelector('meta[name="theme-color"]')
    if (!metaTheme) {
      metaTheme = document.createElement('meta')
      metaTheme.name = 'theme-color'
      document.head.appendChild(metaTheme)
    }
    metaTheme.content = currentBranding.primary_color
  }, [branding])

  // Initialize branding from localStorage
  useEffect(() => {
    const storedBranding = localStorage.getItem('branding')
    if (storedBranding) {
      try {
        const parsed = JSON.parse(storedBranding)
        setBrandingState(parsed)
      } catch (error) {
        logger.error('Failed to parse stored branding', error)
      }
    }
  }, [])

  // Apply branding whenever it changes
  useEffect(() => {
    applyBranding()
  }, [applyBranding])

  return (
    <BrandingContext.Provider
      value={{
        branding: branding || defaultBranding,
        setBranding,
        applyBranding,
      }}
    >
      {children}
    </BrandingContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useBranding() {
  const context = useContext(BrandingContext)
  if (context === undefined) {
    throw new Error('useBranding must be used within a BrandingProvider')
  }
  return context
}
