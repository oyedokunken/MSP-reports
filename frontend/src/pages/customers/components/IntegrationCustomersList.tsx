import { Badge } from '@/components/ui/badge'

interface IntegrationCustomer {
    integration_customer_id: number
    name: string
    integration_type: string
    external_id?: string
}

interface IntegrationCustomersListProps {
    customers: IntegrationCustomer[]
    isBulk: boolean
    currentCustomer: IntegrationCustomer | null
}

export function IntegrationCustomersList({
    customers,
    isBulk,
    currentCustomer,
}: IntegrationCustomersListProps) {
    if (isBulk) {
        return (
            <div className="rounded-lg border border-border bg-accent/50 p-3">
                <p className="text-sm font-medium mb-2">
                    Selected Integration Customers ({customers.length}):
                </p>
                <div className="max-h-[120px] overflow-y-auto divide-y divide-border/50">
                    {customers.map((customer, index) => (
                        <div
                            key={customer.integration_customer_id}
                            className="py-1.5 flex items-center gap-2"
                        >
                            <span className="text-xs text-muted-foreground w-5">{index + 1}.</span>
                            <span className="font-medium text-sm">{customer.name}</span>
                            <Badge variant="outline" className="text-xs ml-auto">
                                {customer.integration_type}
                            </Badge>
                        </div>
                    ))}
                </div>
            </div>
        )
    }

    if (!currentCustomer) return null

    return (
        <div className="rounded-lg border border-border bg-accent/50 p-3">
            <div className="flex justify-between items-start">
                <div>
                    <p className="text-sm font-medium mb-1">Integration Customer:</p>
                    <p className="font-semibold">{currentCustomer.name}</p>
                    <div className="flex items-center gap-2 mt-1">
                        <Badge variant="outline" className="text-xs">
                            {currentCustomer.integration_type}
                        </Badge>
                        <span className="text-xs text-muted-foreground">
                            ID: {currentCustomer.external_id}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    )
}
