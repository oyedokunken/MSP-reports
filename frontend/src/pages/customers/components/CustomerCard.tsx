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
import { FileText, Settings, Link2, MoreVertical, Edit, Cable, GitMerge, GitBranch, Info } from 'lucide-react'
import type { MappedCustomer } from '@/stores/useCustomersStore'

interface CustomerCardProps {
  customer: MappedCustomer
  onSetupReports: () => void
  onViewHistory: () => void
  onEdit: () => void
  onViewIntegrations: () => void
  onMerge: () => void
  onSplit: () => void
  onMoreInfo: () => void
}

export function CustomerCard({
  customer,
  onSetupReports,
  onViewHistory,
  onEdit,
  onViewIntegrations,
  onMerge,
  onSplit,
  onMoreInfo,
}: CustomerCardProps) {
  return (
    <Card className="flex flex-col overflow-hidden">
      <CardHeader className="pb-3 space-y-2">
        {/* Customer Name - Full Width, 2 Lines */}
        <div>
          <CardTitle className="text-base leading-snug line-clamp-2" title={customer.canonical_name || undefined}>
            {customer.canonical_name}
          </CardTitle>
        </div>
        
        {/* Status and Integration Count */}
        <div className="flex items-center gap-2 flex-wrap">
          <Badge 
            variant={customer.status === 'active' ? 'default' : 'secondary'}
            className={`flex-shrink-0 ${customer.status !== 'active' ? 'bg-purple-600 text-white hover:bg-purple-700' : ''}`}
          >
            {customer.status === 'active' ? 'Active' : 'Inactive'}
          </Badge>
          <CardDescription className="text-xs">
            {customer.integrations.length} integration{customer.integrations.length !== 1 ? 's' : ''}
          </CardDescription>
        </div>
      </CardHeader>
      <CardContent className="flex-1 space-y-3">
        {/* Integrations */}
        <div className="space-y-2 overflow-hidden">
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <Link2 className="h-3 w-3 flex-shrink-0" />
            <span>Linked Integrations</span>
          </div>
          <div className="flex flex-wrap gap-1 overflow-hidden">
            {customer.integrations.map((integration) => (
              <Badge key={integration.integration_customer_id} variant="outline" className="text-xs truncate max-w-full whitespace-nowrap" title={integration.integration_type}>
                <span className="truncate">{integration.integration_type}</span>
              </Badge>
            ))}
          </div>
        </div>

        {/* Report Status */}
        <div className={`flex items-center gap-2 text-xs py-2 px-3 rounded-md ${
          customer.report_schedules_count && customer.report_schedules_count > 0
            ? 'bg-green-50 text-green-700 border border-green-200 dark:bg-green-950/30 dark:text-green-400 dark:border-green-900'
            : 'bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-950/30 dark:text-orange-400 dark:border-orange-900'
        }`}>
          <FileText className="h-3 w-3" />
          <span className="font-medium truncate">
            {customer.report_schedules_count && customer.report_schedules_count > 0 
              ? `${customer.report_schedules_count} report${customer.report_schedules_count !== 1 ? 's' : ''} configured`
              : 'No reports configured'}
          </span>
        </div>

        {/* Actions */}
        <div className="flex flex-wrap gap-2 pt-2">
          <Button
            size="sm"
            variant="outline"
            className="flex-1 min-w-[120px] overflow-hidden"
            onClick={onSetupReports}
          >
            <Settings className="h-4 w-4 mr-2 flex-shrink-0" />
            <span className="truncate">Setup Reports</span>
          </Button>
          <Button
            size="sm"
            variant="outline"
            className="flex-1 min-w-[120px] overflow-hidden"
            onClick={onViewHistory}
          >
            <FileText className="h-4 w-4 mr-2 flex-shrink-0" />
            <span className="truncate">View History</span>
          </Button>
          <Button
            size="sm"
            variant="outline"
            className="flex-1 min-w-[120px] overflow-hidden"
            onClick={onMoreInfo}
          >
            <Info className="h-4 w-4 mr-2 flex-shrink-0" />
            <span className="truncate">More Info</span>
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" size="sm" className="px-2 flex-shrink-0">
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
