import { Cable, Share2, Shield, Server, Database, Cloud, Lock, Activity, Cpu, Network } from 'lucide-react'
import type { ReactNode } from 'react'

type IntegrationIconConfig = {
  icon: ReactNode
  color: string
  bgColor: string
}

const integrationConfigs: Record<string, IntegrationIconConfig> = {
  activedirectory: {
    icon: (
      <svg viewBox="0 0 24 24" fill="currentColor" className="h-8 w-8">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
      </svg>
    ),
    color: 'text-blue-700',
    bgColor: 'bg-blue-100 dark:bg-blue-900/30',
  },
  autotask: {
    icon: (
      <svg viewBox="0 0 24 24" fill="currentColor" className="h-8 w-8">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
      </svg>
    ),
    color: 'text-orange-600',
    bgColor: 'bg-orange-100 dark:bg-orange-900/30',
  },
  datto: {
    icon: (
      <svg viewBox="0 0 24 24" fill="currentColor" className="h-8 w-8">
        <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" fill="none"/>
        <path d="M12 6v6l4 2" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round"/>
      </svg>
    ),
    color: 'text-blue-600',
    bgColor: 'bg-blue-100 dark:bg-blue-900/30',
  },
  sendmarc: {
    icon: <Shield className="h-8 w-8" />,
    color: 'text-green-600',
    bgColor: 'bg-green-100 dark:bg-green-900/30',
  },
  bitdefender: {
    icon: <Shield className="h-8 w-8" />,
    color: 'text-red-600',
    bgColor: 'bg-red-100 dark:bg-red-900/30',
  },
  itglue: {
    icon: <Database className="h-8 w-8" />,
    color: 'text-purple-600',
    bgColor: 'bg-purple-100 dark:bg-purple-900/30',
  },
  sharepoint: {
    icon: <Share2 className="h-8 w-8" />,
    color: 'text-teal-600',
    bgColor: 'bg-teal-100 dark:bg-teal-900/30',
  },
  librenms: {
    icon: <Activity className="h-8 w-8" />,
    color: 'text-emerald-600',
    bgColor: 'bg-emerald-100 dark:bg-emerald-900/30',
  },
  barracuda: {
    icon: <Lock className="h-8 w-8" />,
    color: 'text-cyan-600',
    bgColor: 'bg-cyan-100 dark:bg-cyan-900/30',
  },
  kemp: {
    icon: <Server className="h-8 w-8" />,
    color: 'text-indigo-600',
    bgColor: 'bg-indigo-100 dark:bg-indigo-900/30',
  },
  rocketcyber: {
    icon: (
      <svg viewBox="0 0 24 24" fill="currentColor" className="h-8 w-8">
        <path d="M12 2L4 6v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V6l-8-4z" stroke="currentColor" strokeWidth="2" fill="none"/>
      </svg>
    ),
    color: 'text-rose-600',
    bgColor: 'bg-rose-100 dark:bg-rose-900/30',
  },
  threatlocker: {
    icon: <Lock className="h-8 w-8" />,
    color: 'text-amber-600',
    bgColor: 'bg-amber-100 dark:bg-amber-900/30',
  },
  vonahi: {
    icon: <Network className="h-8 w-8" />,
    color: 'text-violet-600',
    bgColor: 'bg-violet-100 dark:bg-violet-900/30',
  },
  admindroid: {
    icon: <Cpu className="h-8 w-8" />,
    color: 'text-sky-600',
    bgColor: 'bg-sky-100 dark:bg-sky-900/30',
  },
  watchguard: {
    icon: <Shield className="h-8 w-8" />,
    color: 'text-red-500',
    bgColor: 'bg-red-100 dark:bg-red-900/30',
  },
  m365: {
    icon: (
      <svg viewBox="0 0 24 24" className="h-8 w-8">
        <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
        <rect x="13" y="1" width="10" height="10" fill="#7FBA00"/>
        <rect x="1" y="13" width="10" height="10" fill="#00A4EF"/>
        <rect x="13" y="13" width="10" height="10" fill="#FFB900"/>
      </svg>
    ),
    color: 'text-blue-600',
    bgColor: 'bg-gradient-to-br from-blue-50 to-green-50 dark:from-blue-900/20 dark:to-green-900/20',
  },
  saas: {
    icon: <Cloud className="h-8 w-8" />,
    color: 'text-slate-600',
    bgColor: 'bg-slate-100 dark:bg-slate-900/30',
  },
  veeamone: {
    icon: <Server className="h-8 w-8" />,
    color: 'text-green-500',
    bgColor: 'bg-green-100 dark:bg-green-900/30',
  },
  cipp: {
    icon: (
      <svg viewBox="0 0 24 24" className="h-8 w-8">
        <rect x="1" y="1" width="10" height="10" fill="#F25022"/>
        <rect x="13" y="1" width="10" height="10" fill="#7FBA00"/>
        <rect x="1" y="13" width="10" height="10" fill="#00A4EF"/>
        <rect x="13" y="13" width="10" height="10" fill="#FFB900"/>
      </svg>
    ),
    color: 'text-blue-600',
    bgColor: 'bg-gradient-to-br from-orange-50 to-blue-50 dark:from-orange-900/20 dark:to-blue-900/20',
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
