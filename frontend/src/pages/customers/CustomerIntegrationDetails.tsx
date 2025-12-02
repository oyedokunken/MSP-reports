import { useParams, useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ArrowLeft } from 'lucide-react'
import { useCustomerIntegrationDetails } from './hooks'
import { LoadingState, ErrorState, EmptyState } from './components/IntegrationStates'
import { IntegrationCardHeader } from './components/IntegrationCardHeader'
import { TechniciansList } from './components/TechniciansList'
import DattoCustomerDetails from '@/components/integrations/DattoCustomerDetails'
import AutotaskCustomerDetails from '@/components/integrations/AutotaskCustomerDetails'

export default function CustomerIntegrationDetails() {
  const { customerId } = useParams<{ customerId: string }>()
  const navigate = useNavigate()
  const { integrations, isLoading, error, customerName, stats } = useCustomerIntegrationDetails(customerId)

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="sm" onClick={() => navigate('/platform/customers')}>
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Customers
            </Button>
            <div>
              <h1 className="text-2xl font-bold">Customer Integration Details</h1>
              {customerName && <p className="text-muted-foreground">{customerName}</p>}
            </div>
          </div>
        </div>

        <LoadingState isLoading={isLoading} />
        <ErrorState error={error} customerId={customerId} />
        <EmptyState show={!isLoading && !error && integrations.length === 0} />

        {!isLoading &&
          !error &&
          integrations.map((integration) => (
            <Card key={integration.integration_customer_id} className="overflow-hidden">
              <IntegrationCardHeader integration={integration} />

              <CardContent className="p-6">
                {integration.metadata && Object.keys(integration.metadata).length > 0 ? (
                  <div className="space-y-4">
                    <TechniciansList
                      technicians={stats[integration.integration_customer_id]?.tickets?.top_technicians || []}
                    />

                    {integration.integration_type.toLowerCase() === 'datto' && (
                      <DattoCustomerDetails metadata={integration.metadata} />
                    )}
                    {integration.integration_type.toLowerCase() === 'autotask' && (
                      <AutotaskCustomerDetails metadata={integration.metadata} />
                    )}
                  </div>
                ) : (
                  <p className="text-center text-muted-foreground py-4">No metadata available</p>
                )}
              </CardContent>
            </Card>
          ))}
      </div>
    </FloatingLayout>
  )
}
