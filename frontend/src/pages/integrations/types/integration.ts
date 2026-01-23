export type Integration = {
  id: number
  name: string
  type: string
  status: 'active' | 'error' | 'warning' | 'importing_historical' | 'disconnected'
  lastSync: string
  syncStatus: 'success' | 'failed' | 'pending'
  customers: number
  unmappedCustomers?: number
  isSourceOfTruth?: boolean
  nextSyncAt?: string | null
  recordsSynced?: number
  lastError?: string | null
  historicalImportStatus?: string | null
  historicalImportProgress?: number
}

