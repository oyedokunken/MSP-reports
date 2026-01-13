import { useState, useEffect, useMemo } from 'react'
import { motion, AnimatePresence } from 'motion/react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { AnimatedCard, PulsingDot } from '@/components/ui/AnimatedCard'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuTrigger,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu'
import { 
  Bell, 
  CheckCheck, 
  X, 
  Clock, 
  Trash2, 
  CheckSquare, 
  Square,
  Search,
  Filter,
  ArrowUpDown,
  Calendar as CalendarIcon,
  ChevronRight,
  ChevronDown
} from 'lucide-react'
import { useNotificationStore, type Notification, type SortKey } from '@/stores/useNotificationStore'
import { EmptyState } from '@/components/ui/LoadingStates'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import { useToast } from '@/components/ui/Toast'
import { groupNotificationsByDate } from '@/utils/notificationUtils'
import { Badge } from '@/components/ui/badge'

export default function NotificationsPage() {
  const toast = useToast()
  
  // Store state and actions
  const fetchNotifications = useNotificationStore((state) => state.fetchNotifications)
  const isLoading = useNotificationStore((state) => state.isLoading)
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
  const notifications = useNotificationStore((state) => state.notifications)
  const searchQuery = useNotificationStore((state) => state.searchQuery)
  const setSearchQuery = useNotificationStore((state) => state.setSearchQuery)
  const filters = useNotificationStore((state) => state.filters)
  const setFilters = useNotificationStore((state) => state.setFilters)
  const sortConfig = useNotificationStore((state) => state.sortConfig)
  const setSortConfig = useNotificationStore((state) => state.setSortConfig)
  const error = useNotificationStore((state) => state.error)
  const getFilteredNotifications = useNotificationStore((state) => state.getFilteredNotifications)
  
  // Local UI state
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null)
  const [showMarkAllConfirm, setShowMarkAllConfirm] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<string | number | null>(null)
  const [showDeleteAllConfirm, setShowDeleteAllConfirm] = useState(false)
  const [showDeleteSelectedConfirm, setShowDeleteSelectedConfirm] = useState(false)
  const [selectionMode, setSelectionMode] = useState(false)
  const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set())

  // Derived state (re-computed whenever dependencies change)
  const filteredNotifications = useMemo(() => getFilteredNotifications(), [notifications, searchQuery, filters, sortConfig, getFilteredNotifications])
  const groupedNotifications = useMemo(() => groupNotificationsByDate(filteredNotifications), [filteredNotifications])
  const unreadCount = getUnreadCount()
  const selectedCount = getSelectedCount()
  const totalCount = notifications.length

  const activeFilterCount = (filters.status !== 'all' ? 1 : 0) + 
                           (filters.severity !== 'all' ? 1 : 0) + 
                           (filters.type !== 'all' ? 1 : 0)

  // Initialization
  useEffect(() => {
    fetchNotifications()
  }, [fetchNotifications])

  // Keyboard Shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Don't trigger if typing in search
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return

      switch (e.key.toLowerCase()) {
        case 'r':
          if (selectedNotification) markAsRead(selectedNotification.id)
          break
        case 'a':
          selectAll()
          setSelectionMode(true)
          break
        case 'delete':
          if (selectedCount > 0) setShowDeleteSelectedConfirm(true)
          break
        case 'escape':
          if (selectedNotification) setSelectedNotification(null)
          if (selectionMode) {
            setSelectionMode(false)
            deselectAll()
          }
          break
        case '/':
          e.preventDefault()
          document.getElementById('notification-search')?.focus()
          break
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [selectedNotification, selectionMode, selectedCount, selectAll, deselectAll, markAsRead])

  // Handlers
  const handleNotificationClick = (notification: Notification) => {
    markAsRead(notification.id)
    setSelectedNotification(notification)
  }

  const closeDetailModal = () => {
    setSelectedNotification(null)
  }

  const toggleGroup = (label: string) => {
    setCollapsedGroups(prev => {
      const next = new Set(prev)
      if (next.has(label)) next.delete(label)
      else next.add(label)
      return next
    })
  }

  const handleMarkAllAsRead = async () => {
    await markAllAsRead()
    setShowMarkAllConfirm(false)
    toast.success('All notifications marked as read')
  }

  const handleDeleteNotification = async (id: string | number) => {
    await deleteNotification(id)
    setShowDeleteConfirm(null)
    toast.success('Notification deleted')
  }

  const handleDeleteAllRead = async () => {
    await deleteAllRead()
    setShowDeleteAllConfirm(false)
    toast.success('All read notifications deleted')
  }

  const handleDeleteSelected = async () => {
    await deleteSelected()
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
        {/* Header Section */}
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Notifications</h1>
            <p className="mt-2 text-muted-foreground">
              Stay updated with your system activities and events
            </p>
          </div>
          
          <div className="flex flex-wrap gap-2">
            {totalCount > 0 && (
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
            
            {!selectionMode && totalCount > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowDeleteAllConfirm(true)}
                className="gap-2 text-red-600 hover:text-red-700"
              >
                <Trash2 className="h-4 w-4" />
                Clear Read
              </Button>
            )}
          </div>
        </div>

        {/* Controls Bar */}
        <div className="flex flex-col md:flex-row gap-3">
          {/* Filter Dropdown */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" className="w-full md:w-auto h-10 gap-2">
                <Filter className="h-4 w-4" />
                Filters
                {activeFilterCount > 0 && (
                  <Badge variant="default" className="ml-1 h-5 min-w-5 px-1 bg-primary text-primary-foreground">
                    {activeFilterCount}
                  </Badge>
                )}
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
              <DropdownMenuLabel>Status</DropdownMenuLabel>
              <DropdownMenuCheckboxItem
                checked={filters.status === 'unread'}
                onCheckedChange={() => setFilters({ status: filters.status === 'unread' ? 'all' : 'unread' })}
              >
                Unread Only
              </DropdownMenuCheckboxItem>
              <DropdownMenuCheckboxItem
                checked={filters.status === 'read'}
                onCheckedChange={() => setFilters({ status: filters.status === 'read' ? 'all' : 'read' })}
              >
                Read Only
              </DropdownMenuCheckboxItem>

              <DropdownMenuSeparator />
              <DropdownMenuLabel>Severity</DropdownMenuLabel>
              <DropdownMenuRadioGroup value={filters.severity} onValueChange={(v) => setFilters({ severity: v })}>
                <DropdownMenuRadioItem value="all">All Severities</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="red">Critical</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="yellow">Warning</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="blue">Info</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="green">Success</DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>

              <DropdownMenuSeparator />
              <DropdownMenuLabel>Type</DropdownMenuLabel>
              <DropdownMenuRadioGroup value={filters.type} onValueChange={(v) => setFilters({ type: v })}>
                <DropdownMenuRadioItem value="all">All Types</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="sync">Sync</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="report">Report</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="alert">Alert</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="system">System</DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>

              <DropdownMenuSeparator />
              <Button 
                variant="ghost" 
                size="sm" 
                className="w-full justify-start text-xs text-muted-foreground hover:text-foreground"
                onClick={() => {
                  setSearchQuery('')
                  setFilters({ status: 'all', severity: 'all', type: 'all' })
                }}
              >
                Reset All Filters
              </Button>
            </DropdownMenuContent>
          </DropdownMenu>

          {/* Search Box */}
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              id="notification-search"
              placeholder="Search notifications... ( / )"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9 h-10"
            />
          </div>

          {/* Sort Dropdown */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" className="w-full md:w-auto h-10 gap-2">
                <ArrowUpDown className="h-4 w-4" />
                Sort
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
              <DropdownMenuLabel>Sort By</DropdownMenuLabel>
              <DropdownMenuRadioGroup 
                value={sortConfig.key} 
                onValueChange={(v: any) => setSortConfig({ key: v as SortKey })}
              >
                <DropdownMenuRadioItem value="date">Date</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="severity">Severity</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="type">Type</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="title">Title</DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
              
              <DropdownMenuSeparator />
              <DropdownMenuLabel>Order</DropdownMenuLabel>
              <DropdownMenuRadioGroup 
                value={sortConfig.direction} 
                onValueChange={(v: any) => setSortConfig({ direction: v })}
              >
                <DropdownMenuRadioItem value="desc">Newest First / High Priority</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="asc">Oldest First / Low Priority</DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        {/* List Section */}
        <AnimatedCard className="p-0 overflow-hidden min-h-[400px]">
          {isLoading ? (
            <div className="p-20 flex flex-col items-center justify-center gap-4">
              <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-primary"></div>
              <p className="text-muted-foreground animate-pulse">Loading notifications...</p>
            </div>
          ) : error ? (
            <div className="p-12">
              <EmptyState
                icon={Bell}
                title="Failed to load"
                description={error || "Could not connect to the server."}
                actionLabel="Try Again"
                onAction={() => fetchNotifications()}
              />
            </div>
          ) : filteredNotifications.length === 0 ? (
            <div className="p-12">
              <EmptyState
                icon={totalCount > 0 ? Search : Bell}
                title={totalCount > 0 ? "No results found" : "No notifications"}
                description={totalCount > 0 ? "Try adjusting your search or filters" : "You're all caught up! New notifications will appear here."}
                actionLabel={totalCount > 0 ? "Clear Filters" : undefined}
                onAction={totalCount > 0 ? () => {
                  setSearchQuery('')
                  setFilters({ type: 'all', severity: 'all', status: 'all' })
                } : undefined}
              />
            </div>
          ) : (
            <div className="flex flex-col">
              {groupedNotifications.map((group) => (
                <div key={group.label} className="flex flex-col">
                  {/* Group Header */}
                  <div 
                    className="flex items-center gap-2 px-4 py-2 bg-muted/30 sticky top-0 z-10 cursor-pointer hover:bg-muted/50 transition-colors"
                    onClick={() => toggleGroup(group.label)}
                  >
                    {collapsedGroups.has(group.label) ? (
                      <ChevronRight className="h-4 w-4 text-muted-foreground" />
                    ) : (
                      <ChevronDown className="h-4 w-4 text-muted-foreground" />
                    )}
                    <CalendarIcon className="h-3 w-3 text-primary" />
                    <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                      {group.label}
                    </span>
                    <Badge variant="secondary" className="ml-auto text-[10px] h-4">
                      {group.notifications.length}
                    </Badge>
                  </div>

                  {/* Group Items */}
                  {!collapsedGroups.has(group.label) && (
                    <div className="divide-y divide-border">
                      <AnimatePresence initial={false}>
                        {group.notifications.map((notification) => (
                          <motion.div
                            key={notification.id}
                            initial={{ opacity: 0, x: -20 }}
                            animate={{ opacity: 1, x: 0 }}
                            exit={{ opacity: 0, scale: 0.95 }}
                            transition={{ duration: 0.2 }}
                            className={`p-4 hover:bg-muted/40 transition-all group relative border-l-4 ${
                            !notification.read ? 'bg-primary/5' : 'bg-transparent'
                          } ${selectedIds.has(notification.id) ? 'bg-primary/10' : ''} ${
                            notification.severity === 'red' ? 'border-l-red-500' :
                            notification.severity === 'yellow' ? 'border-l-yellow-500' :
                            notification.severity === 'blue' ? 'border-l-blue-500' :
                            'border-l-green-500'
                          }`}
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
                                <h3 className={`text-sm font-semibold truncate ${!notification.read ? 'text-foreground' : 'text-muted-foreground'}`}>
                                  {notification.title}
                                </h3>
                                {!notification.read && (
                                  <span className="flex h-2 w-2 rounded-full bg-blue-500 flex-shrink-0 mt-1 ring-4 ring-blue-500/20" />
                                )}
                              </div>
                              {notification.description && (
                                <p className="text-xs text-muted-foreground mt-1 line-clamp-2 leading-relaxed">
                                  {notification.description}
                                </p>
                              )}
                              <div className="flex items-center gap-3 mt-2">
                                <span className="text-[10px] font-medium text-muted-foreground flex items-center gap-1.5 uppercase tracking-tight">
                                  <Clock className="h-3 w-3" />
                                  {notification.time}
                                </span>
                                <span className="h-1 w-1 rounded-full bg-border" />
                                <span className="text-[10px] font-bold text-muted-foreground px-1.5 py-0.5 rounded bg-muted uppercase tracking-tighter">
                                  {notification.type}
                                </span>
                              </div>
                            </div>
                            {!selectionMode && (
                              <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={(e) => {
                                    e.stopPropagation()
                                    setShowDeleteConfirm(notification.id)
                                  }}
                                  className="h-8 w-8 p-0 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950/20"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </Button>
                              </div>
                            )}
                          </div>
                        </motion.div>
                      ))}
                    </AnimatePresence>
                  </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </AnimatedCard>
      </div>

      {/* Notification Detail Modal */}
      {selectedNotification && (
        <div
          className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
          onClick={closeDetailModal}
        >
          <AnimatedCard
            className="bg-background dark:bg-[hsl(var(--auth-card))] rounded-2xl border border-border shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto p-0 flex flex-col"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="p-6 border-b border-border flex items-start justify-between bg-muted/10">
              <div className="flex items-start gap-4 flex-1">
                <div className="flex-shrink-0 mt-1.5">
                  <PulsingDot color={selectedNotification.severity} />
                </div>
                <div className="flex-1 min-w-0">
                  <h2 className="text-2xl font-black text-foreground tracking-tight leading-tight">
                    {selectedNotification.title}
                  </h2>
                  <div className="flex items-center gap-4 mt-3">
                    <span className="text-sm font-medium text-muted-foreground flex items-center gap-1.5">
                      <Clock className="h-4 w-4" />
                      {selectedNotification.time}
                    </span>
                    <Badge variant="outline" className="text-xs uppercase font-bold px-2 py-0.5">
                      {selectedNotification.type}
                    </Badge>
                  </div>
                </div>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={closeDetailModal}
                className="h-10 w-10 p-0 rounded-full hover:bg-muted"
              >
                <X className="h-6 w-6" />
              </Button>
            </div>

            {/* Modal Content */}
            <div className="p-8 space-y-8">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div className="space-y-1">
                  <p className="text-[10px] font-bold text-muted-foreground uppercase">Severity</p>
                  <p className="text-sm font-semibold capitalize">{selectedNotification.severity}</p>
                </div>
                <div className="space-y-1">
                  <p className="text-[10px] font-bold text-muted-foreground uppercase">Type</p>
                  <p className="text-sm font-semibold capitalize">{selectedNotification.type}</p>
                </div>
                <div className="space-y-1">
                  <p className="text-[10px] font-bold text-muted-foreground uppercase">Status</p>
                  <p className="text-sm font-semibold">{selectedNotification.read ? 'Read' : 'Unread'}</p>
                </div>
                <div className="space-y-1">
                  <p className="text-[10px] font-bold text-muted-foreground uppercase">Recieved At</p>
                  <p className="text-sm font-semibold">{selectedNotification.time}</p>
                </div>
              </div>

              <div className="space-y-3">
                <h3 className="text-sm font-bold text-foreground flex items-center gap-2">
                  <X className="h-4 w-4 text-primary rotate-45" /> Message Details
                </h3>
                <div className="bg-muted/30 rounded-xl p-6 border border-border/50">
                  <p className="text-sm text-foreground leading-relaxed whitespace-pre-wrap">
                    {selectedNotification.description || selectedNotification.title}
                  </p>
                </div>
              </div>

              {selectedNotification.metadata && Object.keys(selectedNotification.metadata).length > 0 && (
                <div className="space-y-3">
                  <h3 className="text-sm font-bold text-foreground">Metadata</h3>
                  <div className="bg-muted/50 rounded-xl p-4 overflow-x-auto">
                    <pre className="text-xs font-mono">
                      {JSON.stringify(selectedNotification.metadata, null, 2)}
                    </pre>
                  </div>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="p-6 border-t border-border flex justify-end gap-3 bg-muted/10">
              <Button
                variant="outline"
                onClick={closeDetailModal}
              >
                Close
              </Button>
              {!selectedNotification.read && (
                <Button
                  variant="default"
                  onClick={() => {
                    markAsRead(selectedNotification.id)
                    toast.success('Marked as read')
                  }}
                  className="gap-2"
                >
                  <CheckCheck className="h-4 w-4" />
                  Mark Read
                </Button>
              )}
              <Button
                variant="outline"
                className="text-red-600 hover:text-red-700 border-red-200 hover:bg-red-50"
                onClick={() => {
                  setShowDeleteConfirm(selectedNotification.id)
                  setSelectedNotification(null)
                }}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          </AnimatedCard>
        </div>
      )}

      {/* Confirmation Modals */}
      <ConfirmationModal
        isOpen={showMarkAllConfirm}
        onClose={() => setShowMarkAllConfirm(false)}
        onConfirm={handleMarkAllAsRead}
        title="Mark All as Read"
        description={`Are you sure you want to mark all ${unreadCount} unread notifications as read?`}
        confirmText="Mark All Read"
        type="info"
      />

      <ConfirmationModal
        isOpen={showDeleteConfirm !== null}
        onClose={() => setShowDeleteConfirm(null)}
        onConfirm={() => {
          if (showDeleteConfirm) {
            handleDeleteNotification(showDeleteConfirm)
          }
        }}
        title="Delete Notification"
        description="Are you sure you want to delete this notification? This action cannot be undone."
        confirmText="Delete"
        type="danger"
      />

      <ConfirmationModal
        isOpen={showDeleteAllConfirm}
        onClose={() => setShowDeleteAllConfirm(false)}
        onConfirm={handleDeleteAllRead}
        title="Clear Read Notifications"
        description="Delete all previously read notifications to declutter your list?"
        confirmText="Clear Read"
        type="danger"
      />

      <ConfirmationModal
        isOpen={showDeleteSelectedConfirm}
        onClose={() => setShowDeleteSelectedConfirm(false)}
        onConfirm={handleDeleteSelected}
        title="Delete Selected"
        description={`Permanently delete the ${selectedCount} selected notifications?`}
        confirmText="Delete Selected"
        type="danger"
      />
    </FloatingLayout>
  )
}
