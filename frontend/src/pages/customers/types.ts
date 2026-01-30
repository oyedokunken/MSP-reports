import type {
  IntegrationStats,
  IntegrationCustomer,
  SuggestedMatch as ApiSuggestedMatch
} from '@/types/api/customer'

export type { IntegrationStats, IntegrationCustomer }

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

export interface ExistingCustomer {
  customer_id: number | null
  canonical_name: string | null
  status: string | null
  integrations: IntegrationCustomer[]
}

export interface SuggestedMatch extends ApiSuggestedMatch {
  integrations?: Array<{ id: number }>
}

export interface MapCustomerModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integrationCustomers: IntegrationCustomer[]
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

export interface CustomerIntegrationMapping {
  type: 'auto' | 'manual'
  confidence_score: number
  mapped_at: string
  notes?: string
  mapped_by?: string
}

export interface CustomerIntegrationDetail {
  integration_customer_id: number
  integration_id: number
  integration_type: string
  display_name: string
  external_id: string
  name: string
  active: boolean
  last_seen_at: string | number | null
  metadata?: Record<string, unknown>
  mapping?: CustomerIntegrationMapping
}

export interface CustomerIntegrationsResponse {
  status: string
  data: CustomerIntegrationDetail[]
}

