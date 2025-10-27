import type { Integration } from '../types/integration'

export const transformIntegrations = (responseData: any): Integration[] => {
  return (responseData.configured || []).map((int: any) => ({
    id: int.integration_id,
    name: int.display_name,
    type: int.integration_type?.toLowerCase(),
    status: int.status,
    lastSync: int.last_sync_at
      ? new Date(int.last_sync_at).toLocaleString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })
      : 'Never',
    syncStatus: int.last_sync_status || 'pending',
    customers: int.unmapped_customers_count || 0,
  }))
}
