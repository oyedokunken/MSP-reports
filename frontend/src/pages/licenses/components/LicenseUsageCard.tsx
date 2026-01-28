import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
      <Card className="border-border/50">
        <CardHeader className="pb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="p-2 rounded-lg bg-primary/10">
                <Package className="h-4 w-4 text-primary" />
              </div>
              <CardTitle className="text-base font-medium">Integrations</CardTitle>
            </div>
            {isIntegrationsUnlimited || usage.integrations.active < usage.integrations.licensed ? (
              <CheckCircle className="h-4 w-4 text-green-500" />
            ) : (
              <AlertCircle className="h-4 w-4 text-orange-500" />
            )}
          </div>
        </CardHeader>
        <CardContent className="pt-0">
          <div className="space-y-3">
            {isIntegrationsUnlimited ? (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-semibold tracking-tight">{usage.integrations.active}</span>
                  <Badge variant="outline" className="text-xs border-green-500/50 text-green-600 dark:text-green-400">Unlimited</Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                  Active integration{usage.integrations.active !== 1 ? 's' : ''}
                </p>
              </>
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-semibold tracking-tight">{usage.integrations.active}</span>
                  <span className="text-sm text-muted-foreground">/ {usage.integrations.licensed}</span>
                </div>
                <div className="w-full bg-muted/50 rounded-full h-1.5">
                  <div
                    className={`h-1.5 rounded-full transition-all ${
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

      <Card className="border-border/50">
        <CardHeader className="pb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="p-2 rounded-lg bg-primary/10">
                <Database className="h-4 w-4 text-primary" />
              </div>
              <CardTitle className="text-base font-medium">Data Retention</CardTitle>
            </div>
            <CheckCircle className="h-4 w-4 text-green-500" />
          </div>
        </CardHeader>
        <CardContent className="pt-0">
          <div className="space-y-3">
            {!usage.retention.licensed || usage.retention.max_months === 0 ? (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-semibold tracking-tight">∞</span>
                  <Badge variant="outline" className="text-xs border-green-500/50 text-green-600 dark:text-green-400">Unlimited</Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                  No retention limits
                </p>
              </>
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-semibold tracking-tight">{usage.retention.max_months}</span>
                  <span className="text-sm text-muted-foreground">months</span>
                </div>
                <p className="text-sm text-muted-foreground">
                  Historical data storage
                </p>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      <Card className="border-border/50">
        <CardHeader className="pb-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="p-2 rounded-lg bg-primary/10">
                <Users className="h-4 w-4 text-primary" />
              </div>
              <CardTitle className="text-base font-medium">Users</CardTitle>
            </div>
            {isUsersUnlimited || usage.users.remaining > 0 ? (
              <CheckCircle className="h-4 w-4 text-green-500" />
            ) : (
              <AlertCircle className="h-4 w-4 text-orange-500" />
            )}
          </div>
        </CardHeader>
        <CardContent className="pt-0">
          <div className="space-y-3">
            {isUsersUnlimited ? (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-semibold tracking-tight">{usage.users.current_users}</span>
                  <Badge variant="outline" className="text-xs border-green-500/50 text-green-600 dark:text-green-400">Unlimited</Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                  Active user{usage.users.current_users !== 1 ? 's' : ''}
                </p>
              </>
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl font-semibold tracking-tight">{usage.users.current_users}</span>
                  <span className="text-sm text-muted-foreground">/ {usage.users.max_users}</span>
                </div>
                <div className="w-full bg-muted/50 rounded-full h-1.5">
                  <div
                    className={`h-1.5 rounded-full transition-all ${
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
