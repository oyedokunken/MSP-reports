import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { CheckCircle2, MapPin, Search } from 'lucide-react'
import { api } from '@/lib/api'
import { Badge } from '@/components/ui/badge'

interface IntegrationCustomer {
  integration_customer_id: number
  integration_type: string
  display_name: string
  external_id: string
  name: string
  active: boolean
  last_seen_at: string | null
}

interface ExistingCustomer {
  customer_id: number | null
  canonical_name: string | null
  status: string | null
  integrations: any[]
}

interface SuggestedMatch {
  customer_id: number
  canonical_name: string
  confidence_score: number
  integrations?: any[]
}

interface MapCustomerModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integrationCustomer: IntegrationCustomer | null
  existingCustomers: ExistingCustomer[]
  onSuccess?: () => void
}

export function MapCustomerModal({ 
  open, 
  onOpenChange, 
  integrationCustomer,
  existingCustomers,
  onSuccess 
}: MapCustomerModalProps) {
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null)
  const [createNew, setCreateNew] = useState(false)
  const [newCustomerName, setNewCustomerName] = useState('')
  const [suggestedMatches, setSuggestedMatches] = useState<SuggestedMatch[]>([])
  const [_isLoadingSuggestions, setIsLoadingSuggestions] = useState(false)

  useEffect(() => {
    if (open && integrationCustomer) {
      setSearchTerm('')
      setSelectedCustomerId(null)
      setError('')
      setSuccess(false)
      setCreateNew(false)
      setNewCustomerName(integrationCustomer.name) // Pre-fill with integration customer name
      fetchSuggestedMatches()
    }
  }, [open, integrationCustomer])

  const fetchSuggestedMatches = async () => {
    if (!integrationCustomer) return
    
    try {
      setIsLoadingSuggestions(true)
      const response = await api.get(`/customers/unmapped`)
      const rawData = response.data || response
      const data: any[] = Array.isArray(rawData) ? rawData : []
      
      // Find the current integration customer in the response
      const currentCustomer = data.find(
        (c: any) => c.integration_customer_id === integrationCustomer.integration_customer_id
      )
      
      if (currentCustomer && currentCustomer.suggested_matches) {
        setSuggestedMatches(currentCustomer.suggested_matches)
      } else {
        setSuggestedMatches([])
      }
    } catch (err) {
      console.error('Failed to fetch suggested matches:', err)
      setSuggestedMatches([])
    } finally {
      setIsLoadingSuggestions(false)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (createNew) {
      if (!newCustomerName.trim()) {
        setError('Please enter a customer name')
        return
      }
    } else {
      if (!selectedCustomerId || !integrationCustomer) {
        setError('Please select a customer to map to')
        return
      }
    }

    setError('')
    setIsSubmitting(true)

    try {
      if (createNew) {
        // Use bulk-map endpoint to create new customer
        const response = await api.post('/customers/bulk-map', {
          mappings: [{
            integration_customer_id: integrationCustomer!.integration_customer_id,
            create_new: true,
            canonical_name: newCustomerName.trim(),
            mapping_type: 'manual'
          }]
        })

        if (response) {
          setSuccess(true)
          setTimeout(() => {
            setSuccess(false)
            onOpenChange(false)
            onSuccess?.()
          }, 2000)
        }
      } else {
        // Map to existing customer
        const response = await api.post('/customers/map', {
          integration_customer_id: integrationCustomer!.integration_customer_id,
          customer_id: selectedCustomerId
        })

        if (response) {
          setSuccess(true)
          setTimeout(() => {
            setSuccess(false)
            onOpenChange(false)
            onSuccess?.()
          }, 2000)
        }
      }
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to map customer'
      setError(errorMessage)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleClose = () => {
    if (!isSubmitting) {
      setError('')
      setSuccess(false)
      setSearchTerm('')
      setSelectedCustomerId(null)
      onOpenChange(false)
    }
  }

  const filteredCustomers = existingCustomers.filter(customer =>
    customer.customer_id !== null && 
    customer.canonical_name && 
    customer.canonical_name.toLowerCase().includes(searchTerm.toLowerCase())
  )

  // Suggested matches now come from backend API with proper confidence scores

  if (success) {
    return (
      <Dialog open={open} onOpenChange={handleClose}>
        <DialogContent className="sm:max-w-md">
          <div className="flex flex-col items-center justify-center py-8">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <DialogTitle className="text-2xl text-green-600 mb-2">Customer Mapped!</DialogTitle>
            <DialogDescription className="text-center">
              The integration customer has been mapped successfully.
            </DialogDescription>
          </div>
        </DialogContent>
      </Dialog>
    )
  }

  if (!integrationCustomer) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <MapPin className="h-5 w-5 text-primary" />
            <DialogTitle>Map Customer</DialogTitle>
          </div>
          <DialogDescription>
            Map <strong>{integrationCustomer.name}</strong> from {integrationCustomer.integration_type} to an existing customer
          </DialogDescription>
        </DialogHeader>
        
        <form onSubmit={handleSubmit} className="flex-1 overflow-hidden flex flex-col space-y-4">
          {/* Integration Customer Info */}
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-sm font-medium mb-1">Integration Customer:</p>
            <p className="font-semibold">{integrationCustomer.name}</p>
            <div className="flex items-center gap-2 mt-1">
              <Badge variant="outline" className="text-xs">
                {integrationCustomer.integration_type}
              </Badge>
              <span className="text-xs text-muted-foreground">
                ID: {integrationCustomer.external_id}
              </span>
            </div>
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
                className={`flex-1 py-2 px-4 rounded-lg border-2 transition-all ${
                  !createNew
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
                className={`flex-1 py-2 px-4 rounded-lg border-2 transition-all ${
                  createNew
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
                        className={`w-full text-left rounded-lg border p-3 transition-all ${
                          selectedCustomerId === match.customer_id
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
                        className={`w-full text-left p-3 transition-all ${
                          selectedCustomerId === customer.customer_id
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
