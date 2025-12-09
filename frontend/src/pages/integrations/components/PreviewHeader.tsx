import { ArrowLeft, RefreshCw, Download } from 'lucide-react'

interface PreviewHeaderProps {
  integrationName?: string
  customerName?: string
  isRefreshing: boolean
  onBack: () => void
  onRefresh: () => void
  onDownloadReport?: () => void
  isDownloading?: boolean
  showDownloadButton?: boolean
}

export function PreviewHeader({
  integrationName,
  customerName,
  isRefreshing,
  onBack,
  onRefresh,
  onDownloadReport,
  isDownloading = false,
  showDownloadButton = false,
}: PreviewHeaderProps) {
  return (
    <div className="mb-6">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-4">
          <button
            onClick={onBack}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-foreground bg-card dark:bg-[hsl(var(--auth-card))] border border-border rounded-lg hover:bg-muted/50 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            Back
          </button>
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              {integrationName || 'Integration Preview'}
            </h1>
            {customerName && (
              <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Customer: {customerName}
              </p>
            )}
          </div>
        </div>
        <div className="flex items-center gap-2">
          {showDownloadButton && onDownloadReport && (
            <button
              onClick={onDownloadReport}
              disabled={isDownloading}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 border border-primary rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              title="Download M365 License Consumption Report"
            >
              <Download className={`w-4 h-4 ${isDownloading ? 'animate-bounce' : ''}`} />
              {isDownloading ? 'Downloading...' : 'M365 Licenses'}
            </button>
          )}
          <button
            onClick={onRefresh}
            disabled={isRefreshing}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-foreground bg-card dark:bg-[hsl(var(--auth-card))] border border-border rounded-lg hover:bg-muted/50 transition-colors disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 ${isRefreshing ? 'animate-spin' : ''}`} />
            Refresh
          </button>
        </div>
      </div>
    </div>
  )
}
