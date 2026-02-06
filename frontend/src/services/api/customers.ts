/**
 * Type-safe API client for Customer endpoints
 * Provides strongly-typed methods for all customer-related API calls
 */

import { api } from '@/lib/api'
import type {
    Customer,
    CustomerListResponse,
    UnmappedCustomersResponse,
    CustomerIntegrationsResponse,
    MapCustomerRequest,
    MapCustomerResponse,
    UpdateCustomerRequest,
    MergeCustomersRequest,
    SplitCustomerRequest,
    IntegrationStatsResponse,
} from '@/types/api/customer'
import type { ApiResponse } from '@/types/api'

export const customerApi = {
    /**
     * Get paginated list of customers
     * GET /api/customers
     */
    async getCustomers(params?: {
        page?: number
        search?: string
        statuses?: string[]
        report_filters?: string[]
        integration_types?: string[]
        source_of_truth_only?: boolean | null
    }): Promise<CustomerListResponse> {
        return api.get<CustomerListResponse>('/customers', {
            ...params,
            // Ensure array params are handled correctly by Axios if needed, 
            // but standard Axios configuration usually handles arrays as statuses[]=...
        })
    },

    /**
     * Get unmapped customers
     * GET /api/customers/unmapped
     */
    async getUnmappedCustomers(page?: number, search?: string): Promise<UnmappedCustomersResponse> {
        return api.get<UnmappedCustomersResponse>('/customers/unmapped', {
            page,
            search: search?.trim() || undefined
        })
    },


    /**
     * Get customer integrations
     * GET /api/customers/{id}/integrations
     */
    async getCustomerIntegrations(customerId: number): Promise<CustomerIntegrationsResponse> {
        return api.get<CustomerIntegrationsResponse>(`/customers/${customerId}/integrations`)
    },

    /**
     * Map an unmapped customer
     * POST /api/customers/map
     */
    async mapCustomer(data: MapCustomerRequest): Promise<MapCustomerResponse> {
        return api.post<MapCustomerResponse>('/customers/map', data)
    },

    /**
     * Update customer details
     * PATCH /api/customers/{id}
     */
    async updateCustomer(
        customerId: number,
        data: UpdateCustomerRequest
    ): Promise<ApiResponse<Customer>> {
        return api.patch<ApiResponse<Customer>>(`/customers/${customerId}`, data)
    },

    /**
     * Merge two customers
     * POST /api/customers/{id}/merge
     */
    async mergeCustomers(
        primaryCustomerId: number,
        data: MergeCustomersRequest
    ): Promise<ApiResponse<Customer>> {
        return api.post<ApiResponse<Customer>>(`/customers/${primaryCustomerId}/merge`, data)
    },

    /**
     * Split a customer
     * POST /api/customers/{id}/split
     */
    async splitCustomer(
        customerId: number,
        data: SplitCustomerRequest
    ): Promise<ApiResponse<{
        original_customer: Customer
        new_customer: Customer
    }>> {
        return api.post<ApiResponse<{
            original_customer: Customer
            new_customer: Customer
        }>>(`/customers/${customerId}/split`, data)
    },

    /**
     * Get all customer IDs for bulk selection
     * GET /api/customers/all-ids
     */
    async getAllCustomerIds(): Promise<ApiResponse<number[]>> {
        return api.get<ApiResponse<number[]>>('/customers/all-ids')
    },

    /**
     * Get integration stats for a customer
     * GET /api/customers/{id}/integrations/{integration_id}/stats
     */
    async getCustomerIntegrationStats(customerId: number, integrationCustomerId: number): Promise<IntegrationStatsResponse> {
        return api.get<IntegrationStatsResponse>(`/customers/${customerId}/integrations/${integrationCustomerId}/stats`)
    },
}
