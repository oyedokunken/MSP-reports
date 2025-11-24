import { AnimatedCard } from "@/components/ui/AnimatedCard";
import {
    Cable,
    RefreshCw,
    Users,
    Crown,
    Settings,
    FileText,
    Activity,
    Gauge,
    Database,
    Trash2,
} from "lucide-react";
import { LoadingSpinner } from "@/components/ui/LoadingStates";
import type { Integration } from "../types/integration";
import { getStatusIcon, getStatusText } from "../utils/statusBadge";
import { Badge } from "@/components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { MoreVertical } from "lucide-react";

type IntegrationCardProps = {
  integration: Integration
  isSyncing: boolean
  progress: number
  isSettingSourceOfTruth: boolean
  onSync: (id: number, name: string) => void
  onViewCustomers: (id: number, name: string) => void
  onSetSourceOfTruth: (id: number, name: string) => void
  onEdit: (integration: Integration) => void
  onViewLogs: (integration: Integration) => void
  onHealthCheck: (integration: Integration) => void
  onRateLimit: (integration: Integration) => void
  onHistoricalImport: (integration: Integration) => void
  onDelete: (integration: Integration) => void
  isSourceOfTruth?: boolean
}

export function IntegrationCard ({
  integration,
  isSyncing,
  progress,
  isSettingSourceOfTruth,
  onSync,
  onViewCustomers,
  onSetSourceOfTruth,
  onEdit,
  onViewLogs,
  onHealthCheck,
  onRateLimit,
  onHistoricalImport,
  onDelete,
  isSourceOfTruth = false,
}: IntegrationCardProps) {
    const statusInfo = getStatusText(
        integration.status,
        integration.syncStatus
    );

  return (
    <AnimatedCard
      hoverLift={!isSyncing}
      className={`p-6 transition-all duration-300 ${
        isSyncing ? 'ring-2 ring-primary/50 shadow-lg bg-primary/5' : ''
      } ${isSourceOfTruth ? 'border-2 border-blue-500 dark:border-blue-400' : ''}`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0 flex-1">
          <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
            <Cable className="h-6 w-6 text-primary" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="font-semibold text-foreground break-words max-w-full">{integration.name}</h3>
              {isSourceOfTruth && (
                <Badge variant="default" className="bg-blue-500 text-white hover:bg-blue-600 whitespace-nowrap flex-shrink-0">
                  <Crown className="h-3 w-3 mr-1" />
                  Master List
                </Badge>
              )}
            </div>
            <p className="text-xs text-muted-foreground truncate">{integration.type}</p>
          </div>
        </div>
        <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium flex-shrink-0 ${statusInfo.color}`}>
          {statusInfo.text}
        </span>
      </div>

      <div className="mt-6 space-y-3">
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Last Sync</span>
          <span className="font-medium text-foreground">{integration.lastSync}</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Unmapped Customers</span>
          <span className={`font-medium ${integration.customers > 0 ? 'text-orange-600' : 'text-foreground'}`}>
            {integration.customers}
          </span>
        </div>
        <div className="flex items-center gap-2 pt-2">
          {getStatusIcon(integration.status, integration.syncStatus)}
          <span className="text-sm text-muted-foreground">
            {integration.syncStatus === 'success' 
              ? 'Synced successfully' 
              : integration.syncStatus === 'failed'
              ? 'Sync failed - check logs'
              : isSourceOfTruth
              ? 'Ready to sync'
              : 'Syncs automatically with source of truth'}
          </span>
        </div>

        {isSyncing && (
          <div className="mt-3 space-y-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted-foreground">Syncing...</span>
              <span className="font-medium text-primary">{Math.round(progress)}%</span>
            </div>
            <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
              <div
                className="bg-primary h-2 rounded-full transition-all duration-300 ease-out"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>
        )}
      </div>

      <div className="mt-4 space-y-2 border-t border-border pt-4">
        <div className="flex gap-2">
          <button
            onClick={() => onSync(integration.id, integration.name)}
            disabled={isSyncing}
            className={`flex-1 flex items-center justify-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium transition-all ${
              isSyncing
                ? 'bg-primary text-primary-foreground border-primary'
                : 'bg-background text-foreground hover:bg-muted'
            } disabled:opacity-50 disabled:cursor-not-allowed`}
          >
            {isSyncing ? <LoadingSpinner size="sm" /> : <RefreshCw className="h-4 w-4" />}
            {isSyncing ? 'Syncing...' : 'Sync Now'}
          </button>
          <button
            onClick={() => onViewCustomers(integration.id, integration.name)}
            className="flex-1 flex items-center justify-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted"
          >
            <Users className="h-4 w-4" />
            View Customers
          </button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" size="sm" className="px-2">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onEdit(integration)}>
                <Settings className="mr-2 h-4 w-4" />
                Edit Settings
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onViewLogs(integration)}>
                <FileText className="mr-2 h-4 w-4" />
                View Sync Logs
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onHealthCheck(integration)}>
                <Activity className="mr-2 h-4 w-4" />
                Health Check
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onRateLimit(integration)}>
                <Gauge className="mr-2 h-4 w-4" />
                Rate Limit Status
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onHistoricalImport(integration)}>
                <Database className="mr-2 h-4 w-4" />
                Import Historical Data
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => onDelete(integration)} className="text-destructive">
                <Trash2 className="mr-2 h-4 w-4" />
                Delete Integration
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        {!isSourceOfTruth && (
          <button
            onClick={() => onSetSourceOfTruth(integration.id, integration.name)}
            disabled={isSettingSourceOfTruth}
            className={`w-full flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-all ${
              isSettingSourceOfTruth
                ? 'border-blue-500 bg-blue-500 text-white dark:border-blue-400 dark:bg-blue-600 cursor-wait'
                : 'border-border bg-background text-foreground hover:bg-muted'
            }`}
          >
            {isSettingSourceOfTruth ? (
              <LoadingSpinner size="sm" className="border-white/30 border-t-white" />
            ) : (
              <Crown className="h-4 w-4" />
            )}
            {isSettingSourceOfTruth ? 'Setting...' : 'Use as Master Customer List'}
          </button>
        )}
      </div>
    </AnimatedCard>
  )
}
