export interface GeneratedReport {
  generated_report_id: number
  customer_id: number
  customer_name: string
  report_type: string
  generated_at: string
  status: string
  file_size: number
  delivered: boolean
  delivered_at: string | null
  delivery_method: string
  error_message: string | null
}

export type ReportStatus = 'completed' | 'failed' | 'pending' | 'generating'
