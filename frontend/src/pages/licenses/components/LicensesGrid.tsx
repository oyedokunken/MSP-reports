import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Badge } from '@/components/ui/badge'
import { Key, AlertTriangle } from 'lucide-react'
import { LicenseCard } from './LicenseCard'
import type { License, UserLicenseStatus } from '@/stores/useLicensesStore'

interface LicensesGridProps {
  licenses: License[]
  expiringSoon: License[]
  userLicenseStatus: UserLicenseStatus | null
  licenseEnforcementEnabled: boolean
  isLoading: boolean
  error: string | null
}

export function LicensesGrid({ 
  licenses, 
  expiringSoon, 
  userLicenseStatus, 
  licenseEnforcementEnabled, 
  isLoading, 
  error 
}: LicensesGridProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {[1, 2, 3, 4].map((i) => (
          <Card key={i}>
            <CardContent className="pt-6 space-y-4">
              <div className="flex items-center justify-between">
                <Skeleton className="h-6 w-32" />
                <Skeleton className="h-6 w-20" />
              </div>
              <div className="space-y-2">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
              </div>
              <div className="flex items-center justify-between pt-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-4 w-28" />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  if (error) {
    return (
      <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
        <p className="text-sm text-destructive font-medium">{error}</p>
      </div>
    )
  }

  if (licenses.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <Key className="h-12 w-12 text-muted-foreground mb-4" />
          <p className="text-lg font-medium">No Licenses Found</p>
          <p className="text-sm text-muted-foreground">
            Contact support to activate licenses
          </p>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="space-y-6">
      {expiringSoon.length > 0 && (
        <Card className="border-orange-500/30 bg-orange-50 dark:bg-orange-950/20">
          <CardContent className="pt-5 pb-5">
            <div className="flex items-start gap-3">
              <div className="p-1.5 rounded-md bg-orange-500/10">
                <AlertTriangle className="h-4 w-4 text-orange-600 dark:text-orange-400" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-medium text-foreground">Licenses Expiring Soon</p>
                <p className="text-sm text-muted-foreground mt-1">
                  {expiringSoon.length} license{expiringSoon.length > 1 ? 's' : ''} will expire soon. Contact support to renew.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {userLicenseStatus && (
        <Card className="border-border/50">
          <CardContent className="pt-5 pb-5">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-foreground">User Licenses</p>
                <p className="text-sm text-muted-foreground mt-1">
                  {userLicenseStatus.current_users} active user{userLicenseStatus.current_users !== 1 ? 's' : ''}
                  {userLicenseStatus.licensed && ` • ${userLicenseStatus.remaining} remaining`}
                </p>
              </div>
              <div className="flex items-center gap-2">
                {!userLicenseStatus.licensed ? (
                  <Badge variant="outline" className="text-sm border-green-500/50 text-green-600 dark:text-green-400">Unlimited</Badge>
                ) : userLicenseStatus.remaining > 0 ? (
                  <Badge variant="outline" className="text-sm">{userLicenseStatus.remaining} Available</Badge>
                ) : (
                  <Badge variant="destructive" className="text-sm">Limit Reached</Badge>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {!licenseEnforcementEnabled && (
        <Card className="border-blue-500/30 bg-blue-50 dark:bg-blue-950/20">
          <CardContent className="pt-5 pb-5">
            <div className="flex items-start gap-3">
              <div className="p-1.5 rounded-md bg-blue-500/10">
                <Key className="h-4 w-4 text-blue-600 dark:text-blue-400" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-medium text-foreground">License Enforcement Disabled</p>
                <p className="text-sm text-muted-foreground mt-1">
                  License restrictions are not currently enforced. All features are available.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      <div>
        <h3 className="text-lg font-medium mb-4 text-foreground">Active Licenses</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {licenses.map((license) => (
            <LicenseCard key={license.license_id} license={license} />
          ))}
        </div>
      </div>
    </div>
  )
}
