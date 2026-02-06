/**
 * Customer API types
 * Matches backend CustomerMappingController response structure
 */

import type { ApiSuccessResponse, PaginatedResponse } from './common'
import * as schemas from '@/schemas/customer'

export type CustomerMapping = schemas.CustomerMapping
export type IntegrationCustomer = schemas.IntegrationCustomer
export type Customer = schemas.Customer
export type SuggestedMatch = schemas.SuggestedMatch
export type UnmappedCustomer = schemas.UnmappedCustomer
export type UnmappedSummary = schemas.UnmappedSummary

export type CustomerListResponse = PaginatedResponse<Customer>

export type UnmappedCustomersResponse = PaginatedResponse<UnmappedCustomer> & {
    summary: UnmappedSummary
}

export type CustomerIntegrationsResponse = ApiSuccessResponse<IntegrationCustomer[]>

export type MapCustomerRequest = schemas.MapCustomerRequest

export type MapCustomerResponse = ApiSuccessResponse<{
    integration_customer_id: number
    customer_id: number
    canonical_name: string
    created_new: boolean
}>

export type UpdateCustomerRequest = schemas.UpdateCustomerRequest
export type MergeCustomersRequest = schemas.MergeCustomersRequest
export type SplitCustomerRequest = schemas.SplitCustomerRequest
export type IntegrationStats = schemas.IntegrationStats

export type IntegrationStatsResponse = ApiSuccessResponse<IntegrationStats>

