import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { AnimatedCard, PulsingDot } from '@/components/ui/AnimatedCard'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Bell, CheckCheck, X, Clock, Tag, Trash2, CheckSquare, Square } from 'lucide-react'
import { useNotificationStore, type Notification } from '@/stores/useNotificationStore'
import { EmptyState } from '@/components/ui/LoadingStates'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { useToast } from '@/components/ui/Toast'

export default function NotificationsPage() {
  const toast = useToast()
  const notifications = useNotificationStore((state) => state.notifications)
  const selectedIds = useNotificationStore((state) => state.selectedIds)
  const markAsRead = useNotificationStore((state) => state.markAsRead)
  const markAllAsRead = useNotificationStore((state) => state.markAllAsRead)
  const deleteNotification = useNotificationStore((state) => state.deleteNotification)
  const deleteSelected = useNotificationStore((state) => state.deleteSelected)
  const deleteAllRead = useNotificationStore((state) => state.deleteAllRead)
  const toggleSelect = useNotificationStore((state) => state.toggleSelect)
  const selectAll = useNotificationStore((state) => state.selectAll)
  const deselectAll = useNotificationStore((state) => state.deselectAll)
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)
  const getSelectedCount = useNotificationStore((state) => state.getSelectedCount)

  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null)
  const [showMarkAllConfirm, setShowMarkAllConfirm] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<number | null>(null)
  const [showDeleteAllConfirm, setShowDeleteAllConfirm] = useState(false)
  const [showDeleteSelectedConfirm, setShowDeleteSelectedConfirm] = useState(false)
  const [selectionMode, setSelectionMode] = useState(false)

  const unreadCount = getUnreadCount()
  const readCount = notifications.filter(n => n.read).length
  const selectedCount = getSelectedCount()

  const handleNotificationClick = (notification: Notification) => {
    markAsRead(notification.id)
    setSelectedNotification(notification)
  }

  const closeDetailModal = () => {
    setSelectedNotification(null)
  }

  const handleMarkAllAsRead = () => {
    markAllAsRead()
    setShowMarkAllConfirm(false)
    toast.success('All notifications marked as read')
  }

  const handleDeleteNotification = (id: number) => {
    deleteNotification(id)
    setShowDeleteConfirm(null)
    toast.success('Notification deleted')
  }

  const handleDeleteAllRead = () => {
    deleteAllRead()
    setShowDeleteAllConfirm(false)
    toast.success('All read notifications deleted')
  }

  const handleDeleteSelected = () => {
    deleteSelected()
    setShowDeleteSelectedConfirm(false)
    setSelectionMode(false)
    toast.success(`${selectedCount} notification(s) deleted`)
  }

  const toggleSelectionMode = () => {
    if (selectionMode) {
      deselectAll()
    }
    setSelectionMode(!selectionMode)
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
          <div className="flex gap-2">
            {notifications.length > 0 && (
              <Button
                variant={selectionMode ? "default" : "outline"}
                size="sm"
                onClick={toggleSelectionMode}
                className="gap-2"
              >
                {selectionMode ? <CheckSquare className="h-4 w-4" /> : <Square className="h-4 w-4" />}
                {selectionMode ? 'Done' : 'Select'}
              </Button>
            )}
            {selectionMode && selectedCount > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowDeleteSelectedConfirm(true)}
                className="gap-2 text-red-600 hover:text-red-700"
              >
                <Trash2 className="h-4 w-4" />
                Delete ({selectedCount})
              </Button>
            )}
            {selectionMode && notifications.length > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={selectedCount === notifications.length ? deselectAll : selectAll}
                className="gap-2"
              >
                {selectedCount === notifications.length ? 'Deselect All' : 'Select All'}
              </Button>
            )}
            {!selectionMode && unreadCount > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowMarkAllConfirm(true)}
                className="gap-2"
              >
                <CheckCheck className="h-4 w-4" />
                Mark all as read
              </Button>
            )}
            {!selectionMode && readCount > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowDeleteAllConfirm(true)}
                className="gap-2 text-red-600 hover:text-red-700"
              >
                <Trash2 className="h-4 w-4" />
                Delete read
              </Button>
            )}
          </div>
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
                  className={`p-4 hover:bg-muted/50 transition-colors group ${
                    !notification.read ? 'bg-primary/5' : ''
                  } ${selectedIds.has(notification.id) ? 'bg-primary/10' : ''}`}
                >
                  <div className="flex items-start gap-4">
                    {selectionMode && (
                      <div className="flex-shrink-0 mt-1">
                        <Checkbox
                          checked={selectedIds.has(notification.id)}
                          onCheckedChange={() => toggleSelect(notification.id)}
                        />
                      </div>
                    )}
                    <div className="flex-shrink-0 mt-1">
                      <PulsingDot color={notification.severity} />
                    </div>
                    <div 
                      className="flex-1 min-w-0 cursor-pointer"
                      onClick={() => selectionMode ? toggleSelect(notification.id) : handleNotificationClick(notification)}
                    >
                      <div className="flex items-start justify-between gap-2">
                        <h3 className="text-sm font-medium text-foreground">
                          {notification.title}
                        </h3>
                        {!notification.read && (
                          <span className="flex h-2 w-2 rounded-full bg-blue-500 flex-shrink-0 mt-1" />
                        )}
                      </div>
                      {notification.description && (
                        <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                          {notification.description}
                        </p>
                      )}
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
                      {!selectionMode && (
                        <p className="text-xs text-muted-foreground mt-2 italic">
                          Click to view details
                        </p>
                      )}
                    </div>
                    {!selectionMode && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                          e.stopPropagation()
                          setShowDeleteConfirm(notification.id)
                        }}
                        className="opacity-0 group-hover:opacity-100 transition-opacity h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/20"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
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

      {/* Confirmation Modals */}
      <ConfirmationModal
        isOpen={showMarkAllConfirm}
        onClose={() => setShowMarkAllConfirm(false)}
        onConfirm={handleMarkAllAsRead}
        title="Mark All as Read"
        description={`Are you sure you want to mark all ${unreadCount} unread notifications as read? This action cannot be undone.`}
        confirmText="Mark All as Read"
        cancelText="Cancel"
        type="info"
      />

      <ConfirmationModal
        isOpen={showDeleteConfirm !== null}
        onClose={() => setShowDeleteConfirm(null)}
        onConfirm={() => showDeleteConfirm && handleDeleteNotification(showDeleteConfirm)}
        title="Delete Notification"
        description="Are you sure you want to delete this notification? This action cannot be undone."
        confirmText="Delete"
        cancelText="Cancel"
        type="danger"
      />

      <ConfirmationModal
        isOpen={showDeleteAllConfirm}
        onClose={() => setShowDeleteAllConfirm(false)}
        onConfirm={handleDeleteAllRead}
        title="Delete All Read Notifications"
        description={`Are you sure you want to delete all ${readCount} read notifications? This action cannot be undone.`}
        confirmText="Delete All"
        cancelText="Cancel"
        type="danger"
      />

      <ConfirmationModal
        isOpen={showDeleteSelectedConfirm}
        onClose={() => setShowDeleteSelectedConfirm(false)}
        onConfirm={handleDeleteSelected}
        title="Delete Selected Notifications"
        description={`Are you sure you want to delete ${selectedCount} selected notification(s)? This action cannot be undone.`}
        confirmText="Delete Selected"
        cancelText="Cancel"
        type="danger"
      />
    </FloatingLayout>
  )
}
