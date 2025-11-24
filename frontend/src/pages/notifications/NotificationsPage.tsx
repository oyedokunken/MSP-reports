import { useEffect } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { AnimatedCard, PulsingDot } from '@/components/ui/AnimatedCard'
import { Button } from '@/components/ui/button'
import { Bell, CheckCheck } from 'lucide-react'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { EmptyState } from '@/components/ui/LoadingStates'

export default function NotificationsPage() {
  const notifications = useNotificationStore((state) => state.notifications)
  const markAsRead = useNotificationStore((state) => state.markAsRead)
  const markAllAsRead = useNotificationStore((state) => state.markAllAsRead)
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)

  const unreadCount = getUnreadCount()

  const handleNotificationClick = (id: number) => {
    markAsRead(id)
  }

  const handleMarkAllAsRead = () => {
    markAllAsRead()
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Notifications</h1>
            <p className="mt-2 text-muted-foreground">
              Stay updated with your system activities and events
            </p>
          </div>
          {unreadCount > 0 && (
            <Button
              variant="outline"
              size="sm"
              onClick={handleMarkAllAsRead}
              className="gap-2"
            >
              <CheckCheck className="h-4 w-4" />
              Mark all as read
            </Button>
          )}
        </div>

        <AnimatedCard className="p-0 overflow-hidden">
          {notifications.length === 0 ? (
            <div className="p-12">
              <EmptyState
                icon={Bell}
                title="No notifications"
                description="You're all caught up! New notifications will appear here."
              />
            </div>
          ) : (
            <div className="divide-y divide-border">
              {notifications.map((notification) => (
                <div
                  key={notification.id}
                  onClick={() => handleNotificationClick(notification.id)}
                  className={`p-4 hover:bg-muted/50 transition-colors cursor-pointer ${
                    !notification.read ? 'bg-primary/5' : ''
                  }`}
                >
                  <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 mt-1">
                      <PulsingDot color={notification.severity} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <h3 className="text-sm font-medium text-foreground">
                          {notification.title}
                        </h3>
                        {!notification.read && (
                          <span className="flex h-2 w-2 rounded-full bg-blue-500 flex-shrink-0 mt-1" />
                        )}
                      </div>
                      <div className="flex items-center gap-2 mt-1">
                        <span className="text-xs text-muted-foreground">
                          {notification.time}
                        </span>
                        <span className="text-xs rounded-full bg-muted px-2 py-0.5">
                          {notification.type}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </AnimatedCard>
      </div>
    </FloatingLayout>
  )
}
