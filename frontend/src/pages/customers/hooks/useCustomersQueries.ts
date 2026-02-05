import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { customerApi } from '@/services/api/customers'
import { api } from '@/lib/api'
import type { MapCustomerRequest, UpdateCustomerRequest, MergeCustomersRequest, SplitCustomerRequest } from '@/types/api/customer'

export const customerKeys = {
    all: ['customers'] as const,
    lists: () => [...customerKeys.all, 'list'] as const,
    list: (params: any) => [...customerKeys.lists(), params] as const,
    unmapped: () => [...customerKeys.all, 'unmapped'] as const,
    unmappedList: (page?: number, search?: string) => [...customerKeys.unmapped(), { page, search }] as const,
    integrations: (customerId: number) => [...customerKeys.all, customerId, 'integrations'] as const,
    stats: (customerId: number, integrationCustomerId: number) => [...customerKeys.integrations(customerId), integrationCustomerId, 'stats'] as const,
}

export const useCustomersQuery = (params: {
    page?: number
    search?: string
    statuses?: string[]
    report_filters?: string[]
    integration_types?: string[]
    source_of_truth_only?: boolean | null
}) => {
    return useQuery({
        queryKey: customerKeys.list(params),
        queryFn: () => customerApi.getCustomers(params),
        placeholderData: (previousData) => previousData,
    })
}

export const useUnmappedCustomersQuery = (page?: number, search?: string) => {
    return useQuery({
        queryKey: customerKeys.unmappedList(page, search),
        queryFn: () => customerApi.getUnmappedCustomers(page, search),
        placeholderData: (previousData) => previousData,
    })
}

export const useCustomerIntegrationsQuery = (customerId: number | undefined) => {
    return useQuery({
        queryKey: customerId ? customerKeys.integrations(customerId) : [],
        queryFn: () => customerApi.getCustomerIntegrations(customerId!),
        enabled: !!customerId,
    })
}

export const useCustomerIntegrationStatsQuery = (customerId: number | undefined, integrationCustomerId: number | undefined) => {
    return useQuery({
        queryKey: (customerId && integrationCustomerId) ? customerKeys.stats(customerId, integrationCustomerId) : [],
        queryFn: () => customerApi.getCustomerIntegrationStats(customerId!, integrationCustomerId!),
        enabled: !!customerId && !!integrationCustomerId,
    })
}

export const useMapCustomerMutation = () => {
    const queryClient = useQueryClient()
    return useMutation({
        mutationFn: (data: MapCustomerRequest) => customerApi.mapCustomer(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: customerKeys.all })
        },
    })
}

export const useUpdateCustomerMutation = () => {
    const queryClient = useQueryClient()
    return useMutation({
        mutationFn: ({ customerId, data }: { customerId: number, data: UpdateCustomerRequest }) =>
            customerApi.updateCustomer(customerId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: customerKeys.all })
        },
    })
}

export const useMergeCustomersMutation = () => {
    const queryClient = useQueryClient()
    return useMutation({
        mutationFn: ({ primaryId, data }: { primaryId: number, data: MergeCustomersRequest }) =>
            customerApi.mergeCustomers(primaryId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: customerKeys.all })
        },
    })
}

export const useSplitCustomerMutation = () => {
    const queryClient = useQueryClient()
    return useMutation({
        mutationFn: ({ customerId, data }: { customerId: number, data: SplitCustomerRequest }) =>
            customerApi.splitCustomer(customerId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: customerKeys.all })
        },
    })
}

export const useDeleteMappingMutation = () => {
    const queryClient = useQueryClient()
    return useMutation({
        mutationFn: ({ customerId, mappingId }: { customerId: number, mappingId: number }) =>
            api.delete(`/customers/${customerId}/mappings/${mappingId}`),
        onSuccess: (_, { customerId }) => {
            queryClient.invalidateQueries({ queryKey: customerKeys.all })
            queryClient.invalidateQueries({ queryKey: customerKeys.integrations(customerId) })
        },
    })
}
