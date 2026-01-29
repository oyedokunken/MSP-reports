import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Download, RefreshCw, CheckCircle2, XCircle, AlertCircle, Clock, Eye, FileText, Building2, Calendar, Mail, MoreVertical, Trash2, Send, Share2 } from 'lucide-react'
import { formatDate, formatFileSize } from '../utils/formatters'
import type { GeneratedReport } from '../types/report'
import { ReportPreviewModal } from '@/components/reports/ReportPreviewModal'
import { ShareLinkModal } from '@/components/reports/ShareLinkModal'
import { AnimatedCard } from '@/components/ui/AnimatedCard'
import { cn } from '@/lib/utils'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'

interface ReportCardProps {
  report: GeneratedReport
  downloading: boolean
  onDownload: () => void
  onDelete?: (reportId: number) => void
  deleting?: boolean
  isSelected?: boolean
  onSelect?: () => void
}

export function ReportCard({ 
  report, 
  downloading, 
  onDownload, 
  onDelete, 
  deleting = false,
  isSelected = false,
  onSelect
}: ReportCardProps) {
  const { user } = useAuth()
  const canManageReports = hasPermission(user, 'reports', 'generate')
  
  const [showPreview, setShowPreview] = useState(false)
  const [showShareModal, setShowShareModal] = useState(false)

  const getStatusBadge = (status: string) => {
    const variants = {
      completed: { variant: 'default' as const, icon: CheckCircle2 },
      failed: { variant: 'destructive' as const, icon: XCircle },
      pending: { variant: 'secondary' as const, icon: Clock },
      generating: { variant: 'secondary' as const, icon: RefreshCw },
    }

    const config = variants[status as keyof typeof variants] || { variant: 'secondary' as const, icon: AlertCircle }
    const Icon = config.icon

    return (
      <Badge variant={config.variant} className="flex items-center gap-1 w-fit whitespace-nowrap">
        <Icon className="h-3 w-3 flex-shrink-0" />
        <span className="truncate">{status.charAt(0).toUpperCase() + status.slice(1)}</span>
      </Badge>
    )
  }

  const formatReportType = (type: string) => {
    return type.split('_').map(word => 
      word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ')
  }

  return (
    <AnimatedCard 
      className={cn(
        "p-6 h-full flex flex-col transition-all duration-200",
        isSelected && "ring-2 ring-primary border-primary bg-primary/5"
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0 flex-1">
          <div className="flex items-center gap-3">
            {canManageReports && (
              <Checkbox 
                checked={isSelected} 
                onCheckedChange={onSelect}
                className="h-5 w-5"
              />
            )}
            <div className="rounded-lg bg-primary/10 p-2 flex-shrink-0">
              <FileText className="h-6 w-6 text-primary" />
            </div>
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="font-semibold text-foreground break-words truncate">
              {report.customer_name || 'Unknown Customer'}
            </h3>
            <p className="text-xs text-muted-foreground truncate">
              {formatReportType(report.report_type || 'custom_report')} • {formatFileSize(report.file_size)}
            </p>
          </div>
        </div>
        {getStatusBadge(report.status)}
      </div>

      <div className="mt-6 space-y-3 flex-1">
        <div className="flex items-center gap-2 text-sm">
          <Building2 className="h-4 w-4 text-muted-foreground flex-shrink-0" />
          <span className="text-foreground truncate">{report.customer_name || 'Unknown Customer'}</span>
        </div>
        
        <div className="flex items-center gap-2 text-sm">
          <Calendar className="h-4 w-4 text-muted-foreground flex-shrink-0" />
          <span className="text-foreground">{formatDate(report.generated_at)}</span>
        </div>

        <div className="flex items-center gap-2 text-sm">
          <Mail className="h-4 w-4 text-muted-foreground flex-shrink-0" />
          <span className={cn(
            "truncate",
            report.delivered ? 'text-green-600' : 'text-orange-600'
          )}>
            {report.delivered ? 'Delivered via ' + report.delivery_method : 'Not delivered yet'}
          </span>
        </div>

        {report.error_message && (
          <div className="flex items-start gap-2 p-3 rounded-md bg-destructive/10 border border-destructive/20">
            <AlertCircle className="h-4 w-4 text-destructive flex-shrink-0 mt-0.5" />
            <p className="text-xs text-destructive font-medium line-clamp-2">
              {report.error_message}
            </p>
          </div>
        )}
      </div>

      <div className="mt-4 border-t border-border pt-4">
        <div className="flex gap-2">
          <button
            onClick={() => setShowPreview(true)}
            disabled={report.status !== 'completed'}
            className="flex-1 flex items-center justify-center gap-2 rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Eye className="h-4 w-4" />
            Preview
          </button>
          <button
            onClick={onDownload}
            disabled={downloading || report.status !== 'completed'}
            className="flex-1 flex items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {downloading ? (
              <>
                <RefreshCw className="h-4 w-4 animate-spin" />
                <span className="hidden sm:inline">Downloading...</span>
              </>
            ) : (
              <>
                <Download className="h-4 w-4" />
                <span className="hidden sm:inline">Download</span>
              </>
            )}
          </button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" size="sm" className="px-2">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem 
                disabled={report.status !== 'completed'}
                onClick={() => setShowShareModal(true)}
              >
                <Share2 className="mr-2 h-4 w-4" />
                Quick Share Link
              </DropdownMenuItem>
              <DropdownMenuItem disabled={report.status !== 'completed'}>
                <Send className="mr-2 h-4 w-4" />
                Resend Report
              </DropdownMenuItem>
              {canManageReports && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem 
                    className="text-destructive font-medium focus:text-destructive"
                    onClick={() => onDelete?.(report.generated_report_id)}
                    disabled={deleting}
                  >
                    <Trash2 className="mr-2 h-4 w-4" />
                    {deleting ? 'Deleting...' : 'Delete Report'}
                  </DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <ReportPreviewModal
        open={showPreview}
        onClose={() => setShowPreview(false)}
        reportId={report.generated_report_id}
        reportName={`${report.customer_name} - ${formatReportType(report.report_type || 'custom_report')}`}
      />

      <ShareLinkModal
        open={showShareModal}
        onClose={() => setShowShareModal(false)}
        reportId={report.generated_report_id}
        reportName={`${report.customer_name} - ${formatReportType(report.report_type || 'custom_report')}`}
      />
    </AnimatedCard>
  )
}
