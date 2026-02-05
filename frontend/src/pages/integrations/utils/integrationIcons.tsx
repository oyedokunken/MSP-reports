import { Cable } from 'lucide-react'
import type { ReactNode } from 'react'

type IntegrationIconConfig = {
  icon: ReactNode
  color: string
  bgColor: string
}

const integrationConfigs: Record<string, IntegrationIconConfig> = {
  activedirectory: {
    icon: (
      <svg viewBox="0 0 96 96" className="h-8 w-8">
        <path fill="#00adef" d="M0 0h45.5v45.5H0z"/>
        <path fill="#7fba00" d="M50.5 0H96v45.5H50.5z"/>
        <path fill="#ffb900" d="M0 50.5h45.5V96H0z"/>
        <path fill="#f25022" d="M50.5 50.5H96V96H50.5z"/>
      </svg>
    ),
    color: 'text-blue-600',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  autotask: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#007DC3" d="M50 5L5 30v40l45 25 45-25V30L50 5zm0 10l35 19.4v31.2L50 85 15 65.6V34.4L50 15z"/>
        <path fill="#007DC3" d="M50 25L25 40v20l25 15 25-15V40L50 25z"/>
      </svg>
    ),
    color: 'text-[#007DC3]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  datto: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <circle cx="50" cy="50" r="45" fill="#199ED9"/>
        <path fill="#fff" d="M35 30h30c8.3 0 15 6.7 15 15v10c0 8.3-6.7 15-15 15H35V30zm10 10v20h20c2.8 0 5-2.2 5-5V45c0-2.8-2.2-5-5-5H45z"/>
      </svg>
    ),
    color: 'text-[#199ED9]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  sendmarc: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#00C389" d="M50 5C25.1 5 5 25.1 5 50s20.1 45 45 45 45-20.1 45-45S74.9 5 50 5zm20 60L50 50 30 65V35l20 15 20-15v30z"/>
      </svg>
    ),
    color: 'text-[#00C389]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  bitdefender: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#ED1C24" d="M50 5L10 25v30c0 22.1 17.9 40 40 40s40-17.9 40-40V25L50 5zm0 10l30 15v25c0 16.6-13.4 30-30 30S20 71.6 20 55V30l30-15z"/>
        <path fill="#ED1C24" d="M50 35l-15 7.5v12.5c0 8.3 6.7 15 15 15s15-6.7 15-15V42.5L50 35z"/>
      </svg>
    ),
    color: 'text-[#ED1C24]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  itglue: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#0078D4" x="10" y="10" width="80" height="80" rx="8"/>
        <text x="50" y="68" textAnchor="middle" fill="#fff" fontSize="40" fontWeight="bold" fontFamily="Arial">IT</text>
      </svg>
    ),
    color: 'text-[#0078D4]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  sharepoint: {
    icon: (
      <svg viewBox="0 0 96 96" className="h-8 w-8">
        <circle cx="48" cy="28" r="24" fill="#036C70"/>
        <circle cx="28" cy="60" r="20" fill="#1A9BA1"/>
        <circle cx="60" cy="68" r="16" fill="#37C6D0"/>
      </svg>
    ),
    color: 'text-[#036C70]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  librenms: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#2D7D2D" x="10" y="10" width="80" height="80" rx="10"/>
        <path fill="#fff" d="M25 70V30l15 20 10-15 10 15 15-20v40H25z"/>
      </svg>
    ),
    color: 'text-[#2D7D2D]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  barracuda: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#00A3E0" d="M10 50c0-22.1 17.9-40 40-40s40 17.9 40 40-17.9 40-40 40-40-17.9-40-40z"/>
        <path fill="#fff" d="M30 40h40l-20 30-20-30z"/>
      </svg>
    ),
    color: 'text-[#00A3E0]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  kemp: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#5C2D91" x="10" y="10" width="80" height="80" rx="8"/>
        <text x="50" y="65" textAnchor="middle" fill="#fff" fontSize="32" fontWeight="bold" fontFamily="Arial">K</text>
      </svg>
    ),
    color: 'text-[#5C2D91]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  rocketcyber: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#E31937" d="M50 5L20 20v35c0 22.1 13.4 35 30 40 16.6-5 30-17.9 30-40V20L50 5z"/>
        <path fill="#fff" d="M50 25l-15 10v20c0 11 6.7 17.5 15 20 8.3-2.5 15-9 15-20V35L50 25z"/>
        <path fill="#E31937" d="M45 45l5 15 5-15 10 5-10 5 5 10-10-5-5 10-5-10-10 5 5-10-10-5 10-5z"/>
      </svg>
    ),
    color: 'text-[#E31937]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  threatlocker: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#0098D6" d="M50 5L10 25v30c0 22.1 17.9 40 40 40s40-17.9 40-40V25L50 5z"/>
        <path fill="#fff" d="M50 20L25 32.5v22.5c0 13.8 11.2 25 25 25s25-11.2 25-25V32.5L50 20z"/>
        <rect fill="#0098D6" x="40" y="45" width="20" height="25" rx="3"/>
        <circle fill="#0098D6" cx="50" cy="40" r="8"/>
      </svg>
    ),
    color: 'text-[#0098D6]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  vonahi: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#6366F1" x="10" y="10" width="80" height="80" rx="10"/>
        <path fill="#fff" d="M25 30l25 40 25-40H65L50 55 35 30H25z"/>
      </svg>
    ),
    color: 'text-[#6366F1]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  admindroid: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#00BCD4" x="10" y="10" width="80" height="80" rx="15"/>
        <circle fill="#fff" cx="50" cy="35" r="12"/>
        <path fill="#fff" d="M30 75V60c0-11 9-20 20-20s20 9 20 20v15H30z"/>
      </svg>
    ),
    color: 'text-[#00BCD4]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  watchguard: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#C8102E" d="M50 5L10 25v30c0 22.1 17.9 40 40 40s40-17.9 40-40V25L50 5z"/>
        <path fill="#fff" d="M50 15L20 32v23c0 16.6 13.4 30 30 30s30-13.4 30-30V32L50 15z"/>
        <path fill="#C8102E" d="M40 45h20v20H40z"/>
      </svg>
    ),
    color: 'text-[#C8102E]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  m365: {
    icon: (
      <svg viewBox="0 0 96 96" className="h-8 w-8">
        <path fill="#f25022" d="M1 1h45v45H1z"/>
        <path fill="#7fba00" d="M50 1h45v45H50z"/>
        <path fill="#00a4ef" d="M1 50h45v45H1z"/>
        <path fill="#ffb900" d="M50 50h45v45H50z"/>
      </svg>
    ),
    color: 'text-blue-600',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  saas: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <path fill="#199ED9" d="M80 55c0-11-9-20-20-20-1.5 0-3 .2-4.4.5C53.5 26 45.3 20 35.5 20 23.1 20 13 30.1 13 42.5c0 1.2.1 2.4.3 3.5C6.5 48.5 2 54.8 2 62c0 9.9 8.1 18 18 18h55c8.3 0 15-6.7 15-15 0-7.7-5.8-14-13.3-14.9.2-1.7.3-3.4.3-5.1z"/>
      </svg>
    ),
    color: 'text-[#199ED9]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  veeamone: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#00B336" x="10" y="10" width="80" height="80" rx="8"/>
        <path fill="#fff" d="M25 30l25 40 25-40H65L50 55 35 30H25z"/>
      </svg>
    ),
    color: 'text-[#00B336]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
  cipp: {
    icon: (
      <svg viewBox="0 0 100 100" className="h-8 w-8">
        <rect fill="#0078D4" x="10" y="10" width="80" height="80" rx="10"/>
        <path fill="#fff" d="M30 35c0-8.3 6.7-15 15-15h10c8.3 0 15 6.7 15 15v5H30v-5z"/>
        <path fill="#fff" d="M30 45h40v20c0 8.3-6.7 15-15 15H45c-8.3 0-15-6.7-15-15V45z"/>
        <circle fill="#0078D4" cx="50" cy="55" r="5"/>
      </svg>
    ),
    color: 'text-[#0078D4]',
    bgColor: 'bg-white dark:bg-slate-800',
  },
}

export function getIntegrationIconConfig(type: string): IntegrationIconConfig {
  const normalizedType = type.toLowerCase().replace(/[^a-z0-9]/g, '')
  
  // Try exact match first
  if (integrationConfigs[normalizedType]) {
    return integrationConfigs[normalizedType]
  }
  
  // Try partial matches
  for (const [key, config] of Object.entries(integrationConfigs)) {
    if (normalizedType.includes(key) || key.includes(normalizedType)) {
      return config
    }
  }
  
  // Default fallback
  return {
    icon: <Cable className="h-8 w-8" />,
    color: 'text-primary',
    bgColor: 'bg-primary/10',
  }
}

export function IntegrationIcon({ type, size = 'md' }: { type: string; size?: 'sm' | 'md' | 'lg' }) {
  const config = getIntegrationIconConfig(type)
  const sizeClasses = {
    sm: 'h-6 w-6',
    md: 'h-8 w-8',
    lg: 'h-10 w-10',
  }
  
  return (
    <div className={`${config.bgColor} ${config.color} rounded-lg p-2 flex items-center justify-center`}>
      <div className={sizeClasses[size]}>
        {config.icon}
      </div>
    </div>
  )
}
