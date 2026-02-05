import { useState, useEffect } from 'react'
import { logger } from '@/lib/logger'
import { api } from '@/lib/api'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'
import { useBaseCustomerList } from './useBaseCustomerList'

export const useMasterCustomerList = () => {
    const base = useBaseCustomerList(true)
    const [sotIntegrationName, setSotIntegrationName] = useState<string | null>(null)

    const { user } = useAuth()
    const canViewIntegrations = hasPermission(user, 'integrations', 'view')

    useEffect(() => {
        if (!canViewIntegrations) return

        api.get('/integrations').then((response: unknown) => {
            const typedResponse = response as { configured?: Array<{ is_source_of_truth?: boolean; display_name?: string; integration_type?: string }> }
            const sotIntegration = typedResponse.configured?.find((int) => int.is_source_of_truth === true)
            if (sotIntegration) {
                setSotIntegrationName(sotIntegration.display_name || sotIntegration.integration_type || null)
            }
        }).catch((err: unknown) => {
            logger.error('Failed to fetch SOT integration:', err)
        })
    }, [canViewIntegrations])

    return {
        ...base,
        sotIntegrationName,
    }
}
