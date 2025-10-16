import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import { Users, Link2, AlertCircle, CheckCircle2, MapPin } from 'lucide-react'
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

export default function CustomerMapping() {
  const toast = useToast()
  const [isLoading, setIsLoading] = useState(true)
  const [customers, setCustomers] = useState<Customer[]>([])
  const [unmappedCount, setUnmappedCount] = useState(0)
  const [mapModalOpen, setMapModalOpen] = useState(false)
  const [selectedIntegrationCustomer, setSelectedIntegrationCustomer] = useState<IntegrationCustomer | null>(null)

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
      
      // Count unmapped customers
      const unmapped = customerData.filter((c: Customer) => c.customer_id === null)
      setUnmappedCount(unmapped.length)
    } catch (err: any) {
      console.error('Failed to load customers:', err)
      toast.error('Failed to load customers', err.response?.data?.message || err.message)
    } finally {
      setIsLoading(false)
    }
  }

  const handleMapCustomer = (integrationCustomer: IntegrationCustomer) => {
    setSelectedIntegrationCustomer(integrationCustomer)
    setMapModalOpen(true)
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never'
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric'
    })
  }

  const mappedCustomers = customers.filter(c => c.customer_id !== null)
  const unmappedCustomers = customers.filter(c => c.customer_id === null)

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground flex items-center gap-2">
              <Users className="h-8 w-8 text-primary" />
              Customer Mapping
            </h1>
            <p className="text-muted-foreground mt-1">
              Manage customer mappings across integrations
            </p>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{mappedCustomers.length}</div>
              <p className="text-xs text-muted-foreground">
                Mapped customers
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Unmapped</CardTitle>
              <AlertCircle className="h-4 w-4 text-amber-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-amber-600">{unmappedCount}</div>
              <p className="text-xs text-muted-foreground">
                Needs mapping
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Mapping Rate</CardTitle>
              <CheckCircle2 className="h-4 w-4 text-green-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">
                {customers.length > 0 
                  ? Math.round((mappedCustomers.length / customers.length) * 100) 
                  : 0}%
              </div>
              <p className="text-xs text-muted-foreground">
                Successfully mapped
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Unmapped Customers Section */}
        {unmappedCount > 0 && (
          <Card className="border-amber-500/50 bg-amber-500/5">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-amber-700">
                <AlertCircle className="h-5 w-5" />
                Unmapped Customers ({unmappedCount})
              </CardTitle>
              <CardDescription>
                These customers from your integrations need to be mapped to existing customers or created as new ones
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 md:grid-cols-2">
                {unmappedCustomers.map((customer, idx) => (
                  <div
                    key={idx}
                    className="relative rounded-lg border border-border bg-card p-5 hover:shadow-md transition-all"
                  >
                    {/* Customer Name */}
                    <div className="mb-4">
                      <h3 className="text-xl font-bold text-foreground mb-1">
                        {customer.integrations[0].name}
                      </h3>
                      <div className="flex items-center gap-2">
                        <Badge variant={customer.integrations[0].active ? 'default' : 'secondary'}>
                          {customer.integrations[0].active ? '✓ Active' : '✗ Inactive'}
                        </Badge>
                      </div>
                    </div>

                    {/* Integration Source */}
                    <div className="space-y-3 mb-4">
                      <div className="flex items-start gap-2">
                        <span className="text-sm font-medium text-muted-foreground min-w-[100px]">Source:</span>
                        <div className="flex flex-col gap-1">
                          <Badge variant="outline" className="w-fit">
                            {customer.integrations[0].integration_type}
                          </Badge>
                          <span className="text-xs text-muted-foreground">
                            {customer.integrations[0].display_name}
                          </span>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-muted-foreground min-w-[100px]">External ID:</span>
                        <code className="text-xs bg-muted px-2 py-1 rounded font-mono">
                          {customer.integrations[0].external_id}
                        </code>
                      </div>

                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-muted-foreground min-w-[100px]">Last Seen:</span>
                        <span className="text-sm text-muted-foreground">
                          {formatDate(customer.integrations[0].last_seen_at)}
                        </span>
                      </div>
                    </div>

                    {/* Action Button */}
                    <Button
                      onClick={() => handleMapCustomer(customer.integrations[0])}
                      className="w-full"
                    >
                      <MapPin className="h-4 w-4 mr-2" />
                      Map to Customer
                    </Button>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Mapped Customers Section */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CheckCircle2 className="h-5 w-5 text-green-500" />
              Mapped Customers ({mappedCustomers.length})
            </CardTitle>
            <CardDescription>
              Customers successfully mapped across integrations
            </CardDescription>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Spinner className="h-8 w-8" />
              </div>
            ) : mappedCustomers.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <Users className="h-12 w-12 text-muted-foreground mb-3" />
                <p className="text-lg font-medium text-muted-foreground">No Mapped Customers</p>
                <p className="text-sm text-muted-foreground mt-1">
                  Map integration customers to create unified customer records
                </p>
              </div>
            ) : (
              <div className="grid gap-4 md:grid-cols-2">
                {mappedCustomers.map((customer) => (
                  <div
                    key={customer.customer_id}
                    className="relative rounded-lg border border-border bg-card p-5 hover:shadow-md transition-all"
                  >
                    {/* Customer Name & Status */}
                    <div className="mb-4">
                      <h3 className="text-xl font-bold text-foreground mb-2">
                        {customer.canonical_name}
                      </h3>
                      <div className="flex items-center gap-2">
                        <Badge variant={customer.status === 'active' ? 'default' : 'secondary'}>
                          {customer.status === 'active' ? '✓ Active' : '✗ Inactive'}
                        </Badge>
                        <Badge variant="outline" className="flex items-center gap-1">
                          <Link2 className="h-3 w-3" />
                          {customer.integrations.length} {customer.integrations.length === 1 ? 'Integration' : 'Integrations'}
                        </Badge>
                      </div>
                    </div>

                    {/* Report Destination */}
                    {customer.report_destination && (
                      <div className="mb-4 pb-4 border-b border-border">
                        <span className="text-sm font-medium text-muted-foreground">Report Email:</span>
                        <div className="mt-1">
                          <code className="text-xs bg-muted px-2 py-1 rounded font-mono">
                            {customer.report_destination}
                          </code>
                        </div>
                      </div>
                    )}

                    {/* Integration Mappings */}
                    <div>
                      <div className="flex items-center gap-2 mb-3">
                        <Link2 className="h-4 w-4 text-muted-foreground" />
                        <span className="text-sm font-semibold text-foreground">
                          Linked Integrations
                        </span>
                      </div>
                      <div className="space-y-2">
                        {customer.integrations.map((integration) => (
                          <div
                            key={integration.integration_customer_id}
                            className="rounded-md border border-border bg-muted/50 p-3 hover:bg-muted transition-colors"
                          >
                            <div className="flex items-start justify-between mb-2">
                              <div className="flex items-center gap-2">
                                <Badge variant="secondary" className="text-xs">
                                  {integration.integration_type}
                                </Badge>
                                <Badge variant={integration.active ? 'default' : 'secondary'} className="text-xs">
                                  {integration.active ? '✓' : '✗'}
                                </Badge>
                              </div>
                            </div>
                            <p className="font-medium text-sm mb-1">{integration.name}</p>
                            <p className="text-xs text-muted-foreground">
                              ID: <code className="bg-muted px-1 py-0.5 rounded">{integration.external_id}</code>
                            </p>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <MapCustomerModal
          open={mapModalOpen}
          onOpenChange={setMapModalOpen}
          integrationCustomer={selectedIntegrationCustomer}
          existingCustomers={mappedCustomers}
          onSuccess={() => {
            fetchCustomers()
            toast.success('Customer Mapped', 'Integration customer mapped successfully')
          }}
        />
      </div>
    </FloatingLayout>
  )
}
