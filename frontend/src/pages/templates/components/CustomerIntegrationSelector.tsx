import type { Customer } from '@/types/api/customer'
import { Check } from 'lucide-react'

interface CustomerIntegrationSelectorProps {
  customers: Customer[]
  selectedCustomerId: number | null
  onSelect: (customer: Customer) => void
  customerIntegrations?: Record<number, string[]>
}

export function CustomerIntegrationSelector({
  customers,
  selectedCustomerId,
  onSelect,
  customerIntegrations = {},
}: CustomerIntegrationSelectorProps) {
  return (
    <div>
      <div className="border rounded-lg max-h-64 overflow-y-auto thin-scrollbar">
        {customers.map((customer) => {
          const integrations = customerIntegrations[customer.customer_id] ||
            customer.integrations?.map(i => i.integration_type) || []

          const isSelected = selectedCustomerId === customer.customer_id


          const integrationsText = integrations.length > 0
            ? integrations.join(', ')
            : 'No integrations'

          return (
            <button
              key={customer.customer_id}
              onClick={() => onSelect(customer)}
              className={`
                w-full px-3 py-1.5 text-left transition-colors border-b last:border-b-0 text-sm
                ${isSelected
                  ? 'bg-primary/5 text-primary font-medium'
                  : 'hover:bg-muted/50'
                }
              `}
            >
              <div className="flex items-center justify-between gap-2">
                <div className="flex-1 min-w-0">
                  <span className="font-medium">{customer.canonical_name}</span>
                  <span className="text-muted-foreground text-xs ml-1.5">
                    ({integrationsText})
                  </span>
                </div>
                {isSelected && (
                  <Check className="h-3.5 w-3.5 flex-shrink-0" />
                )}
              </div>
            </button>
          )
        })}
      </div>
    </div>
  )
}
