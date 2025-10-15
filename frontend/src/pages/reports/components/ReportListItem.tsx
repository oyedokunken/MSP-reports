import { FileText, Calendar, Download } from 'lucide-react'
import type { ReportItem } from '../types'
import { getStatusBadge, getStatusIcon } from '../utils'

type ReportListItemProps = {
  report: ReportItem
}

export function ReportListItem ({ report }: ReportListItemProps) {
  return (
    <div className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-6 shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between">
        <div className="flex items-start gap-4 flex-1">
          <div className="rounded-lg bg-primary/10 p-3">
            <FileText className="h-6 w-6 text-primary" />
          </div>
          <div className="flex-1">
            <div className="flex items-center gap-3">
              <h3 className="text-lg font-semibold text-foreground">{report.name}</h3>
              <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${getStatusBadge(report.status)}`}>
                {getStatusIcon(report.status)}
                {report.status.charAt(0).toUpperCase() + report.status.slice(1)}
              </span>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">{report.customer}</p>
            <div className="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <p className="text-xs text-muted-foreground">Schedule</p>
                <p className="mt-1 text-sm font-medium text-foreground flex items-center gap-1">
                  <Calendar className="h-3 w-3" />
                  {report.schedule}
                </p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Last Generated</p>
                <p className="mt-1 text-sm font-medium text-foreground">{report.lastGenerated}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Next Run</p>
                <p className="mt-1 text-sm font-medium text-foreground">{report.nextRun}</p>
              </div>
            </div>
          </div>
        </div>
        <div className="flex gap-2">
          <button className="rounded-md border border-border bg-background p-2 hover:bg-muted">
            <Download className="h-4 w-4 text-foreground" />
          </button>
          <button className="rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted">
            Configure
          </button>
        </div>
      </div>
    </div>
  )
}


