<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Notification\GetNotificationsRequest;
use App\Http\Requests\Notification\MarkNotificationRequest;
use App\Http\Requests\Notification\MarkAllNotificationsRequest;
use App\Http\Requests\Notification\DeleteNotificationRequest;
use App\Http\Requests\Notification\DeleteAllReadNotificationsRequest;
use Illuminate\Support\Facades\Auth;

final class NotificationController extends Controller
{
    /**
     * Get paginated notifications for the authenticated user
     */
    public function index(GetNotificationsRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? $request->input('per_page', 20);
        
        $notifications = $user->notifications()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $notifications->map(function($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->data['type'] ?? 'info',
                    'title' => $n->data['title'] ?? 'Notification',
                    'description' => $n->data['description'] ?? '',
                    'severity' => $n->data['severity'] ?? 'blue',
                    'read' => !is_null($n->read_at),
                    'created_at' => $n->created_at->toIso8601String(),
                    'metadata' => $n->data['metadata'] ?? null
                ];
            }),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $user->unreadNotifications()->count()
            ]
        ]);
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead(MarkNotificationRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);
        
        // No need for "if (!$notification)" because authorize() handles it (if it existed)
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(MarkAllNotificationsRequest $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(DeleteNotificationRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead(DeleteAllReadNotificationsRequest $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->readNotifications()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'All read notifications deleted'
        ]);
    }
}
