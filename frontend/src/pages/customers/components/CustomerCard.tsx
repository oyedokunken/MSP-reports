import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { FileText, Settings, MoreVertical, Edit, Cable, GitMerge, GitBranch, Info, Building2, CheckCircle, XCircle, Send } from 'lucide-react'
import { AnimatedCard } from '@/components/ui/AnimatedCard'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'
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
  onApplyTemplate: () => void
  onConfigureDelivery: () => void
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
  onApplyTemplate,
  onConfigureDelivery,
}: CustomerCardProps) {
  const { user } = useAuth()
  const hasReports = customer.report_schedules_count && customer.report_schedules_count > 0
  const canConfigureReports = hasPermission(user, 'reports', 'configure')
  const canEditCustomers = hasPermission(user, 'customers', 'edit')

  return (
    <AnimatedCard className="p-6 h-full flex flex-col">
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0 flex-1">
          <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
            <Building2 className="h-6 w-6 text-primary" />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="font-semibold text-foreground break-words">{customer.canonical_name}</h3>
            <p className="text-xs text-muted-foreground truncate">
              {customer.integrations.length} integration{customer.integrations.length !== 1 ? 's' : ''}
            </p>
          </div>
        </div>
        <Badge
          variant={customer.status === 'active' ? 'default' : 'secondary'}
          className={`flex-shrink-0 ${customer.status !== 'active' ? 'bg-purple-600 text-white hover:bg-purple-700' : ''}`}
        >
          {customer.status === 'active' ? 'Active' : 'Inactive'}
        </Badge>
      </div>

      <div className="mt-6 space-y-3">
        <div className="flex items-center gap-2 text-sm">
          <Cable className="h-4 w-4 text-muted-foreground flex-shrink-0" />
          <div className="flex flex-wrap gap-1 overflow-hidden">
            {customer.integrations.map((integration, index) => (
              <span key={integration.integration_customer_id} className="text-foreground">
                {integration.integration_type}{index < customer.integrations.length - 1 ? ',' : ''}
              </span>
            ))}
          </div>
        </div>

        <div className="flex items-center gap-2 pt-2">
          {hasReports ? (
            <CheckCircle className="h-4 w-4 text-green-600 flex-shrink-0" />
          ) : (
            <XCircle className="h-4 w-4 text-orange-600 flex-shrink-0" />
          )}
          <span className={`text-sm ${hasReports ? 'text-green-600' : 'text-orange-600'
            }`}>
            {hasReports
              ? `${customer.report_schedules_count} report${customer.report_schedules_count !== 1 ? 's' : ''} configured`
              : 'No reports configured'}
          </span>
        </div>
      </div>

      <div className="mt-4 border-t border-border pt-4 space-y-2">
        <TooltipProvider>
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                onClick={canConfigureReports ? onSetupReports : undefined}
                disabled={!canConfigureReports}
                className={`w-full flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all ${canConfigureReports
                  ? 'bg-primary text-primary-foreground hover:bg-primary/90 cursor-pointer'
                  : 'bg-muted text-muted-foreground cursor-not-allowed opacity-60'
                  }`}
              >
                <Settings className="h-4 w-4" />
                Setup Reports
              </button>
            </TooltipTrigger>
            {!canConfigureReports && (
              <TooltipContent>
                <p>You need <strong>reports.configure</strong> permission</p>
              </TooltipContent>
            )}
          </Tooltip>
        </TooltipProvider>

        <div className="flex gap-2">
          <button
            onClick={onMoreInfo}
            className="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-2 py-2 text-xs font-medium text-foreground hover:bg-muted transition-all"
          >
            <Info className="h-3.5 w-3.5 flex-shrink-0" />
            <span>Info</span>
          </button>
          <button
            onClick={onViewHistory}
            className="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-2 py-2 text-xs font-medium text-foreground hover:bg-muted transition-all"
          >
            <FileText className="h-3.5 w-3.5 flex-shrink-0" />
            <span>History</span>
          </button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button className="flex items-center justify-center rounded-lg border border-border bg-background px-3 py-2 text-foreground hover:bg-muted transition-all">
                <MoreVertical className="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {canEditCustomers && (
                <DropdownMenuItem onClick={onEdit}>
                  <Edit className="mr-2 h-4 w-4" />
                  Edit Customer
                </DropdownMenuItem>
              )}
              <DropdownMenuItem onClick={onViewIntegrations}>
                <Cable className="mr-2 h-4 w-4" />
                View Integrations
              </DropdownMenuItem>
              {canConfigureReports && (
                <>
                  <DropdownMenuItem onClick={onApplyTemplate}>
                    <FileText className="mr-2 h-4 w-4" />
                    Apply Report Template
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={onConfigureDelivery}>
                    <Send className="mr-2 h-4 w-4" />
                    Delivery Settings
                  </DropdownMenuItem>
                </>
              )}
              {canEditCustomers && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={onMerge}>
                    <GitMerge className="mr-2 h-4 w-4" />
                    Merge Customer
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={onSplit}>
                    <GitBranch className="mr-2 h-4 w-4" />
                    Split Customer
                  </DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </AnimatedCard>
  )
}
