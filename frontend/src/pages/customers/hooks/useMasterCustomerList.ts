import { useQuery } from '@tanstack/react-query'
import { logger } from '@/lib/logger'
import { integrationsApi } from '@/services/api/integrations'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'
import { useBaseCustomerList } from './useBaseCustomerList'

export const useMasterCustomerList = () => {
    const base = useBaseCustomerList(true)

    const { user } = useAuth()
    const canViewIntegrations = hasPermission(user, 'integrations', 'view')

    const { data: integrationsData } = useQuery({
        queryKey: ['integrations'],
        queryFn: () => integrationsApi.getIntegrations(),
        enabled: canViewIntegrations,
        meta: {
            onError: (err: any) => logger.error('Failed to fetch SOT integration:', err)
        }
    })

    const sotIntegration = integrationsData?.configured?.find((int) => int.is_source_of_truth === true)
    const sotIntegrationName = sotIntegration
        ? (sotIntegration.display_name || sotIntegration.integration_type)
        : null

    return {
        ...base,
        sotIntegrationName,
    }
}
