import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { useCallback } from "react";
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
    Clock,
    Share2,
    MoreVertical,
    Zap,
    AlertTriangle,
    RotateCcw,
    XCircle,
} from "lucide-react";
import { LoadingSpinner } from "@/components/ui/LoadingStates";
import type { Integration } from "../types/integration";
import { getStatusIcon, getStatusText } from "../utils/statusBadge";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/contexts/AuthContext";
import { hasPermission } from "@/utils/permissions";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { useCountdown } from "@/hooks/useCountdown";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
type IntegrationCardProps = {
    integration: Integration;
    isSyncing: boolean;
    isTesting?: boolean;
    progress: number;
    isSettingSourceOfTruth: boolean;
    onSync: (id: number, name: string) => void;
    onSetSourceOfTruth: (id: number, name: string) => void;
    onEdit: (integration: Integration) => void;
    onViewLogs: (integration: Integration) => void;
    onHealthCheck: (integration: Integration) => void;
    onRateLimit: (integration: Integration) => void;
    onHistoricalImport: (integration: Integration) => void;
    onViewHistoricalJobs: (integration: Integration) => void;
    onDelete: (integration: Integration) => void;
    onPreviewData: (integration: Integration) => void;
    onTestIntegration: (integration: Integration) => void;
    onRetryHistoricalImport?: (integration: Integration) => void;
    onCancelHistoricalImport?: (integration: Integration) => void;
    isSourceOfTruth?: boolean;
};
export function IntegrationCard({
    integration,
    isSyncing,
    isTesting = false,
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
    onTestIntegration,
    onRetryHistoricalImport,
    onCancelHistoricalImport,
    isSourceOfTruth = false,
}: IntegrationCardProps) {
    const { user } = useAuth();
    const canManageSlice = hasPermission(user, 'integrations', 'manage');
    const canSyncSlice = hasPermission(user, 'integrations', 'sync');

    const getIntegrationIcon = (type: string) => {
        const normalizeType = type.toLowerCase();
        if (normalizeType.includes('sharepoint')) {
            return <Share2 className="h-6 w-6 text-blue-600" />;
        }
        return <Cable className="h-6 w-6 text-primary" />;
    };
    const statusInfo = getStatusText(
        integration.status,
        integration.syncStatus
    );

    const calculateETA = (progressPercent: number): string => {
        if (progressPercent <= 0 || progressPercent >= 100) return '';
        // Estimate based on typical import speed - rough calculation
        const remainingPercent = 100 - progressPercent;
        const estimatedMinutes = Math.ceil(remainingPercent * 0.5); // ~30 seconds per percent
        if (estimatedMinutes > 60) {
            const hours = Math.floor(estimatedMinutes / 60);
            const mins = estimatedMinutes % 60;
            return `~${hours}h ${mins}m`;
        }
        return `~${estimatedMinutes}m`;
    };
    const handleCountdownComplete = useCallback(() => {
    }, []);
    const countdown = useCountdown(
        integration.nextSyncAt ?? null,
        handleCountdownComplete
    );
    return (
        <TooltipProvider>
            <AnimatedCard
                hoverLift={!isSyncing}
                className={`p-6 h-full flex flex-col transition-all duration-300 ${isSyncing ? "ring-2 ring-primary/50 shadow-lg bg-primary/5" : ""
                    } ${isSourceOfTruth ? "border-2 border-primary" : ""}`}
            >
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                            {getIntegrationIcon(integration.type)}
                        </div>
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <h3
                                    className="font-semibold text-foreground text-lg leading-none"
                                    title={integration.name}
                                >
                                    {integration.name}
                                </h3>
                                {isSourceOfTruth && (
                                    <Badge
                                        variant="default"
                                        className="bg-primary text-primary-foreground hover:bg-primary/90 whitespace-nowrap h-5 px-1.5"
                                    >
                                        <Crown className="h-3 w-3 mr-1" />
                                        Master List
                                    </Badge>
                                )}
                            </div>
                            <p
                                className="text-xs text-muted-foreground"
                                title={integration.type}
                            >
                                {integration.type}
                            </p>
                        </div>
                    </div>
                    <span
                        className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium whitespace-nowrap ${statusInfo.color}`}
                    >
                        {statusInfo.text}
                    </span>
                </div>
                <div className="mt-6 space-y-3 flex-1">
                    {integration.status !== 'importing_historical' && (
                        <div className="flex items-center justify-between text-sm gap-2">
                            <span className="text-muted-foreground flex-shrink-0">
                                Last Sync
                            </span>
                            <span
                                className="font-medium text-foreground truncate"
                                title={integration.lastSync}
                            >
                                {integration.lastSync}
                            </span>
                        </div>
                    )}
                    {!!integration.unmappedCustomers && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Unmapped Customers
                            </span>
                            <span className="font-medium text-orange-600">
                                {integration.unmappedCustomers}
                            </span>
                        </div>
                    )}
                    {integration.nextSyncAt && (
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground flex items-center gap-1">
                                <Clock className="h-3.5 w-3.5" />
                                Next Sync
                            </span>
                            <span className="font-medium text-primary">
                                {countdown}
                            </span>
                        </div>
                    )}
                    <div className="flex items-center gap-2 pt-2">
                        {getStatusIcon(integration.status, integration.syncStatus)}
                        <span className="text-sm text-muted-foreground flex-1 truncate">
                            {integration.status === 'importing_historical'
                                ? "Importing historical data..."
                                : integration.syncStatus === "success"
                                    ? isSourceOfTruth
                                        ? `Synced ${integration.recordsSynced} records`
                                        : "Auto-synced with master list"
                                    : integration.syncStatus === "failed"
                                        ? `Failed: ${integration.lastError || "Check logs"}`
                                        : integration.lastSync === "Never"
                                            ? "Awaiting first sync"
                                            : isSourceOfTruth
                                                ? "Ready to sync"
                                                : "Syncs automatically with master list"}
                        </span>
                    </div>
                    {(isSyncing || integration.status === 'importing_historical') && (
                        <div className="mt-3 space-y-2">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-muted-foreground">
                                    {integration.status === 'importing_historical' ? 'Importing Historical Data...' : 'Syncing...'}
                                </span>
                                <div className="flex items-center gap-2">
                                    {integration.status === 'importing_historical' && (integration.historicalImportProgress || 0) > 0 && (
                                        <span className="text-muted-foreground">
                                            {calculateETA(integration.historicalImportProgress || 0)}
                                        </span>
                                    )}
                                    <span className="font-medium text-primary">
                                        {Math.round(integration.status === 'importing_historical' ? (integration.historicalImportProgress || 0) : progress)}%
                                    </span>
                                </div>
                            </div>
                            <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div
                                    className="bg-primary h-2 rounded-full transition-all duration-300 ease-out"
                                    style={{ width: `${integration.status === 'importing_historical' ? (integration.historicalImportProgress || 0) : progress}%` }}
                                />
                            </div>
                        </div>
                    )}
                    {/* Failed Historical Import Actions */}
                    {integration.historicalImportStatus === 'failed' && (
                        <div className="mt-3 p-3 rounded-lg border border-destructive/50 bg-destructive/10">
                            <div className="flex items-center gap-2 text-destructive text-sm font-medium mb-2">
                                <AlertTriangle className="h-4 w-4" />
                                Historical Import Failed
                            </div>
                            <div className="flex gap-2">
                                {onRetryHistoricalImport && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onRetryHistoricalImport(integration)}
                                        className="flex-1"
                                    >
                                        <RotateCcw className="h-3 w-3 mr-1" />
                                        Retry
                                    </Button>
                                )}
                                {onCancelHistoricalImport && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onCancelHistoricalImport(integration)}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <XCircle className="h-3 w-3 mr-1" />
                                        Dismiss
                                    </Button>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => onViewHistoricalJobs(integration)}
                                >
                                    <FileText className="h-3 w-3 mr-1" />
                                    View Logs
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Test Integration Button - Full Width */}
                <div className="mt-4 border-t border-border pt-4">
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={() => onTestIntegration(integration)}
                        disabled={isTesting || isSyncing}
                    >
                        {isTesting ? (
                            <>
                                <LoadingSpinner size="sm" />
                                <span className="ml-2">Testing...</span>
                            </>
                        ) : (
                            <>
                                <Zap className="h-4 w-4 mr-2" />
                                Test Integration
                            </>
                        )}
                    </Button>
                </div>

                <div className="mt-4 border-t border-border pt-4 flex items-center gap-2">
                    {canSyncSlice && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    onClick={() =>
                                        onSync(integration.id, integration.name)
                                    }
                                    disabled={isSyncing || integration.status === 'importing_historical'}
                                    className={`flex-1 flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-all h-9 ${isSyncing
                                        ? "bg-primary text-primary-foreground border-primary"
                                        : "border-border bg-background text-foreground hover:bg-muted"
                                        } disabled:opacity-50 disabled:cursor-not-allowed`}
                                >
                                    {isSyncing ? (
                                        <LoadingSpinner size="sm" />
                                    ) : (
                                        <RefreshCw className="h-4 w-4" />
                                    )}
                                    {isSyncing ? "Syncing..." : integration.status === 'importing_historical' ? "Importing..." : "Sync Now"}
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {integration.status === 'importing_historical'
                                    ? "Cannot sync while importing historical data"
                                    : "Sync Now"}
                            </TooltipContent>
                        </Tooltip>
                    )}

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => onPreviewData(integration)}
                                className="h-9 w-9 shrink-0"
                            >
                                <Users className="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            View Customers
                        </TooltipContent>
                    </Tooltip>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                className="h-9 w-9 shrink-0"
                            >
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            {!isSourceOfTruth && canManageSlice && (
                                <>
                                    <DropdownMenuItem
                                        onClick={() =>
                                            onSetSourceOfTruth(
                                                integration.id,
                                                integration.name
                                            )
                                        }
                                        disabled={isSettingSourceOfTruth}
                                    >
                                        <Crown className="mr-2 h-4 w-4" />
                                        {isSettingSourceOfTruth
                                            ? "Setting as Master..."
                                            : "Set as Master List"}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                </>
                            )}
                            {canManageSlice && (
                                <DropdownMenuItem
                                    onClick={() => onEdit(integration)}
                                >
                                    <Settings className="mr-2 h-4 w-4" />
                                    Edit Settings
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                                onClick={() => onViewLogs(integration)}
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                View Sync Logs
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => onHealthCheck(integration)}
                            >
                                <Activity className="mr-2 h-4 w-4" />
                                Health Check
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onClick={() => onRateLimit(integration)}
                            >
                                <Gauge className="mr-2 h-4 w-4" />
                                Rate Limit Status
                            </DropdownMenuItem>
                            {canSyncSlice && (
                                <DropdownMenuItem
                                    onClick={() => onHistoricalImport(integration)}
                                >
                                    <Database className="mr-2 h-4 w-4" />
                                    Import Historical Data
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                                onClick={() =>
                                    onViewHistoricalJobs(integration)
                                }
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                View Import Jobs
                            </DropdownMenuItem>
                            {canManageSlice && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() => onDelete(integration)}
                                        className="text-destructive"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Integration
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </AnimatedCard>
        </TooltipProvider>
    );
}
