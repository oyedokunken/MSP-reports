import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Building2, MapPin, ChevronRight } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import type { IntegrationCustomer } from '../types'

interface UnmappedCustomersListProps {
    customers: IntegrationCustomer[]
    onSelectCustomer: (customer: IntegrationCustomer) => void
}

export function UnmappedCustomersList({ customers, onSelectCustomer }: UnmappedCustomersListProps) {
    if (customers.length === 0) return null

    return (
        <Card className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30">
            <CardContent className="py-4">
                <div className="flex flex-col gap-4">
                    <div className="flex items-start gap-3">
                        <div className="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/50 flex-shrink-0">
                            <Building2 className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="flex-1">
                            <p className="font-semibold text-foreground text-lg mb-1">
                                {customers.length} New Client{customers.length !== 1 ? 's' : ''} from Integrations
                            </p>
                            <p className="text-sm text-muted-foreground mb-3">
                                Select a customer below to map to an existing client or create a new one
                            </p>
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <div className="flex items-center gap-1">
                                    <div className="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400"></div>
                                    <span className="text-muted-foreground">Step 1: Review synced customers</span>
                                </div>
                                <span className="text-muted-foreground hidden sm:inline">→</span>
                                <div className="flex items-center gap-1">
                                    <div className="w-2 h-2 rounded-full bg-blue-500 dark:bg-blue-400"></div>
                                    <span className="text-muted-foreground">Step 2: Map to clients</span>
                                </div>
                                <span className="text-muted-foreground hidden sm:inline">→</span>
                                <div className="flex items-center gap-1">
                                    <div className="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                                    <span className="text-muted-foreground">Step 3: Configure reports</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="border border-blue-200 dark:border-blue-800 rounded-lg overflow-hidden bg-white dark:bg-gray-900">
                        <div className="max-h-[400px] overflow-y-auto">
                            <div className="divide-y divide-border">
                                {customers.map((customer) => (
                                    <button
                                        key={customer.integration_customer_id}
                                        onClick={() => onSelectCustomer(customer)}
                                        className="w-full p-4 hover:bg-accent/50 transition-colors flex items-center justify-between gap-3 group"
                                    >
                                        <div className="flex-1 text-left min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <p className="font-medium text-foreground truncate">
                                                    {customer.name}
                                                </p>
                                                {!customer.active && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <Badge variant="outline" className="text-xs">
                                                    {customer.integration_type}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    ID: {customer.external_id}
                                                </span>
                                                {customer.last_seen_at && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Last seen: {new Date(customer.last_seen_at).toLocaleDateString()}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="group-hover:bg-primary/10"
                                                asChild
                                            >
                                                <div className="flex items-center gap-1">
                                                    <MapPin className="h-4 w-4" />
                                                    <span>Map</span>
                                                    <ChevronRight className="h-4 w-4" />
                                                </div>
                                            </Button>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    )
}
