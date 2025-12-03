import { ArrowLeft, RefreshCw } from 'lucide-react'

interface PreviewHeaderProps {
  integrationName?: string
  customerName?: string
  isRefreshing: boolean
  onBack: () => void
  onRefresh: () => void
}

export function PreviewHeader({
  integrationName,
  customerName,
  isRefreshing,
  onBack,
  onRefresh,
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
