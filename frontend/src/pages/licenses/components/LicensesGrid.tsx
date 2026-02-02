import { Skeleton } from '@/components/ui/skeleton'
import { Badge } from '@/components/ui/badge'
import { Key, AlertTriangle, ShieldCheck } from 'lucide-react'
import { LicenseCard } from './LicenseCard'
import type { License } from '@/stores/useLicensesStore'

interface LicensesGridProps {
  licenses: License[]
  expiringSoon: License[]
  licenseEnforcementEnabled: boolean
  isLoading: boolean
  error: string | null
}

export function LicensesGrid({ 
  licenses, 
  expiringSoon, 
  licenseEnforcementEnabled, 
  isLoading, 
  error 
}: LicensesGridProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {[1, 2, 3].map((i) => (
          <div key={i} className="rounded-xl border border-border/50 bg-card p-6 space-y-4 shadow-sm">
            <div className="flex items-center justify-between">
              <Skeleton className="h-6 w-32" />
              <Skeleton className="h-6 w-16 rounded-full" />
            </div>
            <Skeleton className="h-4 w-full" />
            <div className="grid grid-cols-2 gap-4">
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-full" />
            </div>
          </div>
        ))}
      </div>
    )
  }

  if (error) {
    return (
      <div className="rounded-xl border border-destructive/20 bg-destructive/5 p-4 flex items-center gap-3">
        <AlertTriangle className="h-5 w-5 text-destructive" />
        <p className="text-sm text-destructive font-medium">{error}</p>
      </div>
    )
  }

  if (licenses.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-border p-12 text-center bg-card">
        <Key className="mx-auto h-12 w-12 text-muted-foreground" />
        <h3 className="mt-4 text-lg font-semibold text-foreground">No active licenses found</h3>
        <p className="mt-2 text-sm text-muted-foreground max-w-sm mx-auto">
          It looks like you don't have any active license keys registered to your platform. 
          Please contact support to activate your services.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 gap-4">
        {expiringSoon.length > 0 && (
          <div className="rounded-xl border border-orange-200 bg-orange-50/50 dark:bg-orange-950/10 dark:border-orange-900/30 p-4 transition-all hover:bg-orange-50 dark:hover:bg-orange-950/20">
            <div className="flex items-start gap-4">
              <div className="p-2 rounded-lg bg-orange-100 dark:bg-orange-900/50">
                <AlertTriangle className="h-5 w-5 text-orange-600 dark:text-orange-400" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-bold text-foreground">Licenses Expiring Soon</p>
                <p className="text-sm text-muted-foreground mt-1">
                  {expiringSoon.length} license{expiringSoon.length > 1 ? 's' : ''} will expire soon. Contact your account manager to prevent service interruption.
                </p>
              </div>
            </div>
          </div>
        )}

        {!licenseEnforcementEnabled && (
          <div className="rounded-xl border border-blue-200 bg-blue-50/50 dark:bg-blue-950/10 dark:border-blue-900/30 p-4 transition-all hover:bg-blue-50 dark:hover:bg-blue-950/20">
            <div className="flex items-start gap-4">
              <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/50">
                <ShieldCheck className="h-5 w-5 text-blue-600 dark:text-blue-400" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-bold text-foreground">License Enforcement Bypass</p>
                <p className="text-sm text-muted-foreground mt-1">
                  License restrictions are currently globally disabled. All features and tiers are accessible without restriction.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      <div>
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-xl font-bold text-foreground">Active Licenses</h3>
          <Badge variant="outline" className="bg-muted/50 border-border/50">
            {licenses.length} Total
          </Badge>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {licenses.map((license) => (
            <LicenseCard key={license.license_id} license={license} />
          ))}
        </div>
      </div>
    </div>
  )
}
