import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Key, Calendar, Package } from 'lucide-react'
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
    return <Badge variant={config.variant}>{config.label}</Badge>
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
    <Card className="border-border/50 hover:border-border transition-colors">
      <CardHeader className="pb-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 mb-2 flex-wrap">
              <CardTitle className="text-lg font-medium">{getLicenseTypeLabel(license.license_type)}</CardTitle>
              {getStatusBadge(license.status)}
            </div>
            <CardDescription className="flex items-center gap-1.5 text-sm">
              <Key className="h-3 w-3" />
              {license.product_sku}
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        {license.metadata?.description && (
          <div className="mb-4 p-3 bg-muted/30 rounded-lg border border-border/50">
            <p className="text-sm text-muted-foreground leading-relaxed">{license.metadata.description}</p>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p className="text-sm text-muted-foreground mb-1.5 flex items-center gap-1">
              <Calendar className="h-3 w-3" />
              Expiry Date
            </p>
            <p className="font-medium">{formatDate(license.expiry_date)}</p>
          </div>
          {license.quantity !== null && (
            <div>
              <p className="text-sm text-muted-foreground mb-1.5 flex items-center gap-1">
                <Package className="h-3 w-3" />
                Quantity
              </p>
              <p className="font-medium">{license.quantity === -1 ? 'Unlimited' : license.quantity}</p>
            </div>
          )}
        </div>

        {license.metadata?.includes && license.metadata.includes.length > 0 && (
          <div className="mb-4">
            <p className="text-sm text-muted-foreground mb-2">Includes</p>
            <div className="flex flex-wrap gap-1.5">
              {formatIncludes(license.metadata.includes)?.map((item, idx) => (
                <Badge key={idx} variant="outline" className="text-sm border-primary/30 text-primary">
                  {item}
                </Badge>
              ))}
            </div>
          </div>
        )}

        {license.metadata && (license.metadata.deployment_type || license.metadata.customer) && (
          <div className="pt-4 border-t border-border/50">
            <div className="grid grid-cols-2 gap-4">
              {license.metadata.deployment_type && (
                <div>
                  <span className="text-sm text-muted-foreground">Deployment</span>
                  <p className="font-medium capitalize mt-1">{license.metadata.deployment_type.replace(/_/g, ' ')}</p>
                </div>
              )}
              {license.metadata.customer && (
                <div>
                  <span className="text-sm text-muted-foreground">Customer</span>
                  <p className="font-medium mt-1">{license.metadata.customer}</p>
                </div>
              )}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
