import { api } from '@/lib/api'
import type {
    NotificationListResponse,
    MarkReadResponse,
    MarkAllReadResponse,
    DeleteNotificationResponse,
    DeleteAllReadResponse
} from '@/types/api/notification'

export const notificationApi = {
    /**
     * Get paginated notifications
     * GET /api/notifications
     */
    async getNotifications(page: number = 1, perPage: number = 20): Promise<NotificationListResponse> {
        return api.get<NotificationListResponse>(`/notifications?page=${page}&per_page=${perPage}`)
    },

    /**
     * Mark notification as read
     * PATCH /api/notifications/{id}/read
     */
    async markAsRead(id: string | number): Promise<MarkReadResponse> {
        return api.patch<MarkReadResponse>(`/notifications/${id}/read`)
    },

    /**
     * Mark all notifications as read
     * POST /api/notifications/mark-all-read
     */
    async markAllAsRead(): Promise<MarkAllReadResponse> {
        return api.post<MarkAllReadResponse>('/notifications/mark-all-read')
    },

    /**
     * Delete a notification
     * DELETE /api/notifications/{id}
     */
    async deleteNotification(id: string | number): Promise<DeleteNotificationResponse> {
        return api.delete<DeleteNotificationResponse>(`/notifications/${id}`)
    },

    /**
     * Delete all read notifications
     * DELETE /api/notifications/read
     */
    async deleteAllRead(): Promise<DeleteAllReadResponse> {
        return api.delete<DeleteAllReadResponse>('/notifications/read')
    }
}
