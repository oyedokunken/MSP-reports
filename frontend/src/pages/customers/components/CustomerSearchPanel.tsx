import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'
import { Search } from 'lucide-react'
import type { RefObject } from 'react'

import type { IntegrationCustomer } from '../types'

interface CustomerCandidate {
    customer_id: number | null
    canonical_name: string | null
    integrations?: IntegrationCustomer[]
}

interface CustomerSearchPanelProps {
    searchTerm: string
    onSearchChange: (value: string) => void
    isSearching: boolean
    customers: CustomerCandidate[]
    selectedCustomerId: number | null
    onSelectCustomer: (id: number) => void
    isLoadingMore: boolean
    observerRef: RefObject<HTMLDivElement | null>
}

export function CustomerSearchPanel({
    searchTerm,
    onSearchChange,
    isSearching,
    customers,
    selectedCustomerId,
    onSelectCustomer,
    isLoadingMore,
    observerRef,
}: CustomerSearchPanelProps) {
    return (
        <div className="flex flex-col gap-2 mt-3 h-full">
            <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    placeholder="Search customers..."
                    value={searchTerm}
                    onChange={(e) => onSearchChange(e.target.value)}
                    className="pl-9"
                />
            </div>

            <div className="flex-1 border rounded-lg overflow-hidden min-h-0 flex flex-col max-h-[300px]">
                {isSearching ? (
                    <div className="flex items-center justify-center py-6">
                        <Spinner className="mr-2 h-5 w-5" /> Searching...
                    </div>
                ) : customers.length === 0 ? (
                    <div className="flex items-center justify-center py-6 text-sm text-muted-foreground">
                        No customers found
                    </div>
                ) : (
                    <div className="overflow-y-auto divide-y flex-1">
                        {customers.filter(c => c.customer_id !== null).map((customer) => (
                            <button
                                key={customer.customer_id}
                                type="button"
                                className={`w-full text-left p-3 ${selectedCustomerId === customer.customer_id
                                    ? 'bg-primary/10'
                                    : 'hover:bg-accent/50'
                                    }`}
                                onClick={() => onSelectCustomer(customer.customer_id!)}
                            >
                                <p className="font-medium">{customer.canonical_name ?? 'Unnamed'}</p>
                                <p className="text-xs text-muted-foreground">
                                    {customer.integrations?.length || 0} integrations
                                </p>
                            </button>
                        ))}

                        <div ref={observerRef} className="py-2 flex justify-center items-center">
                            {isLoadingMore && <Spinner className="h-4 w-4" />}
                        </div>
                    </div>
                )}
            </div>
        </div>
    )
}
