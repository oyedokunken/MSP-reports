import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { Users, CheckCircle, XCircle } from 'lucide-react'
import { api } from '@/lib/api'
import { Badge } from '@/components/ui/badge'

interface Customer {
  integration_customer_id: number
  external_id: string
  customer_name: string
  active_status: boolean
  last_seen_at: string | null
  customer_metadata: any
}

interface ViewIntegrationCustomersModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integrationId: number | null
  integrationName: string
}

export function ViewIntegrationCustomersModal({ 
  open, 
  onOpenChange, 
  integrationId, 
  integrationName 
}: ViewIntegrationCustomersModalProps) {
  const [isLoading, setIsLoading] = useState(false)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [error, setError] = useState('')

  useEffect(() => {
    if (open && integrationId) {
      fetchCustomers()
    }
  }, [open, integrationId])

  const fetchCustomers = async () => {
    if (!integrationId) return

    try {
      setIsLoading(true)
      setError('')
      const response = await api.get<any>(`/integrations/${integrationId}/customers`)
      
      const data = (response as any).data || response
      setCustomers(data.customers || [])
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'Failed to load customers'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never'
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[700px] max-h-[80vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Users className="h-5 w-5 text-primary" />
            <DialogTitle>Integration Customers</DialogTitle>
          </div>
          <DialogDescription>
            Customers synced from <strong>{integrationName}</strong>
          </DialogDescription>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto">
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Spinner className="h-8 w-8" />
            </div>
          ) : error ? (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
              <p className="text-sm text-destructive font-medium">{error}</p>
            </div>
          ) : customers.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <Users className="h-12 w-12 text-muted-foreground mb-3" />
              <p className="text-lg font-medium text-muted-foreground">No Customers Found</p>
              <p className="text-sm text-muted-foreground mt-1">
                This integration hasn't synced any customers yet
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              {customers.map((customer) => (
                <div
                  key={customer.integration_customer_id}
                  className="rounded-lg border border-border p-4 hover:bg-accent/50 transition-colors"
                >
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex-1">
                      <h4 className="font-semibold text-base">{customer.customer_name}</h4>
                      <p className="text-sm text-muted-foreground">
                        External ID: {customer.external_id}
                      </p>
                    </div>
                    <Badge variant={customer.active_status ? 'default' : 'secondary'}>
                      {customer.active_status ? (
                        <>
                          <CheckCircle className="h-3 w-3 mr-1" />
                          Active
                        </>
                      ) : (
                        <>
                          <XCircle className="h-3 w-3 mr-1" />
                          Inactive
                        </>
                      )}
                    </Badge>
                  </div>
                  <div className="text-xs text-muted-foreground">
                    Last seen: {formatDate(customer.last_seen_at)}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="flex justify-between items-center pt-4 border-t">
          <p className="text-sm text-muted-foreground">
            {customers.length} {customers.length === 1 ? 'customer' : 'customers'} found
          </p>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
