import FloatingLayout from '@/components/layout/FloatingLayout'
import { Skeleton } from '@/components/ui/skeleton'

export function PreviewLoadingState() {
  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Skeleton className="h-10 w-10 rounded-lg" />
            <div className="space-y-2">
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-4 w-32" />
            </div>
          </div>
          <div className="flex gap-2">
            <Skeleton className="h-10 w-24" />
            <Skeleton className="h-10 w-24" />
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="p-4 bg-card border border-border rounded-lg">
              <Skeleton className="h-4 w-24 mb-2" />
              <Skeleton className="h-8 w-16 mb-2" />
              <Skeleton className="h-3 w-32" />
            </div>
          ))}
        </div>

        <div className="space-y-4">
          <Skeleton className="h-6 w-40" />
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <div key={i} className="p-4 bg-card border border-border rounded-lg">
                <div className="flex items-center justify-between">
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-5 w-3/4" />
                    <Skeleton className="h-4 w-1/2" />
                  </div>
                  <Skeleton className="h-6 w-6 rounded" />
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="p-4 bg-card border border-border rounded-lg">
          <div className="flex items-center justify-between">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-4 w-40" />
          </div>
        </div>
      </div>
    </FloatingLayout>
  )
}
