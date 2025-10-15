export type Trend = {
  value: string
  isPositive: boolean
}

export type Stat = {
  label: string
  value: string
  icon: any
  trend: Trend
}

export type Integration = {
  id: number
  name: string
  type: string
  status: 'active' | 'error' | 'warning'
  lastSync: string
  customers: number
  syncStatus: 'success' | 'failed' | 'pending'
}

export type UpcomingReport = {
  id: number
  name: string
  customer: string
  nextRun: string
  status: 'scheduled' | 'pending' | 'paused'
}

export type RecentActivity = {
  id: number
  type: string
  title: string
  time: string
  severity: 'green' | 'yellow' | 'red'
}


