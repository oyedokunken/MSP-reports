import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { AnimatedCard, PulsingDot } from '@/components/ui/AnimatedCard'
import { Button } from '@/components/ui/button'
import { Bell, CheckCheck, X, Clock, Tag } from 'lucide-react'
import { useNotificationStore, type Notification } from '@/stores/useNotificationStore'
import { EmptyState } from '@/components/ui/LoadingStates'

export default function NotificationsPage() {
  const notifications = useNotificationStore((state) => state.notifications)
  const markAsRead = useNotificationStore((state) => state.markAsRead)
  const markAllAsRead = useNotificationStore((state) => state.markAllAsRead)
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)

  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null)

  const unreadCount = getUnreadCount()

  const handleNotificationClick = (notification: Notification) => {
    markAsRead(notification.id)
    setSelectedNotification(notification)
  }

  const closeDetailModal = () => {
    setSelectedNotification(null)
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
                  onClick={() => handleNotificationClick(notification)}
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
                        <span className="text-xs text-muted-foreground flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {notification.time}
                        </span>
                        <span className="text-xs rounded-full bg-muted px-2 py-0.5 flex items-center gap-1">
                          <Tag className="h-3 w-3" />
                          {notification.type}
                        </span>
                      </div>
                      <p className="text-xs text-muted-foreground mt-2 italic">
                        Click to view details
                      </p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </AnimatedCard>
      </div>

      {/* Notification Detail Modal */}
      {selectedNotification && (
        <div
          className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
          onClick={closeDetailModal}
        >
          <div
            className="bg-background dark:bg-[hsl(var(--auth-card))] rounded-xl border border-border shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header */}
            <div className="p-6 border-b border-border flex items-start justify-between">
              <div className="flex items-start gap-4 flex-1">
                <div className="flex-shrink-0 mt-1">
                  <PulsingDot color={selectedNotification.severity} />
                </div>
                <div className="flex-1 min-w-0">
                  <h2 className="text-xl font-bold text-foreground">
                    {selectedNotification.title}
                  </h2>
                  <div className="flex items-center gap-3 mt-2">
                    <span className="text-sm text-muted-foreground flex items-center gap-1">
                      <Clock className="h-4 w-4" />
                      {selectedNotification.time}
                    </span>
                    <span className="text-sm rounded-full bg-muted px-3 py-1 flex items-center gap-1">
                      <Tag className="h-4 w-4" />
                      {selectedNotification.type}
                    </span>
                  </div>
                </div>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={closeDetailModal}
                className="h-8 w-8 p-0"
              >
                <X className="h-5 w-5" />
              </Button>
            </div>

            {/* Content */}
            <div className="p-6 space-y-4">
              <div>
                <h3 className="text-sm font-semibold text-foreground mb-2">Details</h3>
                <div className="bg-muted/50 rounded-lg p-4 space-y-2">
                  <div className="flex items-start gap-2">
                    <span className="text-sm font-medium text-foreground min-w-24">Event Type:</span>
                    <span className="text-sm text-muted-foreground capitalize">{selectedNotification.type}</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <span className="text-sm font-medium text-foreground min-w-24">Severity:</span>
                    <span className="text-sm text-muted-foreground capitalize">{selectedNotification.severity}</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <span className="text-sm font-medium text-foreground min-w-24">Status:</span>
                    <span className="text-sm text-muted-foreground">{selectedNotification.read ? 'Read' : 'Unread'}</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <span className="text-sm font-medium text-foreground min-w-24">Timestamp:</span>
                    <span className="text-sm text-muted-foreground">{selectedNotification.time}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 className="text-sm font-semibold text-foreground mb-2">Description</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">
                  {selectedNotification.title}
                </p>
              </div>

              {selectedNotification.type === 'sync' && (
                <div>
                  <h3 className="text-sm font-semibold text-foreground mb-2">Sync Information</h3>
                  <div className="bg-muted/50 rounded-lg p-4 space-y-2">
                    <p className="text-sm text-muted-foreground">
                      This notification indicates that a data synchronization process has been completed.
                      The integration has successfully pulled the latest data from the external system.
                    </p>
                  </div>
                </div>
              )}

              {selectedNotification.type === 'report' && (
                <div>
                  <h3 className="text-sm font-semibold text-foreground mb-2">Report Information</h3>
                  <div className="bg-muted/50 rounded-lg p-4 space-y-2">
                    <p className="text-sm text-muted-foreground">
                      This notification indicates that scheduled reports have been generated successfully.
                      Reports are now available for download or have been delivered to configured recipients.
                    </p>
                  </div>
                </div>
              )}

              {selectedNotification.type === 'alert' && (
                <div>
                  <h3 className="text-sm font-semibold text-foreground mb-2">Alert Information</h3>
                  <div className="bg-muted/50 rounded-lg p-4 space-y-2">
                    <p className="text-sm text-muted-foreground">
                      This is a system alert that requires your attention. Please review the details above
                      and take appropriate action if necessary.
                    </p>
                  </div>
                </div>
              )}
            </div>

            {/* Footer */}
            <div className="p-6 border-t border-border flex justify-end gap-2">
              {!selectedNotification.read && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => markAsRead(selectedNotification.id)}
                >
                  Mark as Read
                </Button>
              )}
              <Button
                variant="default"
                size="sm"
                onClick={closeDetailModal}
              >
                Close
              </Button>
            </div>
          </div>
        </div>
      )}
    </FloatingLayout>
  )
}
