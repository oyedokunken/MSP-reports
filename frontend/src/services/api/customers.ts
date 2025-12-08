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
        status?: string
        report_filter?: string
    }): Promise<CustomerListResponse> {
        const queryParams = new URLSearchParams()
        if (params?.page) queryParams.append('page', params.page.toString())
        if (params?.search) queryParams.append('search', params.search)
        if (params?.status && params.status !== 'all') queryParams.append('status', params.status)
        if (params?.report_filter && params.report_filter !== 'all') {
            queryParams.append('report_filter', params.report_filter)
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
