import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/components/ui/dialog'
import { CheckCircle2, MapPin } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import type { MapCustomerModalProps } from '../types'
import { useMapCustomerModal } from '../hooks'
import { useIntersectionObserver } from '@/hooks/use-intersection-observer'
import { CustomerSearchPanel } from './CustomerSearchPanel'
import { SuggestedMatchesList } from './SuggestedMatchesList'
import { IntegrationCustomersList } from './IntegrationCustomersList'

export function MapCustomerModal({
  open,
  onOpenChange,
  integrationCustomers,
  existingCustomers,
  onSuccess
}: MapCustomerModalProps) {
  const {
    isSubmitting,
    error,
    success,
    searchTerm,
    setSearchTerm,
    selectedCustomerId,
    setSelectedCustomerId,
    createNew,
    setCreateNew,
    suggestedMatches,
    filteredCustomers,
    handleSubmit,
    currentCustomer,
    totalCount,
    isBulk,
    mappedCount,
    isSearching,
    hasMoreCandidates,
    isLoadingMoreCandidates,
    loadMoreCandidates,
    customers
  } = useMapCustomerModal(open, integrationCustomers, existingCustomers, () => {
    onOpenChange(false)
    onSuccess?.()
  })

  const observerTarget = useRef<HTMLDivElement>(null)
  const [viewMode, setViewMode] = useState<'suggested' | 'search'>('suggested')
  const entry = useIntersectionObserver(observerTarget)

  useEffect(() => {
    if (
      entry?.isIntersecting &&
      hasMoreCandidates &&
      !isLoadingMoreCandidates &&
      !isSearching
    ) {
      loadMoreCandidates()
    }
  }, [entry?.isIntersecting, hasMoreCandidates, isLoadingMoreCandidates, isSearching, loadMoreCandidates])

  const handleClose = () => {
    if (!isSubmitting) {
      onOpenChange(false)
      if (mappedCount > 0) onSuccess?.()
    }
  }

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">
              {isBulk ? `${totalCount} Customers Mapped!` : 'Customer Mapped!'}
            </DialogTitle>
            <DialogDescription className="text-center">
              {isBulk
                ? `All ${totalCount} integration customers have been mapped successfully.`
                : 'The integration customer has been mapped successfully.'}
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!currentCustomer) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <MapPin className="h-5 w-5 text-primary" />
            <DialogTitle>
              {isBulk ? `Map ${totalCount} Customers` : 'Map Customer'}
            </DialogTitle>
          </div>
          <DialogDescription>
            {isBulk
              ? `Map ${totalCount} selected customers to an existing customer or create new ones`
              : <>Map <strong>{currentCustomer.name}</strong> from {currentCustomer.integration_type} to an existing customer</>
            }
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="flex-1 overflow-hidden flex flex-col space-y-2">
          <IntegrationCustomersList
            customers={customers}
            isBulk={isBulk}
            currentCustomer={currentCustomer}
          />

          <div className="flex items-center gap-4">
            <button
              type="button"
              onClick={() => { setCreateNew(false); setSelectedCustomerId(null); }}
              className={`flex-1 py-2 px-4 rounded-lg border-2 ${!createNew ? 'border-primary bg-primary/10 text-primary font-medium' : 'border-border hover:bg-accent/50'}`}
            >
              Map to Existing
            </button>
            <button
              type="button"
              onClick={() => { setCreateNew(true); setSelectedCustomerId(null); }}
              className={`flex-1 py-2 px-4 rounded-lg border-2 ${createNew ? 'border-primary bg-primary/10 text-primary font-medium' : 'border-border hover:bg-accent/50'}`}
            >
              Create New Customer
            </button>
          </div>

          {createNew ? (
            <div className="space-y-2">
              <Label>New Customers to Create ({customers.length})</Label>
              <div className="border rounded-lg max-h-[200px] overflow-y-auto divide-y">
                {customers.map((customer, index) => (
                  <div key={customer.integration_customer_id} className="p-3 flex items-center gap-3">
                    <span className="text-xs text-muted-foreground w-6">{index + 1}.</span>
                    <div className="flex-1">
                      <p className="font-medium">{customer.name}</p>
                      <p className="text-xs text-muted-foreground">{customer.integration_type}</p>
                    </div>
                  </div>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">
                Click Confirm to create all {customers.length} customers at once.
              </p>
            </div>
          ) : (
            <>
              {!isBulk && (
                <div className="flex gap-2 mt-2">
                  <Button
                    type="button"
                    variant={viewMode === 'suggested' ? 'default' : 'outline'}
                    onClick={() => setViewMode('suggested')}
                    className="flex-1"
                  >
                    Suggested
                  </Button>
                  <Button
                    type="button"
                    variant={viewMode === 'search' ? 'default' : 'outline'}
                    onClick={() => setViewMode('search')}
                    className="flex-1"
                  >
                    Search
                  </Button>
                </div>
              )}

              {!isBulk && viewMode === 'suggested' && (
                <SuggestedMatchesList
                  matches={suggestedMatches}
                  selectedCustomerId={selectedCustomerId}
                  onSelectCustomer={setSelectedCustomerId}
                />
              )}

              {(isBulk || viewMode === 'search') && (
                <CustomerSearchPanel
                  searchTerm={searchTerm}
                  onSearchChange={setSearchTerm}
                  isSearching={isSearching}
                  customers={filteredCustomers}
                  selectedCustomerId={selectedCustomerId}
                  onSelectCustomer={setSelectedCustomerId}
                  isLoadingMore={isLoadingMoreCandidates}
                  observerRef={observerTarget}
                />
              )}
            </>
          )}

          {error && <p className="text-destructive text-sm">{error}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={handleClose} disabled={isSubmitting}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting || (!createNew && !selectedCustomerId)}>
              {isSubmitting ? 'Processing...' : 'Confirm'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
