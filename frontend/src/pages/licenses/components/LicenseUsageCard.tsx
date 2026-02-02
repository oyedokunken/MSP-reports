import { Package, Users, Database } from 'lucide-react'
import { AnimatedStatCard } from '@/components/ui/AnimatedCard'
import type { LicenseUsage } from '@/stores/useLicensesStore'

interface LicenseUsageCardProps {
  usage: LicenseUsage | null
}

export function LicenseUsageCard({ usage }: LicenseUsageCardProps) {
  if (!usage) return null

  const isIntegrationsUnlimited = usage.integrations.licensed === 0
  const integrationValue = isIntegrationsUnlimited 
    ? usage.integrations.active.toString() 
    : `${usage.integrations.active} / ${usage.integrations.licensed}`

  const isUsersUnlimited = !usage.users.licensed
  const userValue = isUsersUnlimited 
    ? usage.users.current_users.toString() 
    : `${usage.users.current_users} / ${usage.users.max_users}`

  const retentionValue = !usage.retention.licensed || usage.retention.max_months === 0
    ? 'Unlimited'
    : `${usage.retention.max_months} Months`

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
      <AnimatedStatCard
        name="Integrations"
        value={integrationValue}
        icon={Package}
        trend={isIntegrationsUnlimited ? { value: 'Unlimited', isPositive: true } : undefined}
      />
      <AnimatedStatCard
        name="Data Retention"
        value={retentionValue}
        icon={Database}
      />
      <AnimatedStatCard
        name="Active Users"
        value={userValue}
        icon={Users}
        trend={isUsersUnlimited ? { value: 'Unlimited', isPositive: true } : undefined}
      />
    </div>
  )
}
