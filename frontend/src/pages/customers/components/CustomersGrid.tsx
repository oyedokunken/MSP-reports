import { Card, CardContent } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { Users } from 'lucide-react'
import { CustomerCard } from './CustomerCard'
import type { MappedCustomer } from '@/stores/useCustomersStore'

interface CustomersGridProps {
  customers: MappedCustomer[]
  isLoading: boolean
  hasUnmapped: boolean
  onSetupReports: (customerId: number) => void
  onViewHistory: (customerId: number) => void
  onEdit: (customer: MappedCustomer) => void
  onViewIntegrations: (customer: MappedCustomer) => void
  onMerge: (customer: MappedCustomer) => void
  onSplit: (customer: MappedCustomer) => void
}

export function CustomersGrid({
  customers,
  isLoading,
  hasUnmapped,
  onSetupReports,
  onViewHistory,
  onEdit,
  onViewIntegrations,
  onMerge,
  onSplit,
}: CustomersGridProps) {
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Spinner className="h-8 w-8" />
      </div>
    )
  }

  if (customers.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center justify-center py-12">
          <Users className="h-12 w-12 text-muted-foreground mb-4" />
          <p className="text-lg font-medium">No Mapped Customers</p>
          <p className="text-sm text-muted-foreground">
            {hasUnmapped 
              ? 'Map the unmapped customers above to get started'
              : 'Integration customers will appear here once synced'}
          </p>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {customers.map((customer) => (
        <CustomerCard
          key={customer.customer_id}
          customer={customer}
          onSetupReports={() => onSetupReports(customer.customer_id!)}
          onViewHistory={() => onViewHistory(customer.customer_id!)}
          onEdit={() => onEdit(customer)}
          onViewIntegrations={() => onViewIntegrations(customer)}
          onMerge={() => onMerge(customer)}
          onSplit={() => onSplit(customer)}
        />
      ))}
    </div>
  )
}
