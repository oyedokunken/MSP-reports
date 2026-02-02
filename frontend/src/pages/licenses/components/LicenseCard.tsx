import { Badge } from '@/components/ui/badge'
import { Key, Calendar, Package } from 'lucide-react'
import { AnimatedCard } from '@/components/ui/AnimatedCard'
import type { License } from '@/stores/useLicensesStore'

interface LicenseCardProps {
  license: License
}

export function LicenseCard({ license }: LicenseCardProps) {
  const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive', label: string }> = {
      active: { variant: 'default', label: 'Active' },
      expired: { variant: 'destructive', label: 'Expired' },
      suspended: { variant: 'secondary', label: 'Suspended' },
    }
    const config = variants[status] || { variant: 'secondary', label: status }
    return <Badge variant={config.variant} className="text-[10px] h-4 px-1.5 uppercase tracking-wider">{config.label}</Badge>
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never expires'
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  }

  const getLicenseTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      integration: 'Integration License',
      retention: 'Data Retention',
      users: 'User Licenses',
      reports: 'Report Types',
      unlimited: 'Unlimited License',
    }
    return labels[type] || type
  }

  const formatIncludes = (includes?: string[]) => {
    if (!includes || includes.length === 0) return null
    
    const labels: Record<string, string> = {
      all_integrations: 'All Integrations',
      unlimited_retention: 'Unlimited Retention',
      unlimited_users: 'Unlimited Users',
      unlimited_reports: 'Unlimited Reports',
    }
    
    return includes.map(item => labels[item] || item)
  }

  return (
    <AnimatedCard 
      className="p-0 overflow-hidden flex flex-col group"
      hoverScale={true}
    >
      <div className="p-5 flex-1">
        <div className="flex items-start justify-between gap-3 mb-4">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 mb-1.5 flex-wrap">
              <h3 className="text-base font-bold text-foreground">{getLicenseTypeLabel(license.license_type)}</h3>
              {getStatusBadge(license.status)}
            </div>
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">
              <Key className="h-3 w-3" />
              {license.product_sku}
            </div>
          </div>
        </div>

        {license.metadata?.description && (
          <div className="mb-4 p-3 bg-muted/30 rounded-lg border border-border/50">
            <p className="text-xs text-muted-foreground leading-relaxed">{license.metadata.description}</p>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-1 flex items-center gap-1">
              <Calendar className="h-3 w-3" />
              Expiry
            </p>
            <p className="text-sm font-medium text-foreground">{formatDate(license.expiry_date)}</p>
          </div>
          {license.quantity !== null && (
            <div>
              <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-1 flex items-center gap-1">
                <Package className="h-3 w-3" />
                Quota
              </p>
              <p className="text-sm font-medium text-foreground">{license.quantity === -1 ? 'Unlimited' : license.quantity}</p>
            </div>
          )}
        </div>

        {license.metadata?.includes && license.metadata.includes.length > 0 && (
          <div className="space-y-2">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Includes</p>
            <div className="flex flex-wrap gap-1.5">
              {formatIncludes(license.metadata.includes)?.map((item, idx) => (
                <Badge key={idx} variant="outline" className="text-[10px] border-primary/20 bg-primary/5 text-primary">
                  {item}
                </Badge>
              ))}
            </div>
          </div>
        )}
      </div>

      {(license.metadata?.deployment_type || license.metadata?.customer) && (
        <div className="px-5 py-3 bg-muted/20 border-t border-border/50 mt-auto">
          <div className="grid grid-cols-2 gap-4">
            {license.metadata.deployment_type && (
              <div>
                <span className="text-[10px] font-medium uppercase text-muted-foreground">Deployment</span>
                <p className="text-xs font-semibold text-foreground capitalize mt-0.5">{license.metadata.deployment_type.replace(/_/g, ' ')}</p>
              </div>
            )}
            {license.metadata.customer && (
              <div>
                <span className="text-[10px] font-medium uppercase text-muted-foreground">Customer</span>
                <p className="text-xs font-semibold text-foreground mt-0.5">{license.metadata.customer}</p>
              </div>
            )}
          </div>
        </div>
      )}
    </AnimatedCard>
  )
}
