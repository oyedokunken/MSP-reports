import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Users } from 'lucide-react'
import { CustomerCard } from './CustomerCard'
import type { MappedCustomer } from '../types'

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
  onMoreInfo: (customerId: number) => void
  onApplyTemplate: (customer: MappedCustomer) => void
  onConfigureDelivery: (customer: MappedCustomer) => void
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
  onMoreInfo,
  onApplyTemplate,
  onConfigureDelivery,
}: CustomersGridProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <Card key={i}>
            <CardContent className="pt-6">
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center space-x-3 flex-1">
                  <Skeleton className="h-12 w-12 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-5 w-32" />
                    <Skeleton className="h-4 w-24" />
                  </div>
                </div>
                <Skeleton className="h-6 w-16" />
              </div>
              <div className="space-y-3">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
                <div className="flex space-x-2 pt-2">
                  <Skeleton className="h-9 flex-1" />
                  <Skeleton className="h-9 flex-1" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
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
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-stretch max-w-full overflow-hidden">
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
          onMoreInfo={() => onMoreInfo(customer.customer_id!)}
          onApplyTemplate={() => onApplyTemplate(customer)}
          onConfigureDelivery={() => onConfigureDelivery(customer)}
        />
      ))}
    </div>
  )
}
