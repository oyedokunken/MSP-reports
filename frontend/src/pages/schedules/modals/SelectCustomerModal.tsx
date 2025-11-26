import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Search, Users, ArrowRight } from 'lucide-react'
import { Spinner } from '@/components/ui/spinner'
import { api } from '@/lib/api'

interface Customer {
  customer_id: number
  canonical_name: string
  status: string
  integration_count: number
  report_schedules_count: number
}

interface SelectCustomerModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function SelectCustomerModal({ open, onOpenChange }: SelectCustomerModalProps) {
  const navigate = useNavigate()
  const [customers, setCustomers] = useState<Customer[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')

  useEffect(() => {
    if (open) {
      fetchCustomers()
    }
  }, [open])

  const fetchCustomers = async () => {
    try {
      setIsLoading(true)
      const response = await api.get('/customers?per_page=100')
      const data = (response as any)
      const customerData = data.data || []
      setCustomers(customerData.filter((c: any) => c.customer_id !== null))
    } catch (err) {
      console.error('Failed to fetch customers:', err)
    } finally {
      setIsLoading(false)
    }
  }

  const filteredCustomers = customers.filter(customer =>
    customer.canonical_name.toLowerCase().includes(searchQuery.toLowerCase())
  )

  const handleSelectCustomer = (customerId: number) => {
    navigate(`/platform/customers/${customerId}/configure-reports`)
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Users className="h-5 w-5" />
            Select Customer for Schedule
          </DialogTitle>
          <DialogDescription>
            Choose a customer to configure automatic report schedules
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 flex-1 overflow-hidden flex flex-col">
          {/* Search */}
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search customers..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9"
            />
          </div>

          {/* Customer List */}
          {isLoading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner className="h-8 w-8" />
            </div>
          ) : filteredCustomers.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-8 text-center">
              <Users className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-sm text-muted-foreground">
                {searchQuery ? 'No customers found matching your search' : 'No customers available'}
              </p>
            </div>
          ) : (
            <div className="flex-1 overflow-y-auto space-y-2 pr-2">
              {filteredCustomers.map((customer) => (
                <button
                  key={customer.customer_id}
                  onClick={() => handleSelectCustomer(customer.customer_id)}
                  className="w-full p-4 rounded-lg border border-border bg-card hover:bg-accent transition-colors text-left group"
                >
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="font-medium text-foreground truncate">
                          {customer.canonical_name}
                        </h3>
                        <Badge variant={customer.status === 'active' ? 'default' : 'secondary'} className="flex-shrink-0">
                          {customer.status}
                        </Badge>
                      </div>
                      <div className="flex items-center gap-4 text-xs text-muted-foreground">
                        <span>{customer.integration_count} integration{customer.integration_count !== 1 ? 's' : ''}</span>
                        <span>
                          {customer.report_schedules_count || 0} schedule{customer.report_schedules_count !== 1 ? 's' : ''}
                        </span>
                      </div>
                    </div>
                    <ArrowRight className="h-5 w-5 text-muted-foreground group-hover:text-foreground group-hover:translate-x-1 transition-all flex-shrink-0" />
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="flex justify-end pt-4 border-t">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
