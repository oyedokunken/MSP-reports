import { useQueries } from '@tanstack/react-query'
import { customerApi } from '@/services/api/customers'
import { useCustomerIntegrationsQuery, customerKeys } from './useCustomersQueries'
import type { IntegrationStats } from '../types'

export const useCustomerIntegrationDetails = (customerId: string | undefined) => {
    const numericId = customerId ? parseInt(customerId, 10) : undefined

    const {
        data: integrationsResponse,
        isLoading: isLoadingIntegrations,
        error: integrationsError
    } = useCustomerIntegrationsQuery(numericId)

    const integrations = integrationsResponse?.data || []

    // Fetch stats for all integrations in parallel using useQueries
    const statsQueries = useQueries({
        queries: integrations.map((integration) => ({
            queryKey: customerKeys.stats(numericId!, integration.integration_customer_id),
            queryFn: () => customerApi.getCustomerIntegrationStats(numericId!, integration.integration_customer_id),
            enabled: !!numericId,
        }))
    })

    const isLoadingStats = statsQueries.some(query => query.isLoading)
    const statsError = statsQueries.find(query => query.error)?.error

    const stats: { [key: number]: IntegrationStats } = {}
    const loadingStats: { [key: number]: boolean } = {}

    integrations.forEach((integration, index) => {
        const query = statsQueries[index]
        if (query.data) {
            stats[integration.integration_customer_id] = query.data.data
        }
        loadingStats[integration.integration_customer_id] = query.isLoading
    })

    const customerName = integrations.length > 0 ? integrations[0].name : ''

    return {
        integrations,
        isLoading: isLoadingIntegrations || isLoadingStats,
        error: integrationsError || statsError ? ((integrationsError || statsError) as Error).message : null,
        customerName,
        stats,
        loadingStats,
    }
}
