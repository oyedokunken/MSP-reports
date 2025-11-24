import { Link, useNavigate } from 'react-router-dom'
import { Settings, ChevronDown, Bell, LogOut } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'
import { ThemeToggle } from '@/components/ui/theme-toggle'
import { useState, useRef, useEffect } from 'react'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { useNotificationStore, type Notification } from '@/stores/useNotificationStore'
import { PulsingDot } from '@/components/ui/AnimatedCard'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { useToast } from '@/components/ui/Toast'

/**
 * Floating Top Navbar
 * Full-width navbar with logo, theme toggle, and user menu
 */

export default function FloatingNav() {
  const toast = useToast()
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false)
  const [isNotificationMenuOpen, setIsNotificationMenuOpen] = useState(false)
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false)
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null)
  const [isScrolled, setIsScrolled] = useState(false)
  const userMenuRef = useRef<HTMLDivElement>(null)
  const notificationMenuRef = useRef<HTMLDivElement>(null)

  // Get notifications from store
  const notifications = useNotificationStore((state) => state.notifications)
  const getUnreadCount = useNotificationStore((state) => state.getUnreadCount)
  const markAsRead = useNotificationStore((state) => state.markAsRead)

  const unreadCount = getUnreadCount()
  const hasUnread = unreadCount > 0

  const handleLogout = async () => {
    try {
      setShowLogoutConfirm(false)
      // Perform logout first
      await logout()
      // Then navigate
      navigate('/login', { replace: true })
      // Show toast after navigation
      setTimeout(() => {
        toast.success('Logged Out', 'You have been successfully logged out')
      }, 100)
    } catch (error) {
      console.error('Logout error:', error)
      toast.error('Logout Failed', 'An error occurred during logout')
    }
  }

  const handleNotificationClick = (notification: Notification) => {
    markAsRead(notification.id)
    setSelectedNotification(notification)
    setIsNotificationMenuOpen(false)
  }

  // Handle scroll for header shadow effect
  useEffect(() => {
    const mainElement = document.querySelector('main')
    if (!mainElement) return

    const handleScroll = () => {
      setIsScrolled(mainElement.scrollTop > 10)
    }

    mainElement.addEventListener('scroll', handleScroll)
    return () => mainElement.removeEventListener('scroll', handleScroll)
  }, [])

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
        setIsUserMenuOpen(false)
      }
      if (notificationMenuRef.current && !notificationMenuRef.current.contains(event.target as Node)) {
        setIsNotificationMenuOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <header className={`
      flex shrink-0 items-center gap-2 border-b bg-background px-4
      transition-all duration-300 ease-in-out
      ${isScrolled ? 'h-12 shadow-md' : 'h-14'}
    `}>
      <div className="flex flex-1 items-center gap-2">
        <SidebarTrigger className="-ml-1" />
        <Separator orientation="vertical" className="mr-2 h-4" />
      </div>

      <div className="flex items-center gap-3">
            {/* Notifications */}
            <div className="relative" ref={notificationMenuRef}>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setIsNotificationMenuOpen(!isNotificationMenuOpen)}
                className="relative h-9 w-9 p-0"
              >
                <Bell className="h-5 w-5" />
                {hasUnread && (
                  <span className="absolute top-1 right-1 flex h-2 w-2 rounded-full bg-red-500" />
                )}
              </Button>

              {/* Notifications Dropdown */}
              {isNotificationMenuOpen && (
                <div className="absolute right-0 top-full mt-2 w-80 rounded-xl border border-gray-200 dark:border-gray-800 bg-background dark:bg-[hsl(var(--auth-card))] shadow-xl animate-in fade-in slide-in-from-top-2 duration-200 max-h-96 overflow-hidden z-50">
                  <div className="p-4 border-b border-border">
                    <div className="flex items-center justify-between">
                      <h3 className="font-semibold text-foreground">Notifications</h3>
                      {unreadCount > 0 && (
                        <span className="text-xs px-2 py-1 rounded-full bg-secondary text-secondary-foreground">
                          {unreadCount} new
                        </span>
                      )}
                    </div>
                  </div>
                  
                  <div className="max-h-80 overflow-y-auto">
                    {notifications.length === 0 ? (
                      <div className="p-8 text-center text-sm text-muted-foreground">
                        No notifications
                      </div>
                    ) : (
                      notifications.slice(0, 5).map((notification) => (
                        <div
                          key={notification.id}
                          onClick={() => handleNotificationClick(notification)}
                          className={`p-3 hover:bg-muted/50 cursor-pointer border-b border-border last:border-b-0 transition-colors ${
                            !notification.read ? 'bg-primary/5' : ''
                          }`}
                        >
                          <div className="flex items-start gap-3">
                            <div className="flex-shrink-0 mt-1">
                              <PulsingDot color={notification.severity} />
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className="text-sm font-medium text-foreground truncate">
                                {notification.title}
                              </p>
                              <p className="text-xs text-muted-foreground mt-1">
                                {notification.time}
                              </p>
                            </div>
                            {!notification.read && (
                              <span className="flex h-2 w-2 rounded-full bg-blue-500 flex-shrink-0 mt-2" />
                            )}
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                  
                  {notifications.length > 0 && (
                    <div className="p-2 border-t border-border">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="w-full text-xs"
                        onClick={() => {
                          setIsNotificationMenuOpen(false)
                          navigate('/notifications')
                        }}
                      >
                        View all notifications
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Theme Toggle */}
            <ThemeToggle />

            {/* User Menu */}
            <div className="relative" ref={userMenuRef}>
              <button
                onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
              >
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground">
                  {user?.name?.charAt(0).toUpperCase() || 'U'}
                </div>
                <span className="hidden sm:inline-block text-foreground">
                  {user?.name || 'User'}
                </span>
                <ChevronDown className="h-4 w-4 text-muted-foreground" />
              </button>

              {/* Dropdown Menu */}
              {isUserMenuOpen && (
                <div className="absolute right-0 top-full mt-2 w-56 rounded-xl border border-gray-200 dark:border-gray-800 bg-background dark:bg-[hsl(var(--auth-card))] shadow-xl animate-in fade-in slide-in-from-top-2 duration-200 z-50">
                  <div className="p-4 border-b border-border">
                    <p className="text-sm font-medium text-foreground">
                      {user?.name || 'User'}
                    </p>
                    <p className="text-xs text-muted-foreground mt-1">
                      {user?.email || 'user@example.com'}
                    </p>
                  </div>
                  <div className="p-2">
                    <Link
                      to="/settings"
                      className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-muted"
                      onClick={() => setIsUserMenuOpen(false)}
                    >
                      <Settings className="h-4 w-4" />
                      <span>Settings</span>
                    </Link>
                    <button
                      onClick={() => {
                        setShowLogoutConfirm(true)
                        setIsUserMenuOpen(false)
                      }}
                      className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-red-600 dark:text-red-400 transition-colors hover:bg-red-50 dark:hover:bg-red-950/20"
                    >
                      <LogOut className="h-4 w-4" />
                      <span>Logout</span>
                    </button>
                  </div>
                </div>
              )}
            </div>
      </div>

      {/* Logout Confirmation Modal */}
      <ConfirmationModal
        isOpen={showLogoutConfirm}
        onClose={() => setShowLogoutConfirm(false)}
        onConfirm={handleLogout}
        title="Confirm Logout"
        description="Are you sure you want to logout? You will need to login again to access the platform."
        confirmText="Logout"
        cancelText="Cancel"
        type="warning"
      />

      {/* Notification Detail Modal - Import from NotificationsPage */}
      {selectedNotification && (
        <NotificationDetailModal
          notification={selectedNotification}
          onClose={() => setSelectedNotification(null)}
        />
      )}
    </header>
  )
}

// Notification Detail Modal Component
interface NotificationDetailModalProps {
  notification: Notification
  onClose: () => void
}

function NotificationDetailModal({ notification, onClose }: NotificationDetailModalProps) {
  const markAsRead = useNotificationStore((state) => state.markAsRead)

  return (
    <div
      className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      onClick={onClose}
    >
      <div
        className="bg-background dark:bg-[hsl(var(--auth-card))] rounded-xl border border-border shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="p-6 border-b border-border flex items-start justify-between">
          <div className="flex items-start gap-4 flex-1">
            <div className="flex-shrink-0 mt-1">
              <PulsingDot color={notification.severity} />
            </div>
            <div className="flex-1 min-w-0">
              <h2 className="text-xl font-bold text-foreground">
                {notification.title}
              </h2>
              <div className="flex items-center gap-3 mt-2">
                <span className="text-sm text-muted-foreground">{notification.time}</span>
                <span className="text-sm rounded-full bg-muted px-3 py-1 capitalize">
                  {notification.type}
                </span>
              </div>
            </div>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            className="h-8 w-8 p-0"
          >
            <span className="text-lg">×</span>
          </Button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4">
          <div>
            <h3 className="text-sm font-semibold text-foreground mb-2">Details</h3>
            <div className="bg-muted/50 rounded-lg p-4 space-y-2">
              <div className="flex items-start gap-2">
                <span className="text-sm font-medium text-foreground min-w-24">Event Type:</span>
                <span className="text-sm text-muted-foreground capitalize">{notification.type}</span>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-sm font-medium text-foreground min-w-24">Status:</span>
                <span className="text-sm text-muted-foreground">{notification.read ? 'Read' : 'Unread'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="p-6 border-t border-border flex justify-end gap-2">
          {!notification.read && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                markAsRead(notification.id)
                onClose()
              }}
            >
              Mark as Read
            </Button>
          )}
          <Button variant="default" size="sm" onClick={onClose}>
            Close
          </Button>
        </div>
      </div>
    </div>
  )
}
