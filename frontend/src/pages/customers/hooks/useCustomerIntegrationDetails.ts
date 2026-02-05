import { useState, useEffect } from 'react'
import { logger } from '@/lib/logger'
import { customerApi } from '@/services/api/customers'
import type { IntegrationCustomer, IntegrationStats } from '../types'

export const useCustomerIntegrationDetails = (customerId: string | undefined) => {
    const [integrations, setIntegrations] = useState<IntegrationCustomer[]>([])
    const [isLoading, setIsLoading] = useState(true)
    const [error, setError] = useState<string | null>(null)
    const [customerName, setCustomerName] = useState<string>('')
    const [stats, setStats] = useState<{ [key: number]: IntegrationStats }>({})
    const [loadingStats, setLoadingStats] = useState<{ [key: number]: boolean }>({})

    useEffect(() => {
        const fetchIntegrationDetails = async () => {
            if (!customerId) return

            try {
                setIsLoading(true)
                setError(null)

                const numericId = parseInt(customerId, 10)
                if (isNaN(numericId)) throw new Error('Invalid customer ID')

                // Use the type-safe API service
                const response = await customerApi.getCustomerIntegrations(numericId)

                // Response data is already unwrapped by our apiRequest helper
                const integrationData = response.data || []
                setIntegrations(integrationData)

                if (integrationData.length > 0) {
                    setCustomerName(integrationData[0].name)

                    // Fetch stats for each integration
                    // Using for...of for sequential or Promise.all for parallel
                    // Parallel is better here
                    await Promise.all(
                        integrationData.map(async (integration) => {
                            const integrationCustomerId = integration.integration_customer_id
                            setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: true }))

                            try {
                                const statsResponse = await customerApi.getCustomerIntegrationStats(numericId, integrationCustomerId)
                                const data = statsResponse.data
                                setStats(prev => ({ ...prev, [integrationCustomerId]: data }))
                            } catch (err) {
                                logger.error(`Failed to fetch stats for integration ${integrationCustomerId}`, err)
                            } finally {
                                setLoadingStats(prev => ({ ...prev, [integrationCustomerId]: false }))
                            }
                        })
                    )
                }
            } catch (err) {
                const errorMessage = err instanceof Error ? err.message : 'Failed to load integration details'
                setError(errorMessage)
                logger.error('fetchIntegrationDetails error:', err)
            } finally {
                setIsLoading(false)
            }
        }

        fetchIntegrationDetails()
    }, [customerId])

    return {
        integrations,
        isLoading,
        error,
        customerName,
        stats,
        loadingStats,
    }
}
