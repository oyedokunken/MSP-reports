import { api } from '@/lib/api'

export interface Integration {
    integration_id: number
    display_name: string
    integration_type: string
    status: string
    is_source_of_truth: boolean
    configured_at: string
    last_sync_at: string
    // Add other fields as needed
}

export interface IntegrationsResponse {
    configured: Integration[]
    available: any[]
}

export const integrationsApi = {
    async getIntegrations(): Promise<IntegrationsResponse> {
        return api.get<IntegrationsResponse>('/integrations')
    },

    async syncIntegration(integrationId: number): Promise<void> {
        return api.post(`/integrations/${integrationId}/sync`, {})
    }
}
