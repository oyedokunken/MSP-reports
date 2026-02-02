import { z } from 'zod'
import type { Integration } from '../types/integration'

const DISPLAY_TIMEZONE = 'Australia/Darwin'

const ApiIntegrationSchema = z.object({
  integration_id: z.number(),
  display_name: z.string(),
  integration_type: z.string().nullable().optional(),
  status: z.string(),
  last_sync_at: z.string().nullable().optional(),
  last_sync_status: z.string().nullable().optional(),
  unmapped_customers_count: z.number().nullable().optional(),
  total_customers_count: z.number().nullable().optional(),
  is_source_of_truth: z.boolean().nullable().optional(),
  next_sync_at: z.string().nullable().optional(),
  records_synced: z.number().nullable().optional(),
  last_error: z.string().nullable().optional(),
  historical_import_status: z.string().nullable().optional(),
  historical_import_progress: z.number().nullable().optional(),
})

const IntegrationsResponseSchema = z.object({
  configured: z.array(ApiIntegrationSchema).optional(),
})

export const transformIntegrations = (responseData: unknown): Integration[] => {
  const parsed = IntegrationsResponseSchema.safeParse(responseData)
  if (!parsed.success) {
    return []
  }

  const configured = parsed.data.configured ?? []

  return configured.map((int) => ({
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
        timeZone: DISPLAY_TIMEZONE,
      })
      : 'Never',
    syncStatus: (int.last_sync_status || 'pending') as Integration['syncStatus'],
    customers: int.total_customers_count || 0,
    unmappedCustomers: int.unmapped_customers_count || 0,
    isSourceOfTruth: int.is_source_of_truth || false,
    nextSyncAt: int.next_sync_at,
    recordsSynced: int.records_synced || 0,
    lastError: int.last_error,
    historicalImportStatus: int.historical_import_status,
    historicalImportProgress: int.historical_import_progress || 0,
  }))
}

