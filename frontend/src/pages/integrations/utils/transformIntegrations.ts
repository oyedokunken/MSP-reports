import type { Integration } from '../types/integration'

export const transformIntegrations = (responseData: unknown): Integration[] => {
  const data = responseData as {
    configured?: Array<{
      integration_id: number
      display_name: string
      integration_type?: string
      status: string
      last_sync_at?: string
      last_sync_status?: string
      unmapped_customers_count?: number
      total_customers_count?: number
      is_source_of_truth?: boolean
    }>
  }

  return (data.configured || []).map((int) => ({
    id: int.integration_id,
    name: int.display_name,
    type: int.integration_type?.toLowerCase() || '',
    status: int.status as Integration['status'],
    lastSync: int.last_sync_at
      ? new Date(int.last_sync_at).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
      : 'Never',
    syncStatus: (int.last_sync_status || 'pending') as Integration['syncStatus'],
    customers: int.total_customers_count || 0,
    isSourceOfTruth: int.is_source_of_truth || false,
  }))
}
