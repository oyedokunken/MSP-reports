// Updated MapCustomerModal with toggle between Suggested Matches and Search

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, MapPin, Search } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { useEffect, useRef, useState } from 'react'
import type { MapCustomerModalProps } from '../types'
import { useMapCustomerModal } from '../hooks'

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
    currentIndex,
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

  // ✅ FIXED TYPE ERROR HERE
  useEffect(() => {
    const observer = new IntersectionObserver((entries) => {
      if (
        entries[0].isIntersecting &&
        hasMoreCandidates &&
        !isLoadingMoreCandidates &&
        !isSearching
      ) {
        loadMoreCandidates()
      }
    })

    const target = observerTarget.current
    if (target) observer.observe(target)

    return () => {
      if (target) observer.unobserve(target)
    }
  }, [hasMoreCandidates, isLoadingMoreCandidates, isSearching, loadMoreCandidates])

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
              {isBulk ? `Map Customer (${currentIndex + 1} of ${totalCount})` : 'Map Customer'}
            </DialogTitle>
          </div>
          <DialogDescription>
            Map <strong>{currentCustomer.name}</strong> from {currentCustomer.integration_type} to an existing customer
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="flex-1 overflow-hidden flex flex-col space-y-2">
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <div className="flex justify-between items-start">
              <div>
                <p className="text-sm font-medium mb-1">Integration Customer:</p>
                <p className="font-semibold">{currentCustomer.name}</p>
                <div className="flex items-center gap-2 mt-1">
                  <Badge variant="outline" className="text-xs">{currentCustomer.integration_type}</Badge>
                  <span className="text-xs text-muted-foreground">ID: {currentCustomer.external_id}</span>
                </div>
              </div>
              {isBulk && (
                <Badge variant="secondary" className="bg-primary/10 text-primary hover:bg-primary/20">
                  {currentIndex + 1} / {totalCount}
                </Badge>
              )}
            </div>
          </div>

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
              {/* Toggle Suggested vs Search */}
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

              {/* Suggested Matches */}
              {viewMode === 'suggested' && suggestedMatches.length > 0 && (
                <div className="space-y-2 mt-3">
                  {suggestedMatches.map((match) => (
                    <button
                      key={match.customer_id}
                      type="button"
                      onClick={() => setSelectedCustomerId(match.customer_id)}
                      className={`w-full text-left rounded-lg border p-3 ${selectedCustomerId === match.customer_id ? 'border-primary bg-primary/10' : 'border-border hover:bg-accent/50'
                        }`}
                    >
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="font-medium">{match.canonical_name}</p>
                          <p className="text-xs text-muted-foreground">{match.integrations?.length || 0} integrations</p>
                        </div>
                        <Badge>{match.confidence_score}% match</Badge>
                      </div>
                    </button>
                  ))}
                </div>
              )}

              {/* Search Section */}
              {viewMode === 'search' && (
                <div className="flex flex-col gap-2 mt-3 h-full">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                      placeholder="Search customers..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-9"
                    />
                  </div>

                  <div className="flex-1 border rounded-lg overflow-hidden min-h-0 flex flex-col max-h-[300px]">
                    {isSearching ? (
                      <div className="flex items-center justify-center py-6">
                        <Spinner className="mr-2 h-5 w-5" /> Searching...
                      </div>
                    ) : filteredCustomers.length === 0 ? (
                      <div className="flex items-center justify-center py-6 text-sm text-muted-foreground">
                        No customers found
                      </div>
                    ) : (
                      <div className="overflow-y-auto divide-y flex-1">
                        {filteredCustomers.map((customer) => (
                          <button
                            key={customer.customer_id}
                            type="button"
                            className={`w-full text-left p-3 ${selectedCustomerId === customer.customer_id ? 'bg-primary/10' : 'hover:bg-accent/50'
                              }`}
                            onClick={() => setSelectedCustomerId(customer.customer_id)}
                          >
                            <p className="font-medium">{customer.canonical_name}</p>
                            <p className="text-xs text-muted-foreground">
                              {customer.integrations?.length || 0} integrations
                            </p>
                          </button>
                        ))}

                        <div ref={observerTarget} className="py-2 flex justify-center items-center">
                          {isLoadingMoreCandidates && <Spinner className="h-4 w-4" />}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
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
