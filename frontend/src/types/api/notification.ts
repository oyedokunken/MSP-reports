import type { PaginatedResponse, ApiSuccessResponse } from './common'

export interface ApiNotification {
    id: string
    type: string
    title: string
    description: string
    severity: 'green' | 'yellow' | 'red' | 'blue'
    read: boolean
    created_at: string
    metadata: Record<string, unknown> | null
}

export interface NotificationListResponse extends PaginatedResponse<ApiNotification> {
    pagination: PaginatedResponse<ApiNotification>['pagination'] & {
        unread_count: number
    }
}

export type MarkReadResponse = ApiSuccessResponse<void>
export type MarkAllReadResponse = ApiSuccessResponse<void>
export type DeleteNotificationResponse = ApiSuccessResponse<void>
export type DeleteAllReadResponse = ApiSuccessResponse<void>
