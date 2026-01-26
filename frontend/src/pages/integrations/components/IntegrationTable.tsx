import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from "@/components/ui/table";
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
    MoreVertical,
    Share2,
    Clock
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { Skeleton } from "@/components/ui/skeleton";
import { Card } from "@/components/ui/card";
import { EmptyState, LoadingSpinner } from "@/components/ui/LoadingStates";
import type { Integration } from "../types/integration";
import { getStatusText } from "../utils/statusBadge";
import { cn } from "@/lib/utils";

type IntegrationTableProps = {
    isLoading: boolean;
    items: Integration[];
    syncingId: number | null;
    progress: number;
    settingSourceOfTruthId: number | null;
    onSync: (id: number, name: string) => void;
    onEmptyAction: () => void;
    onSetSourceOfTruth: (id: number, name: string) => void;
    onEdit: (integration: Integration) => void;
    onViewLogs: (integration: Integration) => void;
    onHealthCheck: (integration: Integration) => void;
    onRateLimit: (integration: Integration) => void;
    onHistoricalImport: (integration: Integration) => void;
    onViewHistoricalJobs: (integration: Integration) => void;
    onDelete: (integration: Integration) => void;
    onPreviewData: (integration: Integration) => void;
    sourceOfTruthId: number | null;
};

export function IntegrationTable({
    isLoading,
    items,
    syncingId,
    progress,
    settingSourceOfTruthId,
    onSync,
    onEmptyAction,
    onSetSourceOfTruth,
    onEdit,
    onViewLogs,
    onHealthCheck,
    onRateLimit,
    onHistoricalImport,
    onViewHistoricalJobs,
    onDelete,
    onPreviewData,
    sourceOfTruthId,
}: IntegrationTableProps) {
    const getIntegrationIcon = (type: string) => {
        const normalizeType = type.toLowerCase();
        if (normalizeType.includes('sharepoint')) {
            return <Share2 className="h-4 w-4 text-blue-600" />;
        }
        return <Cable className="h-4 w-4 text-primary" />;
    };

    if (isLoading) {
        return (
            <Card className="overflow-hidden border-none shadow-none bg-transparent">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/50">
                            <TableHead className="w-[300px]"><Skeleton className="h-4 w-24" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-20" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-16" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-20" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-24" /></TableHead>
                            <TableHead className="text-right"><Skeleton className="h-4 w-16 ml-auto" /></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {[1, 2, 3, 4, 5].map((i) => (
                            <TableRow key={i}>
                                <TableCell><Skeleton className="h-10 w-full" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                                <TableCell><Skeleton className="h-6 w-20" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                                <TableCell className="text-right"><Skeleton className="h-8 w-8 ml-auto" /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        );
    }

    if (items.length === 0) {
        return (
            <div className="py-12">
                <EmptyState
                    icon={Cable}
                    title="No Integrations Yet"
                    description="Connect your first integration to start syncing data and generating reports"
                    actionLabel="Add Integration"
                    onAction={onEmptyAction}
                />
            </div>
        );
    }

    const sortedItems = [...items].sort((a, b) =>
        a.id === sourceOfTruthId ? -1 : b.id === sourceOfTruthId ? 1 : 0
    );

    return (
        <TooltipProvider>
            <div className="border rounded-xl overflow-hidden bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/50 hover:bg-muted/50">
                            <TableHead className="w-[300px] font-semibold">Integration</TableHead>
                            <TableHead className="font-semibold">Type</TableHead>
                            <TableHead className="font-semibold">Status</TableHead>
                            <TableHead className="font-semibold">Last Sync</TableHead>
                            <TableHead className="font-semibold">Customers</TableHead>
                            <TableHead className="text-right font-semibold">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sortedItems.map((integration) => {
                            const isSyncing = syncingId === integration.id;
                            const isSourceOfTruth = sourceOfTruthId === integration.id;
                            const statusInfo = getStatusText(integration.status, integration.syncStatus);
                            const isSettingSourceOfTruth = settingSourceOfTruthId === integration.id;

                            return (
                                <TableRow
                                    key={integration.id}
                                    className={cn(
                                        "transition-colors",
                                        isSourceOfTruth && "bg-primary/5 hover:bg-primary/10"
                                    )}
                                >
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                                                {getIntegrationIcon(integration.type)}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium truncate max-w-[150px]">
                                                        {integration.name}
                                                    </span>
                                                    {isSourceOfTruth && (
                                                        <Badge className="bg-primary text-[10px] h-4 px-1">
                                                            Master
                                                        </Badge>
                                                    )}
                                                </div>
                                                {(isSyncing || integration.status === 'importing_historical') && (
                                                    <div className="mt-1 w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-1">
                                                        <div
                                                            className="bg-primary h-1 rounded-full transition-all duration-300"
                                                            style={{ 
                                                                width: `${integration.status === 'importing_historical' 
                                                                    ? (integration.historicalImportProgress || 0) 
                                                                    : progress}%` 
                                                                }}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm text-muted-foreground">
                                            {integration.type}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className={cn("text-[10px] py-0 px-2", statusInfo.color)}>
                                            {statusInfo.text}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col">
                                            <span className="text-sm text-muted-foreground whitespace-nowrap">
                                                {integration.lastSync}
                                            </span>
                                            {integration.nextSyncAt && (
                                                <div className="flex items-center gap-1 text-[10px] text-primary">
                                                    <Clock className="h-3 w-3" />
                                                    Next sync soon
                                                </div>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm font-medium">
                                            {integration.customers}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {isSourceOfTruth && (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => onSync(integration.id, integration.name)}
                                                            disabled={isSyncing}
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            {isSyncing ? (
                                                                <LoadingSpinner size="sm" />
                                                            ) : (
                                                                <RefreshCw className="h-4 w-4" />
                                                            )}
                                                            <span className="sr-only">Sync Now</span>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Sync Now
                                                    </TooltipContent>
                                                </Tooltip>
                                            )}
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onPreviewData(integration)}
                                                        className="h-8 w-8 p-0"
                                                    >
                                                        <Users className="h-4 w-4" />
                                                        <span className="sr-only">View Customers</span>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    View Customers
                                                </TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                    <MoreVertical className="h-4 w-4" />
                                                                    <span className="sr-only">More options</span>
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                {!isSourceOfTruth && (
                                                                    <>
                                                                        <DropdownMenuItem
                                                                            onClick={() => onSetSourceOfTruth(integration.id, integration.name)}
                                                                            disabled={isSettingSourceOfTruth}
                                                                        >
                                                                            <Crown className="mr-2 h-4 w-4" />
                                                                            Set as Master List
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuSeparator />
                                                                        <DropdownMenuItem
                                                                            onClick={() => onSync(integration.id, integration.name)}
                                                                            disabled={isSyncing}
                                                                        >
                                                                            <RefreshCw className="mr-2 h-4 w-4" />
                                                                            Manual Sync
                                                                        </DropdownMenuItem>
                                                                    </>
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
                                                                <DropdownMenuItem
                                                                    onClick={() => onDelete(integration)}
                                                                    className="text-destructive font-medium focus:text-destructive"
                                                                >
                                                                    <Trash2 className="mr-2 h-4 w-4" />
                                                                    Delete Integration
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    More Actions
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        </TooltipProvider>
    );
}
