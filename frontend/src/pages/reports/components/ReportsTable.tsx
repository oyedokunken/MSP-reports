import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    Download,
    RefreshCw,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Clock,
    Eye,
    FileText,
    MoreVertical,
    Trash2,
    Send,
    Share2,
} from 'lucide-react'
import { formatDate, formatFileSize } from '../utils/formatters'
import type { GeneratedReport } from '../types/report'
import { ReportPreviewModal } from '@/components/reports/ReportPreviewModal'
import { ShareLinkModal } from '@/components/reports/ShareLinkModal'
import { cn } from '@/lib/utils'

interface ReportsTableProps {
    reports: GeneratedReport[]
    isLoading: boolean
    downloading: number | null
    onDownload: (reportId: number) => void
    onDelete?: (reportId: number) => void
    deleting?: number | null | string
    selectedIds?: number[]
    onToggleSelection?: (reportId: number) => void
}

export function ReportsTable({
    reports,
    isLoading,
    downloading,
    onDownload,
    onDelete,
    deleting,
    selectedIds = [],
    onToggleSelection,
}: ReportsTableProps) {
    const [previewReport, setPreviewReport] = useState<GeneratedReport | null>(null)
    const [shareReport, setShareReport] = useState<GeneratedReport | null>(null)

    const getStatusBadge = (status: string) => {
        const variants = {
            completed: { variant: 'default' as const, icon: CheckCircle2, className: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' },
            failed: { variant: 'destructive' as const, icon: XCircle, className: '' },
            pending: { variant: 'secondary' as const, icon: Clock, className: 'bg-amber-500/10 text-amber-600 border-amber-500/20' },
            generating: { variant: 'secondary' as const, icon: RefreshCw, className: 'bg-blue-500/10 text-blue-600 border-blue-500/20' },
        }

        const config = variants[status as keyof typeof variants] || { variant: 'secondary' as const, icon: AlertCircle, className: '' }
        const Icon = config.icon

        return (
            <Badge variant={config.variant} className={cn("flex items-center gap-1 w-fit whitespace-nowrap", config.className)}>
                <Icon className={cn("h-3 w-3 flex-shrink-0", status === 'generating' && 'animate-spin')} />
                <span>{status.charAt(0).toUpperCase() + status.slice(1)}</span>
            </Badge>
        )
    }

    const formatReportType = (type: string) => {
        return type.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ')
    }

    if (isLoading) {
        return (
            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/50">
                            <TableHead className="w-[40px]"><Skeleton className="h-4 w-4" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-24" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-20" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-16" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-20" /></TableHead>
                            <TableHead><Skeleton className="h-4 w-24" /></TableHead>
                            <TableHead className="text-right"><Skeleton className="h-4 w-16 ml-auto" /></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {[1, 2, 3, 4, 5, 6].map((i) => (
                            <TableRow key={i}>
                                <TableCell><Skeleton className="h-4 w-4" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-40" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                                <TableCell><Skeleton className="h-6 w-20" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                                <TableCell><Skeleton className="h-4 w-28" /></TableCell>
                                <TableCell className="text-right"><Skeleton className="h-8 w-20 ml-auto" /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        )
    }

    if (reports.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                    <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                    <p className="text-lg font-medium text-muted-foreground">No reports found</p>
                    <p className="text-sm text-muted-foreground mt-1">
                        Try adjusting your filters or generate a new report
                    </p>
                </CardContent>
            </Card>
        )
    }

    return (
        <>
            <div className="border rounded-xl overflow-hidden bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/50 hover:bg-muted/50">
                            <TableHead className="w-[40px]">
                                <span className="sr-only">Select</span>
                            </TableHead>
                            <TableHead className="font-semibold">Customer</TableHead>
                            <TableHead className="font-semibold">Report Type</TableHead>
                            <TableHead className="font-semibold">Status</TableHead>
                            <TableHead className="font-semibold">Size</TableHead>
                            <TableHead className="font-semibold">Generated</TableHead>
                            <TableHead className="font-semibold">Delivery</TableHead>
                            <TableHead className="text-right font-semibold">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {reports.map((report) => {
                            const isSelected = selectedIds.includes(report.generated_report_id)
                            const isDownloading = downloading === report.generated_report_id
                            const isDeleting = deleting === report.generated_report_id || (deleting === 'batch' && selectedIds.includes(report.generated_report_id))

                            return (
                                <TableRow
                                    key={report.generated_report_id}
                                    className={cn(
                                        "transition-colors",
                                        isSelected && "bg-primary/5"
                                    )}
                                >
                                    <TableCell>
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() => onToggleSelection?.(report.generated_report_id)}
                                            className="h-4 w-4"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
                                                <FileText className="h-4 w-4 text-primary" />
                                            </div>
                                            <span className="font-medium truncate max-w-[200px]">
                                                {report.customer_name || 'Unknown Customer'}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm text-muted-foreground">
                                            {formatReportType(report.report_type || 'custom_report')}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {getStatusBadge(report.status)}
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm text-muted-foreground">
                                            {formatFileSize(report.file_size)}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className="text-sm text-muted-foreground">
                                            {formatDate(report.generated_at)}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className={cn(
                                            "text-sm",
                                            report.delivered ? 'text-emerald-600' : 'text-amber-600'
                                        )}>
                                            {report.delivered ? report.delivery_method : 'Not delivered'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setPreviewReport(report)}
                                                disabled={report.status !== 'completed'}
                                                className="h-8 w-8 p-0"
                                            >
                                                <Eye className="h-4 w-4" />
                                                <span className="sr-only">Preview</span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => onDownload(report.generated_report_id)}
                                                disabled={isDownloading || report.status !== 'completed'}
                                                className="h-8 w-8 p-0"
                                            >
                                                {isDownloading ? (
                                                    <RefreshCw className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Download className="h-4 w-4" />
                                                )}
                                                <span className="sr-only">Download</span>
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                        <MoreVertical className="h-4 w-4" />
                                                        <span className="sr-only">More options</span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        disabled={report.status !== 'completed'}
                                                        onClick={() => setShareReport(report)}
                                                    >
                                                        <Share2 className="mr-2 h-4 w-4" />
                                                        Quick Share Link
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem disabled={report.status !== 'completed'}>
                                                        <Send className="mr-2 h-4 w-4" />
                                                        Resend Report
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        className="text-destructive font-medium focus:text-destructive"
                                                        onClick={() => onDelete?.(report.generated_report_id)}
                                                        disabled={isDeleting}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        {isDeleting ? 'Deleting...' : 'Delete Report'}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )
                        })}
                    </TableBody>
                </Table>
            </div >

            {/* Preview Modal */}
            {
                previewReport && (
                    <ReportPreviewModal
                        open={!!previewReport}
                        onClose={() => setPreviewReport(null)}
                        reportId={previewReport.generated_report_id}
                        reportName={`${previewReport.customer_name} - ${formatReportType(previewReport.report_type || 'custom_report')}`}
                    />
                )
            }

            {/* Share Link Modal */}
            {
                shareReport && (
                    <ShareLinkModal
                        open={!!shareReport}
                        onClose={() => setShareReport(null)}
                        reportId={shareReport.generated_report_id}
                        reportName={`${shareReport.customer_name} - ${formatReportType(shareReport.report_type || 'custom_report')}`}
                    />
                )
            }
        </>
    )
}
