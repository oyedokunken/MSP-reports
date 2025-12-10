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
} from '@/types/api/customer'
import type { ApiSuccessResponse } from '@/types/api/common'

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
    }): Promise<CustomerListResponse> {
        const queryParams = new URLSearchParams()
        if (params?.page) queryParams.append('page', params.page.toString())
        if (params?.search) queryParams.append('search', params.search)
        
        // Add multiple status filters
        if (params?.statuses && params.statuses.length > 0) {
            params.statuses.forEach(status => queryParams.append('statuses[]', status))
        }
        
        // Add multiple report filters
        if (params?.report_filters && params.report_filters.length > 0) {
            params.report_filters.forEach(filter => queryParams.append('report_filters[]', filter))
        }
        
        // Add multiple integration type filters
        if (params?.integration_types && params.integration_types.length > 0) {
            params.integration_types.forEach(type => queryParams.append('integration_types[]', type))
        }

        const url = `/customers${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
        return api.get(url) as unknown as Promise<CustomerListResponse>
    },

    /**
     * Get unmapped customers
     * GET /api/customers/unmapped
     */
    async getUnmappedCustomers(page?: number): Promise<UnmappedCustomersResponse> {
        const url = page ? `/customers/unmapped?page=${page}` : '/customers/unmapped'
        return api.get(url) as unknown as Promise<UnmappedCustomersResponse>
    },

    /**
     * Get customer integrations
     * GET /api/customers/{id}/integrations
     */
    async getCustomerIntegrations(customerId: number): Promise<CustomerIntegrationsResponse> {
        return api.get(`/customers/${customerId}/integrations`) as unknown as Promise<CustomerIntegrationsResponse>
    },

    /**
     * Map an unmapped customer
     * POST /api/customers/map
     */
    async mapCustomer(data: MapCustomerRequest): Promise<MapCustomerResponse> {
        return api.post('/customers/map', data) as unknown as Promise<MapCustomerResponse>
    },

    /**
     * Update customer details
     * PATCH /api/customers/{id}
     */
    async updateCustomer(
        customerId: number,
        data: UpdateCustomerRequest
    ): Promise<ApiSuccessResponse<Customer>> {
        return api.patch(`/customers/${customerId}`, data) as unknown as Promise<ApiSuccessResponse<Customer>>
    },

    /**
     * Merge two customers
     * POST /api/customers/{id}/merge
     */
    async mergeCustomers(
        primaryCustomerId: number,
        data: MergeCustomersRequest
    ): Promise<ApiSuccessResponse<Customer>> {
        return api.post(`/customers/${primaryCustomerId}/merge`, data) as unknown as Promise<
            ApiSuccessResponse<Customer>
        >
    },

    /**
     * Split a customer
     * POST /api/customers/{id}/split
     */
    async splitCustomer(
        customerId: number,
        data: SplitCustomerRequest
    ): Promise<
        ApiSuccessResponse<{
            original_customer: Customer
            new_customer: Customer
        }>
    > {
        return api.post(`/customers/${customerId}/split`, data) as unknown as Promise<
            ApiSuccessResponse<{
                original_customer: Customer
                new_customer: Customer
            }>
        >
    },
}
