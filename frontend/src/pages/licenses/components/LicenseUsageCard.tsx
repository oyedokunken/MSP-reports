import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Package, Users, Database, CheckCircle, AlertCircle } from 'lucide-react'
import type { LicenseUsage } from '@/stores/useLicensesStore'

interface LicenseUsageCardProps {
  usage: LicenseUsage | null
}

export function LicenseUsageCard({ usage }: LicenseUsageCardProps) {
  if (!usage) return null

  const isIntegrationsUnlimited = usage.integrations.licensed === 0
  const integrationPercentage = !isIntegrationsUnlimited && usage.integrations.licensed > 0
    ? (usage.integrations.active / usage.integrations.licensed) * 100
    : 0

  const isUsersUnlimited = !usage.users.licensed
  const userPercentage = usage.users.licensed && usage.users.max_users > 0
    ? (usage.users.current_users / usage.users.max_users) * 100
    : 0

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      {/* Integrations Usage */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardTitle className="text-base flex items-center gap-2">
              <Package className="h-4 w-4" />
              Integrations
            </CardTitle>
            {isIntegrationsUnlimited || usage.integrations.active < usage.integrations.licensed ? (
              <CheckCircle className="h-4 w-4 text-green-500" />
            ) : (
              <AlertCircle className="h-4 w-4 text-orange-500" />
            )}
          </div>
          <CardDescription>Active integrations</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {isIntegrationsUnlimited ? (
              <>
                <Badge variant="default" className="bg-green-500">Unlimited</Badge>
                <p className="text-sm text-muted-foreground mt-2">
                  {usage.integrations.active} active integration{usage.integrations.active !== 1 ? 's' : ''}
                </p>
              </>
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-bold">{usage.integrations.active}</span>
                  <span className="text-muted-foreground">/ {usage.integrations.licensed}</span>
                </div>
                <div className="w-full bg-muted rounded-full h-2">
                  <div
                    className={`h-2 rounded-full transition-all ${
                      integrationPercentage >= 90 ? 'bg-orange-500' : 'bg-primary'
                    }`}
                    style={{ width: `${integrationPercentage}%` }}
                  />
                </div>
                <p className="text-sm text-muted-foreground">
                  {usage.integrations.licensed - usage.integrations.active} available
                </p>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Retention */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardTitle className="text-base flex items-center gap-2">
              <Database className="h-4 w-4" />
              Data Retention
            </CardTitle>
            <CheckCircle className="h-4 w-4 text-green-500" />
          </div>
          <CardDescription>Historical data storage</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {!usage.retention.licensed || usage.retention.max_months === 0 ? (
              <>
                <Badge variant="default" className="bg-green-500">Unlimited</Badge>
                <p className="text-sm text-muted-foreground mt-2">
                  No retention limits
                </p>
              </>
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-bold">{usage.retention.max_months}</span>
                  <span className="text-muted-foreground">months</span>
                </div>
                <p className="text-sm text-muted-foreground">
                  Data retained for {usage.retention.max_months} months
                </p>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Users */}
      <Card>
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <CardTitle className="text-base flex items-center gap-2">
              <Users className="h-4 w-4" />
              Users
            </CardTitle>
            {isUsersUnlimited || usage.users.remaining > 0 ? (
              <CheckCircle className="h-4 w-4 text-green-500" />
            ) : (
              <AlertCircle className="h-4 w-4 text-orange-500" />
            )}
          </div>
          <CardDescription>Active user accounts</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {isUsersUnlimited ? (
              <>
                <Badge variant="default" className="bg-green-500">Unlimited</Badge>
                <p className="text-sm text-muted-foreground mt-2">
                  {usage.users.current_users} user{usage.users.current_users !== 1 ? 's' : ''} active
                </p>
              </>
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-bold">{usage.users.current_users}</span>
                  <span className="text-muted-foreground">/ {usage.users.max_users}</span>
                </div>
                <div className="w-full bg-muted rounded-full h-2">
                  <div
                    className={`h-2 rounded-full transition-all ${
                      userPercentage >= 90 ? 'bg-orange-500' : 'bg-primary'
                    }`}
                    style={{ width: `${userPercentage}%` }}
                  />
                </div>
                <p className="text-sm text-muted-foreground">
                  {usage.users.remaining} available
                </p>
              </>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
