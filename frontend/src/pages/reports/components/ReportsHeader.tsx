import { Button } from '@/components/ui/button'
import { List, Users } from 'lucide-react'

interface ReportsHeaderProps {
  customerId: string | null
  customerName: string
  onViewAll: () => void
  onBulkGenerate?: () => void
}

export function ReportsHeader({ customerId, customerName, onViewAll, onBulkGenerate }: ReportsHeaderProps) {
  return (
    <div className="flex items-center justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">
          {customerId && customerName ? `${customerName} - Reports` : 'Generated Reports'}
        </h1>
        <p className="mt-2 text-muted-foreground">
          {customerId ? `View and download reports for ${customerName || 'this customer'}` : 'View and download generated reports'}
        </p>
      </div>
      <div className="flex items-center gap-2">
        {!customerId && onBulkGenerate && (
          <Button onClick={onBulkGenerate}>
            <Users className="h-4 w-4 mr-2" />
            Bulk Generate
          </Button>
        )}
        {customerId && (
          <Button variant="outline" onClick={onViewAll}>
            <List className="h-4 w-4 mr-2" />
            View All Reports
          </Button>
        )}
      </div>
    </div>
  )
}
