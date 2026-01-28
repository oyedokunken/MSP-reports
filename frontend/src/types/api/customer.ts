/**
 * Customer API types
 * Matches backend CustomerMappingController response structure
 */

import type { ApiSuccessResponse, PaginatedResponse } from './common'

export interface CustomerMapping {
    type: 'auto' | 'manual'
    confidence_score: number
    mapped_at: string
    notes: string | null
}

export interface IntegrationCustomer {
    integration_customer_id: number
    integration_type: string
    display_name: string
    external_id: string
    name: string
    active: boolean
    last_seen_at: number | null // Unix timestamp in milliseconds
    mapping?: CustomerMapping | null
}

export interface Customer {
    customer_id: number
    canonical_name: string
    status: 'active' | 'inactive'
    report_destination: string | null
    report_schedules_count: number
    integrations: IntegrationCustomer[]
}

export interface SuggestedMatch {
    customer_id: number
    canonical_name: string
    confidence_score: number
}

export interface UnmappedCustomer {
    integration_customer_id: number
    integration_type: string
    display_name: string
    external_id: string
    name: string
    active: boolean
    last_seen_at: number | null
    suggested_matches?: SuggestedMatch[]
}

export interface UnmappedSummary {
    total_unmapped: number
    by_integration_type: Record<string, number>
}

export type CustomerListResponse = PaginatedResponse<Customer>

export type UnmappedCustomersResponse = PaginatedResponse<UnmappedCustomer> & {
    summary: UnmappedSummary
}

export type CustomerIntegrationsResponse = ApiSuccessResponse<IntegrationCustomer[]>

export interface MapCustomerRequest {
    integration_customer_id: number
    customer_id?: number
    create_new?: boolean
    canonical_name?: string
    status?: 'active' | 'inactive'
    report_destination?: string
    metadata?: Record<string, unknown>
}

export type MapCustomerResponse = ApiSuccessResponse<{
    integration_customer_id: number
    customer_id: number
    canonical_name: string
    created_new: boolean
}>

export interface UpdateCustomerRequest {
    canonical_name?: string
    status?: 'active' | 'inactive'
    report_destination?: string | null
    metadata?: Record<string, unknown>
}

export interface MergeCustomersRequest {
    merge_customer_id: number
}

export interface SplitCustomerRequest {
    new_customer_name: string
    integration_customer_ids: number[]
}
