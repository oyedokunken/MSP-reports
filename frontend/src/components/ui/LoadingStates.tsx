/**
 * Loading States & Skeletons
 * Provides visual feedback during data loading
 */

export function StatCardSkeleton() {
  return (
    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[hsl(var(--auth-card))] p-6 shadow-sm animate-pulse">
      <div className="flex items-center justify-between">
        <div className="flex-1">
          <div className="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded" />
          <div className="mt-2 h-8 w-16 bg-gray-200 dark:bg-gray-700 rounded" />
        </div>
        <div className="rounded-lg bg-gray-100 dark:bg-gray-800 p-3">
          <div className="h-6 w-6 bg-gray-200 dark:bg-gray-700 rounded" />
        </div>
      </div>
    </div>
  )
}

export function ActivityCardSkeleton() {
  return (
    <div className="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[hsl(var(--auth-card))] p-6 shadow-sm animate-pulse">
      <div className="h-6 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-4" />
      <div className="space-y-4">
        {[1, 2, 3].map((i) => (
          <div key={i} className="flex items-center justify-between py-2">
            <div className="flex items-center gap-3 flex-1">
              <div className="h-2 w-2 rounded-full bg-gray-200 dark:bg-gray-700" />
              <div className="flex-1">
                <div className="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-2" />
                <div className="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded" />
              </div>
            </div>
            <div className="h-6 w-16 bg-gray-200 dark:bg-gray-700 rounded-full" />
          </div>
        ))}
      </div>
    </div>
  )
}

interface EmptyStateProps {
  icon: any
  title: string
  description: string
  actionLabel?: string
  onAction?: () => void
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  actionLabel,
  onAction,
}: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-6 text-center">
      <div className="rounded-full bg-gray-100 dark:bg-gray-800 p-6 mb-4">
        <Icon className="h-12 w-12 text-gray-400 dark:text-gray-500" />
      </div>
      <h3 className="text-lg font-semibold text-foreground mb-2">{title}</h3>
      <p className="text-sm text-muted-foreground max-w-sm mb-6">{description}</p>
      {actionLabel && onAction && (
        <button
          onClick={onAction}
          className="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
        >
          {actionLabel}
        </button>
      )}
    </div>
  )
}

export function LoadingSpinner({ size = 'md' }: { size?: 'sm' | 'md' | 'lg' }) {
  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
  }

  return (
    <div className="flex items-center justify-center">
      <div
        className={`${sizeClasses[size]} animate-spin rounded-full border-2 border-gray-300 dark:border-gray-700 border-t-primary`}
      />
    </div>
  )
}
