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
    <Card>
      <CardHeader>
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <CardTitle className="text-lg">{getLicenseTypeLabel(license.license_type)}</CardTitle>
              {getStatusBadge(license.status)}
            </div>
            <CardDescription className="flex items-center gap-2">
              <Key className="h-4 w-4" />
              SKU: {license.product_sku}
            </CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {license.metadata?.description && (
          <div className="mb-4 p-3 bg-muted/50 rounded-md">
            <p className="text-sm text-foreground">{license.metadata.description}</p>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4 text-sm mb-4">
          <div>
            <p className="text-muted-foreground mb-1 flex items-center gap-1">
              <Calendar className="h-3 w-3" />
              Expiry Date
            </p>
            <p className="font-medium">{formatDate(license.expiry_date)}</p>
          </div>
          {license.quantity !== null && (
            <div>
              <p className="text-muted-foreground mb-1 flex items-center gap-1">
                <Package className="h-3 w-3" />
                Quantity
              </p>
              <p className="font-medium">{license.quantity === -1 ? 'Unlimited' : license.quantity}</p>
            </div>
          )}
        </div>

        {license.metadata?.includes && license.metadata.includes.length > 0 && (
          <div className="mb-4">
            <p className="text-xs text-muted-foreground mb-2">Includes:</p>
            <div className="flex flex-wrap gap-2">
              {formatIncludes(license.metadata.includes)?.map((item, idx) => (
                <Badge key={idx} variant="secondary" className="text-xs">
                  {item}
                </Badge>
              ))}
            </div>
          </div>
        )}

        {license.metadata && (
          <div className="pt-4 border-t border-border">
            <div className="grid grid-cols-2 gap-3 text-xs">
              {license.metadata.deployment_type && (
                <div>
                  <span className="text-muted-foreground">Deployment:</span>
                  <p className="font-medium capitalize mt-1">{license.metadata.deployment_type.replace(/_/g, ' ')}</p>
                </div>
              )}
              {license.metadata.customer && (
                <div>
                  <span className="text-muted-foreground">Customer:</span>
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
