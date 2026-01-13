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
        return api.get(`/notifications?page=${page}&per_page=${perPage}`) as unknown as Promise<NotificationListResponse>
    },

    /**
     * Mark notification as read
     * PATCH /api/notifications/{id}/read
     */
    async markAsRead(id: string | number): Promise<MarkReadResponse> {
        return api.patch(`/notifications/${id}/read`) as unknown as Promise<MarkReadResponse>
    },

    /**
     * Mark all notifications as read
     * POST /api/notifications/mark-all-read
     */
    async markAllAsRead(): Promise<MarkAllReadResponse> {
        return api.post('/notifications/mark-all-read') as unknown as Promise<MarkAllReadResponse>
    },

    /**
     * Delete a notification
     * DELETE /api/notifications/{id}
     */
    async deleteNotification(id: string | number): Promise<DeleteNotificationResponse> {
        return api.delete(`/notifications/${id}`) as unknown as Promise<DeleteNotificationResponse>
    },

    /**
     * Delete all read notifications
     * DELETE /api/notifications/read
     */
    async deleteAllRead(): Promise<DeleteAllReadResponse> {
        return api.delete('/notifications/read') as unknown as Promise<DeleteAllReadResponse>
    }
}
