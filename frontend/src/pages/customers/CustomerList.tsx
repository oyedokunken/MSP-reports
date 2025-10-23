import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import { Users, FileText, Settings, Link2, MapPin, Building2 } from 'lucide-react'
import { api } from '@/lib/api'
import { MapCustomerModal } from '@/components/modals/MapCustomerModal'

interface IntegrationCustomer {
  integration_customer_id: number
  integration_type: string
  display_name: string    
  external_id: string
  name: string
  active: boolean
  last_seen_at: string | null
}

interface Customer {
  customer_id: number | null
  canonical_name: string | null
  status: string | null
  report_destination: string | null
  integrations: IntegrationCustomer[]
}

export default function CustomerList() {
  const navigate = useNavigate()
  const toast = useToast()
  const [isLoading, setIsLoading] = useState(true)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [mapModalOpen, setMapModalOpen] = useState(false)
  const [selectedUnmappedCustomer, setSelectedUnmappedCustomer] = useState<IntegrationCustomer | null>(null)
  const [_currentUnmappedIndex, setCurrentUnmappedIndex] = useState(0)

  useEffect(() => {
    fetchCustomers()
  }, [])

  const fetchCustomers = async () => {
    try {
      setIsLoading(true)
      const response = await api.get<Customer[]>('/customers')
      
      const data = (response as any).data || response
      const customerData = data.data || data
      
      setCustomers(customerData)
    } catch (err: any) {
      toast.error('Failed to load customers', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const mappedCustomers = customers.filter(c => c.customer_id !== null)
  const unmappedCustomers = customers.filter(c => c.customer_id === null)

  const handleStartMapping = () => {
    if (unmappedCustomers.length > 0) {
      setCurrentUnmappedIndex(0)
      setSelectedUnmappedCustomer(unmappedCustomers[0].integrations[0])
      setMapModalOpen(true)
    }
  }

  const handleMapSuccess = async () => {
    toast.success('Customer Mapped', 'Integration customer mapped successfully')
    
    // Refresh customer list first
    try {
      setIsLoading(true)
      const response = await api.get<Customer[]>('/customers')
      
      const data = (response as any).data || response
      const customerData = data.data || data
      
      setCustomers(customerData)
      
      // Get updated unmapped list
      const updatedUnmapped = customerData.filter((c: Customer) => c.customer_id === null)
      
      // If there are more unmapped customers, show the next one
      if (updatedUnmapped.length > 0) {
        setSelectedUnmappedCustomer(updatedUnmapped[0].integrations[0])
        setCurrentUnmappedIndex(0)
        // Modal stays open for next customer
      } else {
        // No more unmapped customers, close modal
        setMapModalOpen(false)
        setCurrentUnmappedIndex(0)
        toast.success('All Done!', 'All customers have been mapped')
      }
    } catch (err: any) {
      toast.error('Failed to refresh', err.response?.data?.message || err.message)
      setMapModalOpen(false)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold text-foreground">Customers</h1>
          <p className="mt-2 text-muted-foreground">
            Manage customers and configure their report schedules
          </p>
        </div>

        {/* Unmapped Warning - Clear & Actionable */}
        {unmappedCustomers.length > 0 && (
          <Card className="border-blue-200 bg-blue-50">
            <CardContent className="py-4">
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3 flex-1">
                  <div className="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 flex-shrink-0">
                    <Building2 className="h-6 w-6 text-blue-600" />
                  </div>
                  <div className="flex-1">
                    <p className="font-semibold text-foreground text-lg mb-1">
                      {unmappedCustomers.length} New Client{unmappedCustomers.length !== 1 ? 's' : ''} from Integrations
                    </p>
                    <p className="text-sm text-muted-foreground mb-3">
                      Your integrations (Autotask, Datto, etc.) have synced customer data. 
                      Link them to your existing clients or create new client records to start generating reports.
                    </p>
                    <div className="flex items-center gap-2 text-sm">
                      <div className="flex items-center gap-1">
                        <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                        <span className="text-muted-foreground">Step 1: Review synced customers</span>
                      </div>
                      <span className="text-muted-foreground">→</span>
                      <div className="flex items-center gap-1">
                        <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                        <span className="text-muted-foreground">Step 2: Map to clients</span>
                      </div>
                      <span className="text-muted-foreground">→</span>
                      <div className="flex items-center gap-1">
                        <div className="w-2 h-2 rounded-full bg-gray-300"></div>
                        <span className="text-muted-foreground">Step 3: Configure reports</span>
                      </div>
                    </div>
                  </div>
                </div>
                <Button 
                  size="lg"
                  onClick={handleStartMapping}
                  className="flex-shrink-0"
                >
                  <MapPin className="h-4 w-4 mr-2" />
                  Start Mapping ({unmappedCustomers.length})
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Content */}
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Spinner className="h-8 w-8" />
          </div>
        ) : mappedCustomers.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <Users className="h-12 w-12 text-muted-foreground mb-4" />
              <p className="text-lg font-medium">No Mapped Customers</p>
              <p className="text-sm text-muted-foreground">
                {unmappedCustomers.length > 0 
                  ? 'Map the unmapped customers above to get started'
                  : 'Integration customers will appear here once synced'}
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {mappedCustomers.map((customer) => (
              <Card key={customer.customer_id} className="flex flex-col">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                      <CardTitle className="text-base truncate">
                        {customer.canonical_name}
                      </CardTitle>
                      <CardDescription className="text-xs mt-1">
                        {customer.integrations.length} integration{customer.integrations.length !== 1 ? 's' : ''}
                      </CardDescription>
                    </div>
                    <Badge variant={customer.status === 'active' ? 'default' : 'secondary'}>
                      {customer.status === 'active' ? 'Active' : 'Inactive'}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="flex-1 space-y-3">
                  {/* Integrations */}
                  <div className="space-y-2">
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      <Link2 className="h-3 w-3" />
                      <span>Linked Integrations</span>
                    </div>
                    <div className="flex flex-wrap gap-1">
                      {customer.integrations.map((integration) => (
                        <Badge key={integration.integration_customer_id} variant="outline" className="text-xs">
                          {integration.integration_type}
                        </Badge>
                      ))}
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex gap-2 pt-2">
                    <Button
                      size="sm"
                      variant="outline"
                      className="flex-1"
                      onClick={() => navigate(`/customers/${customer.customer_id}/configure-reports`)}
                    >
                      <Settings className="h-4 w-4 mr-2" />
                      Configure
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      className="flex-1"
                      onClick={() => navigate(`/customers/${customer.customer_id}/reports`)}
                    >
                      <FileText className="h-4 w-4 mr-2" />
                      Reports
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        {/* Mapping Modal */}
        <MapCustomerModal
          open={mapModalOpen}
          onOpenChange={setMapModalOpen}
          integrationCustomer={selectedUnmappedCustomer}
          existingCustomers={mappedCustomers}
          onSuccess={handleMapSuccess}
        />
      </div>
    </FloatingLayout>
  )
}
