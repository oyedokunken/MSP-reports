import { FileText, Download } from 'lucide-react'
import type { ReportItem } from '../types'
import { getStatusBadge, getStatusIcon } from '../utils'

type ReportGridItemProps = {
  report: ReportItem
}

export function ReportGridItem ({ report }: ReportGridItemProps) {
  return (
    <div className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <FileText className="h-5 w-5 text-primary" />
          <h3 className="text-base font-semibold text-foreground">{report.name}</h3>
        </div>
        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${getStatusBadge(report.status)}`}>
          {getStatusIcon(report.status)}
          {report.status.charAt(0).toUpperCase() + report.status.slice(1)}
        </span>
      </div>
      <p className="mt-2 text-sm text-muted-foreground">{report.customer}</p>
      <div className="mt-3 space-y-1 text-sm">
        <p>
          <span className="font-medium">Schedule:</span> {report.schedule}
        </p>
        <p>
          <span className="font-medium">Last Generated:</span> {report.lastGenerated}
        </p>
        <p>
          <span className="font-medium">Next Run:</span> {report.nextRun}
        </p>
      </div>
      <div className="mt-4 flex gap-2">
        <button className="rounded-md border border-border bg-background p-2 hover:bg-muted">
          <Download className="h-4 w-4 text-foreground" />
        </button>
        <button className="rounded-md border border-border bg-background px-3 py-1 text-sm font-medium text-foreground hover:bg-muted">
          Configure
        </button>
      </div>
    </div>
  )
}


