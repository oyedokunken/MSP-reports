import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { FileText } from 'lucide-react'
import { ReportCard } from './ReportCard'
import type { GeneratedReport } from '../types/report'

interface ReportsGridProps {
  reports: GeneratedReport[]
  isLoading: boolean
  downloading: number | null
  onDownload: (reportId: number) => void
}

export function ReportsGrid({ reports, isLoading, downloading, onDownload }: ReportsGridProps) {
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Spinner className="h-8 w-8" />
      </div>
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
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {reports.map((report) => (
        <ReportCard
          key={report.generated_report_id}
          report={report}
          downloading={downloading === report.generated_report_id}
          onDownload={() => onDownload(report.generated_report_id)}
        />
      ))}
    </div>
  )
}
