export type ReportStatus = 'completed' | 'failed' | 'pending'

export type ReportItem = {
  id: number
  name: string
  customer: string
  schedule: string
  lastGenerated: string
  status: ReportStatus
  nextRun: string
}

export type Stat = {
  label: string
  value: string
  color: string
}


