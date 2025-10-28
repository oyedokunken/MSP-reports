import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Building2, MapPin } from 'lucide-react'

interface UnmappedBannerProps {
  count: number
  onStartMapping: () => void
}

export function UnmappedBanner({ count, onStartMapping }: UnmappedBannerProps) {
  if (count === 0) return null

  return (
    <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30">
      <CardContent className="py-4">
        <div className="flex items-start justify-between gap-4">
          <div className="flex items-start gap-3 flex-1">
            <div className="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/50 flex-shrink-0">
              <Building2 className="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div className="flex-1">
              <p className="font-semibold text-foreground text-lg mb-1">
                {count} New Client{count !== 1 ? 's' : ''} from Integrations
              </p>
              <p className="text-sm text-muted-foreground mb-3">
                Your integrations (Autotask, Datto, etc.) have synced customer data. 
                Link them to your existing clients or create new client records to start generating reports.
              </p>
              <div className="flex items-center gap-2 text-sm">
                <div className="flex items-center gap-1">
                  <div className="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400"></div>
                  <span className="text-muted-foreground">Step 1: Review synced customers</span>
                </div>
                <span className="text-muted-foreground">→</span>
                <div className="flex items-center gap-1">
                  <div className="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400"></div>
                  <span className="text-muted-foreground">Step 2: Map to clients</span>
                </div>
                <span className="text-muted-foreground">→</span>
                <div className="flex items-center gap-1">
                  <div className="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                  <span className="text-muted-foreground">Step 3: Configure reports</span>
                </div>
              </div>
            </div>
          </div>
          <Button 
            size="lg"
            onClick={onStartMapping}
            className="flex-shrink-0"
          >
            <MapPin className="h-4 w-4 mr-2" />
            Start Mapping ({count})
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
