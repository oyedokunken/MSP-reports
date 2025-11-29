import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { api } from '@/lib/api'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Spinner } from '@/components/ui/spinner'
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  ArrowLeft,
  Cable,
  CheckCircle2,
  XCircle,
  ExternalLink,
  Phone,
  MapPin,
  Building2,
  Calendar,
  Settings,
  Mail,
  Users,
} from 'lucide-react'
import type { CustomerIntegrationDetail } from './types'

interface IntegrationStats {
  tickets?: {
    total: number
    open: number
    completed: number
    top_technicians?: Array<{
      name: string
      ticket_count: number
    }>
  }
  time_entries?: {
    total: number
    total_hours: number
    billable_hours: number
  }
  contracts?: {
    total: number
    active: number
  }
}

const formatValue = (value: any): string => {
  if (!value && value !== 0 && value !== false) return '-'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (value instanceof Date || (typeof value === 'string' && value.includes('T'))) {
    try {
      return new Date(value).toLocaleDateString()
    } catch {
      return String(value)
    }
  }
  return String(value)
}

interface MetadataTableProps {
  data: Record<string, any>
  fields: { key: string; label: string; link?: boolean }[]
}

const MetadataTable = ({ data, fields }: MetadataTableProps) => {
  const visibleFields = fields.filter(field => 
    data[field.key] !== undefined && 
    data[field.key] !== null && 
    data[field.key] !== ''
  )
  
  if (visibleFields.length === 0) {
    return <p className="text-sm text-muted-foreground text-center py-4">No data available</p>
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-1/3">Field</TableHead>
          <TableHead>Value</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {visibleFields.map((field) => (
          <TableRow key={field.key}>
            <TableCell className="font-medium text-muted-foreground">{field.label}</TableCell>
            <TableCell>
              {field.link && data[field.key] ? (
                <a
                  href={data[field.key].startsWith('http') ? data[field.key] : `https://${data[field.key]}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary hover:underline inline-flex items-center gap-1"
                >
                  {data[field.key]}
                  <ExternalLink className="h-3 w-3" />
                </a>
              ) : (
                formatValue(data[field.key])
              )}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

export default function CustomerIntegrationDetails() {
  const { customerId } = useParams<{ customerId: string }>()
  const navigate = useNavigate()
  const [integrations, setIntegrations] = useState<CustomerIntegrationDetail[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [customerName, setCustomerName] = useState<string>('')
  const [stats, setStats] = useState<{[key: number]: IntegrationStats}>({})
  const [loadingStats, setLoadingStats] = useState<{[key: number]: boolean}>({})

  useEffect(() => {
    if (customerId) {
      fetchIntegrationDetails()
    }
  }, [customerId])

  const fetchIntegrationDetails = async () => {
    try {
      setIsLoading(true)
      setError(null)

      const response = await api.get<any>(`/customers/${customerId}/integrations`)
      
      console.log('API Response:', response)

      let integrationData: CustomerIntegrationDetail[] = []
      
      if (response && (response as any).status === 'success' && Array.isArray((response as any).data)) {
        integrationData = (response as any).data
      } 
      else if (response && response.success && Array.isArray(response.data)) {
        integrationData = response.data
      }
      else if (response && Array.isArray(response.data)) {
        integrationData = response.data
      }
      else if (Array.isArray(response)) {
        integrationData = response
      }
      else {
        console.error('Unexpected response format:', response)
        setError('Failed to load integration details - unexpected response format')
        return
      }

      setIntegrations(integrationData)
      
      if (integrationData.length > 0) {
        setCustomerName(integrationData[0].name)
        // Fetch stats for each integration
        integrationData.forEach(integration => {
          fetchStats(integration.integration_customer_id)
        })
      }
    } catch (err: any) {
      console.error('Error fetching integration details:', err)
      console.error('Error details:', {
        message: err.message,
        response: err.response,
        responseData: err.response?.data,
      })
      
      const errorMessage = err.message || err.response?.data?.message || 'Failed to load integration details'
      setError(errorMessage)
    } finally {
      setIsLoading(false)
    }
  }

  const fetchStats = async (integrationCustomerId: number) => {
    if (stats[integrationCustomerId] || loadingStats[integrationCustomerId]) return

    setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: true }))

    try {
      const response = await api.get(
        `/customers/${customerId}/integrations/${integrationCustomerId}/stats`
      )
      const data = (response as any).data || response
      setStats(prev => ({ ...prev, [integrationCustomerId]: data }))
    } catch (err: any) {
      console.error('Failed to load stats:', err)
    } finally {
      setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: false }))
    }
  }

  const contactFields = [
    { key: 'phone', label: 'Phone' },
    { key: 'fax', label: 'Fax' },
    { key: 'alternate_phone1', label: 'Alternate Phone 1' },
    { key: 'alternate_phone2', label: 'Alternate Phone 2' },
    { key: 'website', label: 'Website', link: true },
  ]

  const addressFields = [
    { key: 'address1', label: 'Address 1' },
    { key: 'address2', label: 'Address 2' },
    { key: 'city', label: 'City' },
    { key: 'state', label: 'State' },
    { key: 'postal_code', label: 'Postal Code' },
    { key: 'country_id', label: 'Country ID' },
    { key: 'additional_address_info', label: 'Additional Info' },
  ]

  const billingAddressFields = [
    { key: 'billing_address1', label: 'Billing Address 1' },
    { key: 'billing_address2', label: 'Billing Address 2' },
    { key: 'bill_to_city', label: 'City' },
    { key: 'bill_to_state', label: 'State' },
    { key: 'bill_to_zip_code', label: 'ZIP Code' },
    { key: 'bill_to_country_id', label: 'Country ID' },
    { key: 'bill_to_attention', label: 'Attention' },
    { key: 'bill_to_additional_address_info', label: 'Additional Info' },
  ]

  const companyFields = [
    { key: 'company_number', label: 'Company Number' },
    { key: 'company_type', label: 'Company Type' },
    { key: 'classification', label: 'Classification' },
    { key: 'sic_code', label: 'SIC Code' },
    { key: 'tax_id', label: 'Tax ID' },
    { key: 'is_tax_exempt', label: 'Tax Exempt' },
    { key: 'stock_symbol', label: 'Stock Symbol' },
    { key: 'stock_market', label: 'Stock Market' },
    { key: 'asset_value', label: 'Asset Value' },
    { key: 'currency_id', label: 'Currency ID' },
  ]

  const datesFields = [
    { key: 'create_date', label: 'Created Date' },
    { key: 'last_activity_date', label: 'Last Activity' },
    { key: 'last_tracked_modified_date_time', label: 'Last Modified' },
  ]

  const systemFields = [
    { key: 'is_client_portal_active', label: 'Client Portal Active' },
    { key: 'is_task_fire_active', label: 'Task Fire Active' },
    { key: 'is_enabled_for_comanaged', label: 'Comanaged Enabled' },
    { key: 'owner_resource_id', label: 'Owner Resource ID' },
    { key: 'created_by_resource_id', label: 'Created By Resource ID' },
    { key: 'territory_id', label: 'Territory ID' },
    { key: 'parent_company_id', label: 'Parent Company ID' },
    { key: 'api_vendor_id', label: 'API Vendor ID' },
  ]

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
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

        {/* Loading State */}
        {isLoading && (
          <div className="flex items-center justify-center py-12">
            <Spinner className="h-8 w-8" />
          </div>
        )}

        {/* Error State */}
        {error && (
          <Card className="border-destructive">
            <CardContent className="pt-6">
              <div className="space-y-2">
                <div className="flex items-center gap-2 text-destructive">
                  <XCircle className="h-5 w-5" />
                  <p className="font-medium">{error}</p>
                </div>
                <p className="text-sm text-muted-foreground">
                  Customer ID: {customerId} | Endpoint: /api/customers/{customerId}/integrations
                </p>
                <p className="text-xs text-muted-foreground">
                  Check the browser console for more details.
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Integration Details */}
        {!isLoading && !error && integrations.length === 0 && (
          <Card>
            <CardContent className="pt-6">
              <div className="text-center py-8 text-muted-foreground">
                <Cable className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No integrations found for this customer</p>
              </div>
            </CardContent>
          </Card>
        )}

        {!isLoading &&
          !error &&
          integrations.map((integration) => (
            <Card key={integration.integration_customer_id} className="overflow-hidden">
              <CardHeader className="bg-gradient-to-r from-primary/5 to-primary/10 border-b">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                      <Cable className="h-5 w-5 text-primary" />
                      <CardTitle>{integration.display_name}</CardTitle>
                      <Badge variant="outline" className="capitalize">
                        {integration.integration_type}
                      </Badge>
                      <Badge variant={integration.active ? 'default' : 'secondary'}>
                        {integration.active ? (
                          <>
                            <CheckCircle2 className="h-3 w-3 mr-1" />
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
                    <CardDescription>
                      External ID: {integration.external_id} • Last Seen:{' '}
                      {new Date(integration.last_seen_at).toLocaleString()}
                    </CardDescription>
                  </div>
                </div>

                {/* Mapping Info */}
                <div className="mt-4 p-3 bg-background/50 rounded-lg">
                  <div className="flex items-center gap-4 text-sm">
                    <div className="flex items-center gap-2">
                      <span className="text-muted-foreground">Mapping Type:</span>
                      <Badge variant={integration.mapping.type === 'auto' ? 'default' : 'secondary'}>
                        {integration.mapping.type}
                      </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-muted-foreground">Confidence:</span>
                      <span className="font-medium">{integration.mapping.confidence_score}%</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-muted-foreground">Mapped:</span>
                      <span className="font-medium">
                        {new Date(integration.mapping.mapped_at).toLocaleDateString()}
                      </span>
                    </div>
                  </div>
                  {integration.mapping.notes && (
                    <p className="mt-2 text-sm text-muted-foreground">{integration.mapping.notes}</p>
                  )}
                </div>
              </CardHeader>

              <CardContent className="p-6">
                {integration.metadata && Object.keys(integration.metadata).length > 0 ? (
                  <Accordion type="multiple" className="w-full">
                    {/* Technicians Section */}
                    {(() => {
                      const topTechnicians = stats[integration.integration_customer_id]?.tickets?.top_technicians
                      return topTechnicians && topTechnicians.length > 0 && (
                        <AccordionItem value="technicians">
                          <AccordionTrigger className="hover:no-underline">
                            <div className="flex items-center gap-2">
                              <Users className="h-4 w-4 text-primary" />
                              <span className="font-semibold">Assigned Technicians</span>
                              <Badge variant="secondary" className="ml-2">
                                {topTechnicians.length}
                              </Badge>
                            </div>
                          </AccordionTrigger>
                          <AccordionContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                              {topTechnicians.map((tech, idx) => (
                                <div key={idx} className="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border">
                                  <Users className="h-4 w-4 text-primary flex-shrink-0" />
                                  <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">{tech.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                      {tech.ticket_count} {tech.ticket_count === 1 ? 'ticket' : 'tickets'}
                                    </p>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </AccordionContent>
                        </AccordionItem>
                      )
                    })()}

                    <AccordionItem value="contact">
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <Phone className="h-4 w-4 text-primary" />
                          <span className="font-semibold">Contact Information</span>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <MetadataTable data={integration.metadata} fields={contactFields} />
                      </AccordionContent>
                    </AccordionItem>

                    <AccordionItem value="address">
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <MapPin className="h-4 w-4 text-primary" />
                          <span className="font-semibold">Primary Address</span>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <MetadataTable data={integration.metadata} fields={addressFields} />
                      </AccordionContent>
                    </AccordionItem>

                    <AccordionItem value="billing">
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <Mail className="h-4 w-4 text-primary" />
                          <span className="font-semibold">Billing Address</span>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <MetadataTable data={integration.metadata} fields={billingAddressFields} />
                      </AccordionContent>
                    </AccordionItem>

                    <AccordionItem value="company">
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <Building2 className="h-4 w-4 text-primary" />
                          <span className="font-semibold">Company Information</span>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <MetadataTable data={integration.metadata} fields={companyFields} />
                      </AccordionContent>
                    </AccordionItem>

                    <AccordionItem value="dates">
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <Calendar className="h-4 w-4 text-primary" />
                          <span className="font-semibold">Important Dates</span>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <MetadataTable data={integration.metadata} fields={datesFields} />
                      </AccordionContent>
                    </AccordionItem>

                    <AccordionItem value="system">
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <Settings className="h-4 w-4 text-primary" />
                          <span className="font-semibold">System Information</span>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <MetadataTable data={integration.metadata} fields={systemFields} />
                      </AccordionContent>
                    </AccordionItem>
                  </Accordion>
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
