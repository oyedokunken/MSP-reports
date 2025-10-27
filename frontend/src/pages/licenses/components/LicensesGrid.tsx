import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
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
      <div className="flex items-center justify-center h-64">
        <Spinner className="h-8 w-8" />
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
      {/* Expiring Soon Warning */}
      {expiringSoon.length > 0 && (
        <Card className="border-orange-500/50 bg-orange-500/10">
          <CardContent className="pt-6">
            <div className="flex items-start gap-3">
              <AlertTriangle className="h-5 w-5 text-orange-500 mt-0.5" />
              <div>
                <p className="font-medium text-foreground">Licenses Expiring Soon</p>
                <p className="text-sm text-muted-foreground mt-1">
                  {expiringSoon.length} license{expiringSoon.length > 1 ? 's' : ''} will expire soon. Contact support to renew.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* User License Status */}
      {userLicenseStatus && (
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-foreground">User Licenses</p>
                <p className="text-sm text-muted-foreground mt-1">
                  {userLicenseStatus.current_users} active user{userLicenseStatus.current_users !== 1 ? 's' : ''}
                  {userLicenseStatus.licensed && ` • ${userLicenseStatus.remaining} remaining`}
                </p>
              </div>
              <div className="flex items-center gap-2">
                {!userLicenseStatus.licensed ? (
                  <Badge variant="default" className="bg-green-500">Unlimited</Badge>
                ) : userLicenseStatus.remaining > 0 ? (
                  <Badge variant="default">{userLicenseStatus.remaining} Available</Badge>
                ) : (
                  <Badge variant="destructive">Limit Reached</Badge>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* License Enforcement Status */}
      {!licenseEnforcementEnabled && (
        <Card className="border-blue-500/50 bg-blue-500/10">
          <CardContent className="pt-6">
            <div className="flex items-start gap-3">
              <Key className="h-5 w-5 text-blue-500 mt-0.5" />
              <div>
                <p className="font-medium text-foreground">License Enforcement Disabled</p>
                <p className="text-sm text-muted-foreground mt-1">
                  License restrictions are not currently enforced. All features are available.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Active Licenses Grid */}
      <div>
        <h3 className="text-lg font-semibold mb-4">Active Licenses</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {licenses.map((license) => (
            <LicenseCard key={license.license_id} license={license} />
          ))}
        </div>
      </div>
    </div>
  )
}
