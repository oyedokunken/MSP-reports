export type CanonicalCustomer = {
  id: string
  name: string
}

export type MappingRow = {
  id: number
  externalName: string
  integration: string
  mappedCustomerId: string
  confidence: number
  status: 'mapped' | 'pending'
}

export type Stat = {
  label: string
  value: string
  color: string
}

// MapCustomerModal types
export interface IntegrationCustomer {
  integration_customer_id: number
  integration_type: string
  display_name: string
  external_id: string
  name: string
  active: boolean
  last_seen_at: string | null
}

export interface ExistingCustomer {
  customer_id: number | null
  canonical_name: string | null
  status: string | null
  integrations: any[]
}

export interface SuggestedMatch {
  customer_id: number
  canonical_name: string
  confidence_score: number
  integrations?: any[]
}

export interface MapCustomerModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integrationCustomer: IntegrationCustomer | null
  existingCustomers: ExistingCustomer[]
  onSuccess?: () => void
}

export interface Customer {
  customer_id: number
  name: string
  canonical_name?: string
  status: string
  integration_count: number
  report_destination?: string | null
  integrations?: IntegrationCustomer[]
  report_schedules_count?: number
  has_reports_configured?: boolean
}
