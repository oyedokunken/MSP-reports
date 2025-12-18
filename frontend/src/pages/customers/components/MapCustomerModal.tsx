import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, MapPin, Search } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
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
    newCustomerName,
    setNewCustomerName,
    suggestedMatches,
    filteredCustomers,
    handleSubmit,
    currentCustomer,
    totalCount,
    isBulk,
    mappedCount,
    customers,
  } = useMapCustomerModal(open, integrationCustomers, existingCustomers, () => {
    onOpenChange(false)
    onSuccess?.()
  })

  const handleClose = () => {
    if (!isSubmitting) {
      onOpenChange(false)
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
                : 'The integration customer has been mapped successfully.'
              }
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
              ? `Map ${totalCount} selected customers to the same existing customer or create a new one`
              : <>Map <strong>{currentCustomer.name}</strong> from {currentCustomer.integration_type} to an existing customer</>
            }
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="flex-1 overflow-hidden flex flex-col space-y-4">
          {/* Integration Customer Info */}
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-sm font-medium mb-1">
              {isBulk ? `Selected Customers (${totalCount}):` : 'Integration Customer:'}
            </p>
            {isBulk ? (
              <p className="font-semibold text-sm">
                {integrationCustomers.slice(0, 3).map(c => c.name).join(', ')}
                {totalCount > 3 && ` and ${totalCount - 3} more...`}
              </p>
            ) : (
              <>
                <p className="font-semibold">{currentCustomer.name}</p>
                <div className="flex items-center gap-2 mt-1">
                  <Badge variant="outline" className="text-xs">
                    {currentCustomer.integration_type}
                  </Badge>
                  <span className="text-xs text-muted-foreground">
                    ID: {currentCustomer.external_id}
                  </span>
                </div>
              </>
            )}
          </div>

          {/* Create New or Map to Existing */}
          <div className="space-y-3">
            <div className="flex items-center gap-4">
              <button
                type="button"
                onClick={() => {
                  setCreateNew(false)
                  setSelectedCustomerId(null)
                }}
                className={`flex-1 py-2 px-4 rounded-lg border-2 transition-all ${!createNew
                  ? 'border-primary bg-primary/10 text-primary font-medium'
                  : 'border-border hover:bg-accent/50'
                  }`}
              >
                Map to Existing
              </button>
              <button
                type="button"
                onClick={() => {
                  setCreateNew(true)
                  setSelectedCustomerId(null)
                }}
                className={`flex-1 py-2 px-4 rounded-lg border-2 transition-all ${createNew
                  ? 'border-primary bg-primary/10 text-primary font-medium'
                  : 'border-border hover:bg-accent/50'
                  }`}
              >
                Create New Customer
              </button>
            </div>
          </div>

          {/* Create New Customer Form */}
          {createNew ? (
            <div>
              {isBulk ? (
                <>
                  <Label className="text-sm font-medium mb-2 block">
                    New Customers to Create ({totalCount}):
                  </Label>
                  <div className="border rounded-lg p-3 bg-accent/30 space-y-1 max-h-32 overflow-y-auto">
                    {customers.map(c => (
                      <div key={c.integration_customer_id} className="text-sm font-medium">
                        {c.name}
                      </div>
                    ))}
                  </div>
                  <p className="text-xs text-muted-foreground mt-2">
                    This will create {totalCount} separate customer records, one for each selected integration customer.
                  </p>
                </>
              ) : (
                <>
                  <Label htmlFor="newCustomerName" className="text-sm font-medium mb-2 block">
                    New Customer Name
                  </Label>
                  <Input
                    id="newCustomerName"
                    type="text"
                    placeholder="Enter customer name..."
                    value={newCustomerName}
                    onChange={(e) => setNewCustomerName(e.target.value)}
                    disabled={isSubmitting}
                    className="w-full"
                  />
                  <p className="text-xs text-muted-foreground mt-2">
                    This will create a new customer record and link the integration customer to it.
                  </p>
                </>
              )}
            </div>
          ) : (
            <>
              {/* Suggested Matches */}
              {suggestedMatches.length > 0 && (
                <div>
                  <Label className="text-sm font-medium mb-2 block">Suggested Matches</Label>
                  <div className="space-y-2">
                    {suggestedMatches.map((match) => (
                      <button
                        key={match.customer_id}
                        type="button"
                        onClick={() => setSelectedCustomerId(match.customer_id)}
                        className={`w-full text-left rounded-lg border p-3 transition-all ${selectedCustomerId === match.customer_id
                          ? 'border-primary bg-primary/10'
                          : 'border-border hover:bg-accent/50'
                          }`}
                      >
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="font-medium">{match.canonical_name}</p>
                            <p className="text-xs text-muted-foreground">
                              {match.integrations?.length || 0} integration{(match.integrations?.length || 0) !== 1 ? 's' : ''}
                            </p>
                          </div>
                          <Badge variant={match.confidence_score >= 85 ? 'default' : 'secondary'} className="text-white">
                            {match.confidence_score}% match
                          </Badge>
                        </div>
                      </button>
                    ))}
                  </div>
                </div>
              )}

              <div>
                <Label htmlFor="search" className="text-sm font-medium mb-2 block">
                  Or Search All Customers
                </Label>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="search"
                    type="text"
                    placeholder="Search customers..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-9"
                    disabled={isSubmitting}
                  />
                </div>
              </div>

              <div className="flex-1 overflow-y-auto border border-border rounded-lg">
                {filteredCustomers.length === 0 ? (
                  <div className="flex flex-col items-center justify-center py-8 text-center">
                    <p className="text-sm text-muted-foreground">No customers found</p>
                  </div>
                ) : (
                  <div className="divide-y divide-border">
                    {filteredCustomers.map((customer) => (
                      <button
                        key={customer.customer_id}
                        type="button"
                        onClick={() => setSelectedCustomerId(customer.customer_id)}
                        className={`w-full text-left p-3 transition-all ${selectedCustomerId === customer.customer_id
                          ? 'bg-primary/10'
                          : 'hover:bg-accent/50'
                          }`}
                      >
                        <p className="font-medium">{customer.canonical_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {customer.integrations.length} integration{customer.integrations.length !== 1 ? 's' : ''}
                        </p>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            </>
          )}

          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting || (!createNew && !selectedCustomerId) || (createNew && !newCustomerName.trim())}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  {createNew ? 'Creating...' : 'Mapping...'}
                </>
              ) : (
                <>
                  <MapPin className="mr-2 h-4 w-4" />
                  {createNew ? 'Create & Map' : 'Map Customer'}
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
