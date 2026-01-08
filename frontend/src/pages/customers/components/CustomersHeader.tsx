import { Button } from '@/components/ui/button'
import { UserPlus } from 'lucide-react'

interface CustomersHeaderProps {
  title?: string
  subtitle?: string
  onCreateStatic?: () => void
}

export function CustomersHeader({
  title = "Customers",
  subtitle = "Manage customers and configure their report schedules",
  onCreateStatic
}: CustomersHeaderProps) {
  return (
    <div className="flex items-start justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">{title}</h1>
        <p className="mt-2 text-muted-foreground">
          {subtitle}
        </p>
      </div>
      {onCreateStatic && (
        <Button onClick={onCreateStatic} className="flex items-center gap-2">
          <UserPlus className="h-4 w-4" />
          Create Static Customer
        </Button>
      )}
    </div>
  )
}
