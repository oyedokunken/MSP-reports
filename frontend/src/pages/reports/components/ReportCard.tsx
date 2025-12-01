import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Download, RefreshCw, CheckCircle2, XCircle, AlertCircle, Clock, Eye } from 'lucide-react'
import { formatDate, formatFileSize } from '../utils/formatters'
import type { GeneratedReport } from '../types/report'
import { ReportPreviewModal } from '@/components/reports/ReportPreviewModal'

interface ReportCardProps {
  report: GeneratedReport
  downloading: boolean
  onDownload: () => void
}

export function ReportCard({ report, downloading, onDownload }: ReportCardProps) {
  const [showPreview, setShowPreview] = useState(false)

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

  return (
    <Card className="flex flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1 overflow-hidden" style={{maxWidth: 'calc(100% - 100px)'}}>
            <CardTitle className="text-base truncate" title={report.customer_name || 'Unknown Customer'}>
              {report.customer_name || 'Unknown Customer'}
            </CardTitle>
            <CardDescription className="text-xs mt-1 truncate" title={report.report_type || 'Custom Report'}>
              {report.report_type || 'Custom Report'}
            </CardDescription>
          </div>
          <div className="flex-shrink-0">
            {getStatusBadge(report.status)}
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex-1 space-y-3">
        <div className="space-y-2 text-xs">
          <div className="flex justify-between">
            <span className="text-muted-foreground">Generated</span>
            <span className="font-medium">{formatDate(report.generated_at)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground">File Size</span>
            <span className="font-medium">{formatFileSize(report.file_size)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground">Delivery</span>
            <span className="font-medium capitalize">
              {report.delivery_method}
            </span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted-foreground">Delivered</span>
            <span className="font-medium">
              {report.delivered ? 'Yes' : 'Not yet'}
            </span>
          </div>
        </div>

        {report.error_message && (
          <div className="p-2 rounded bg-destructive/10 border border-destructive/50">
            <p className="text-xs text-destructive font-medium line-clamp-2">
              {report.error_message}
            </p>
          </div>
        )}

        <div className="flex gap-2">
          <Button
            size="sm"
            variant="outline"
            className="flex-1"
            onClick={() => setShowPreview(true)}
            disabled={report.status !== 'completed'}
          >
            <Eye className="h-4 w-4 mr-2" />
            Preview
          </Button>
          <Button
            size="sm"
            className="flex-1"
            onClick={onDownload}
            disabled={downloading || report.status !== 'completed'}
          >
            {downloading ? (
              <>
                <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                Downloading...
              </>
            ) : (
              <>
                <Download className="h-4 w-4 mr-2" />
                Download
              </>
            )}
          </Button>
        </div>
      </CardContent>

      {/* Preview Modal */}
      <ReportPreviewModal
        open={showPreview}
        onClose={() => setShowPreview(false)}
        reportId={report.generated_report_id}
        reportName={`${report.customer_name} - ${report.report_type}`}
      />
    </Card>
  )
}
