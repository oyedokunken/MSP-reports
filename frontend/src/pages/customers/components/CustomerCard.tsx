import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { FileText, Settings, Link2, MoreVertical, Edit, Cable, GitMerge, GitBranch } from 'lucide-react'
import type { MappedCustomer } from '@/stores/useCustomersStore'

interface CustomerCardProps {
  customer: MappedCustomer
  onSetupReports: () => void
  onViewHistory: () => void
  onEdit: () => void
  onViewIntegrations: () => void
  onMerge: () => void
  onSplit: () => void
}

export function CustomerCard({
  customer,
  onSetupReports,
  onViewHistory,
  onEdit,
  onViewIntegrations,
  onMerge,
  onSplit,
}: CustomerCardProps) {
  return (
    <Card className="flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <CardTitle className="text-base truncate">
              {customer.canonical_name}
            </CardTitle>
            <CardDescription className="text-xs mt-1">
              {customer.integrations.length} integration{customer.integrations.length !== 1 ? 's' : ''}
            </CardDescription>
          </div>
          <Badge variant={customer.status === 'active' ? 'default' : 'secondary'}>
            {customer.status === 'active' ? 'Active' : 'Inactive'}
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="flex-1 space-y-3">
        {/* Integrations */}
        <div className="space-y-2">
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <Link2 className="h-3 w-3" />
            <span>Linked Integrations</span>
          </div>
          <div className="flex flex-wrap gap-1">
            {customer.integrations.map((integration) => (
              <Badge key={integration.integration_customer_id} variant="outline" className="text-xs">
                {integration.integration_type}
              </Badge>
            ))}
          </div>
        </div>

        {/* Report Status */}
        <div className={`flex items-center gap-2 text-xs py-2 px-3 rounded-md ${
          customer.report_schedules_count && customer.report_schedules_count > 0
            ? 'bg-green-50 text-green-700 border border-green-200'
            : 'bg-orange-50 text-orange-700 border border-orange-200'
        }`}>
          <FileText className="h-3 w-3" />
          <span className="font-medium">
            {customer.report_schedules_count && customer.report_schedules_count > 0 
              ? `${customer.report_schedules_count} report${customer.report_schedules_count !== 1 ? 's' : ''} configured`
              : 'No reports configured'}
          </span>
        </div>

        {/* Actions */}
        <div className="flex gap-2 pt-2">
          <Button
            size="sm"
            variant="outline"
            className="flex-1"
            onClick={onSetupReports}
          >
            <Settings className="h-4 w-4 mr-2" />
            Setup Reports
          </Button>
          <Button
            size="sm"
            variant="outline"
            className="flex-1"
            onClick={onViewHistory}
          >
            <FileText className="h-4 w-4 mr-2" />
            View History
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" size="sm" className="px-2">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={onEdit}>
                <Edit className="mr-2 h-4 w-4" />
                Edit Customer
              </DropdownMenuItem>
              <DropdownMenuItem onClick={onViewIntegrations}>
                <Cable className="mr-2 h-4 w-4" />
                View Integrations
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={onMerge}>
                <GitMerge className="mr-2 h-4 w-4" />
                Merge Customer
              </DropdownMenuItem>
              <DropdownMenuItem onClick={onSplit}>
                <GitBranch className="mr-2 h-4 w-4" />
                Split Customer
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardContent>
    </Card>
  )
}
