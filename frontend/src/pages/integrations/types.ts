export type Integration = {
  id: number
  name: string
  type: string
  status: 'active' | 'error' | 'warning'
  lastSync: string
  syncStatus: 'success' | 'failed' | 'pending'
  customers: number
}


