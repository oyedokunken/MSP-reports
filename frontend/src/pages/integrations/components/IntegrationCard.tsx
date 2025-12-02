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
  onSetSourceOfTruth: (id: number, name: string) => void
  onEdit: (integration: Integration) => void
  onViewLogs: (integration: Integration) => void
  onHealthCheck: (integration: Integration) => void
  onRateLimit: (integration: Integration) => void
  onHistoricalImport: (integration: Integration) => void
  onViewHistoricalJobs: (integration: Integration) => void
  onDelete: (integration: Integration) => void
  onPreviewData: (integration: Integration) => void
  isSourceOfTruth?: boolean
}

export function IntegrationCard ({
  integration,
  isSyncing,
  progress,
  isSettingSourceOfTruth,
  onSync,
  onSetSourceOfTruth,
  onEdit,
  onViewLogs,
  onHealthCheck,
  onRateLimit,
  onHistoricalImport,
  onViewHistoricalJobs,
  onDelete,
  onPreviewData,
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
      } ${isSourceOfTruth ? 'border-2 border-primary' : ''}`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0 flex-1 overflow-hidden">
          <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
            <Cable className="h-6 w-6 text-primary" />
          </div>
          <div className="min-w-0 flex-1 overflow-hidden">
            <div className="flex items-center gap-2">
              <h3 className="font-semibold text-foreground truncate" style={{maxWidth: 'calc(100% - 120px)'}} title={integration.name}>{integration.name}</h3>
              {isSourceOfTruth && (
                <Badge variant="default" className="bg-primary text-primary-foreground hover:bg-primary/90 whitespace-nowrap flex-shrink-0">
                  <Crown className="h-3 w-3 mr-1" />
                  Master List
                </Badge>
              )}
            </div>
            <p className="text-xs text-muted-foreground truncate" title={integration.type}>{integration.type}</p>
          </div>
        </div>
        <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium flex-shrink-0 whitespace-nowrap ${statusInfo.color}`}>
          <span className="truncate">{statusInfo.text}</span>
        </span>
      </div>

      <div className="mt-6 space-y-3">
        <div className="flex items-center justify-between text-sm gap-2">
          <span className="text-muted-foreground flex-shrink-0">Last Sync</span>
          <span className="font-medium text-foreground truncate" title={integration.lastSync}>{integration.lastSync}</span>
        </div>
        {integration.customers > 0 && (
          <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">Unmapped Customers</span>
            <span className="font-medium text-orange-600">
              {integration.customers}
            </span>
          </div>
        )}
        <div className="flex items-center gap-2 pt-2">
          {getStatusIcon(integration.status, integration.syncStatus)}
          <span className="text-sm text-muted-foreground">
            {integration.syncStatus === 'success' 
              ? isSourceOfTruth
                ? 'Synced successfully'
                : 'Auto-synced with master list'
              : integration.syncStatus === 'failed'
              ? 'Sync failed - check logs'
              : integration.lastSync === 'Never'
              ? 'Not synced yet'
              : isSourceOfTruth
              ? 'Ready to sync'
              : 'Syncs automatically with master list'}
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
          {isSourceOfTruth && (
            <button
              onClick={() => onSync(integration.id, integration.name)}
              disabled={isSyncing}
              className={`flex-1 flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-all ${
                isSyncing
                  ? 'bg-primary text-primary-foreground border-primary'
                  : 'border-border bg-background text-foreground hover:bg-muted'
              } disabled:opacity-50 disabled:cursor-not-allowed`}
            >
              {isSyncing ? <LoadingSpinner size="sm" /> : <RefreshCw className="h-4 w-4" />}
              {isSyncing ? 'Syncing...' : 'Sync Now'}
            </button>
          )}
          <button
            onClick={() => onPreviewData(integration)}
            className="flex-1 flex items-center justify-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted transition-all"
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
              {!isSourceOfTruth && (
                <>
                  <DropdownMenuItem onClick={() => onSetSourceOfTruth(integration.id, integration.name)} disabled={isSettingSourceOfTruth}>
                    <Crown className="mr-2 h-4 w-4" />
                    {isSettingSourceOfTruth ? 'Setting as Master...' : 'Set as Master List'}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                </>
              )}
              {!isSourceOfTruth && (
                <DropdownMenuItem onClick={() => onSync(integration.id, integration.name)} disabled={isSyncing}>
                  <RefreshCw className="mr-2 h-4 w-4" />
                  Manual Sync
                </DropdownMenuItem>
              )}
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
              <DropdownMenuItem onClick={() => onViewHistoricalJobs(integration)}>
                <FileText className="mr-2 h-4 w-4" />
                View Import Jobs
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => onDelete(integration)} className="text-destructive">
                <Trash2 className="mr-2 h-4 w-4" />
                Delete Integration
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </AnimatedCard>
  )
}
