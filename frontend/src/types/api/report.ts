/**
 * Report API types
 * Matches backend report endpoints
 */

import type { ApiSuccessResponse } from './common'

export interface GeneratedReport {
    generated_report_id: number
    customer_id: number
    customer_name: string
    report_type: string
    generated_at: string
    status: 'pending' | 'generating' | 'completed' | 'failed'
    file_size: number
    delivered: boolean
    delivered_at: string | null
    delivery_method: string
    error_message: string | null
}

export interface ReportHistoryResponse {
    status: 'success'
    data: {
        reports: GeneratedReport[]
        pagination: {
            current_page: number
            per_page: number
            total: number
            last_page: number
        }
    }
}

export type DownloadReportResponse = Blob

export type DeleteReportResponse = ApiSuccessResponse<{ message: string }>
