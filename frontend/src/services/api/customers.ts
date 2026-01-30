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
        const queryParams = new URLSearchParams()
        if (params?.page) queryParams.append('page', params.page.toString())
        if (params?.search) queryParams.append('search', params.search)

        if (params?.statuses && params.statuses.length > 0) {
            params.statuses.forEach(status => queryParams.append('statuses[]', status))
        }

        if (params?.report_filters && params.report_filters.length > 0) {
            params.report_filters.forEach(filter => queryParams.append('report_filters[]', filter))
        }

        if (params?.integration_types && params.integration_types.length > 0) {
            params.integration_types.forEach(type => queryParams.append('integration_types[]', type))
        }

        if (params?.source_of_truth_only !== null && params?.source_of_truth_only !== undefined) {
            queryParams.append('source_of_truth_only', params.source_of_truth_only.toString())
        }

        const url = `/customers${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
        return api.get<CustomerListResponse>(url)
    },

    /**
     * Get unmapped customers
     * GET /api/customers/unmapped
     */
    async getUnmappedCustomers(page?: number, search?: string): Promise<UnmappedCustomersResponse> {
        const queryParams = new URLSearchParams()
        if (page) queryParams.append('page', page.toString())
        if (search) queryParams.append('search', search)

        const url = `/customers/unmapped${queryParams.toString() ? `?${queryParams.toString()}` : ''}`
        return api.get<UnmappedCustomersResponse>(url)
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
