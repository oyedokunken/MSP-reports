import { create } from 'zustand'
import { notificationApi } from '@/services/api/notifications'
import { initEcho } from '@/lib/echo'

export interface Notification {
  id: string | number
  title: string
  description?: string
  time: string
  rawDate: string // Added for sorting and grouping
  read: boolean
  type: string
  severity: 'green' | 'yellow' | 'red' | 'blue'
  metadata?: Record<string, unknown>
}

export type SortKey = 'date' | 'severity' | 'type' | 'title'
export type SortDirection = 'asc' | 'desc'

interface NotificationState {
  notifications: Notification[]
  selectedIds: Set<string | number>
  unreadCount: number
  total: number
  isLoading: boolean
  error: string | null
  echo: ReturnType<typeof initEcho> | null

  // Filters & Search
  searchQuery: string
  filters: {
    type: string | 'all'
    severity: string | 'all'
    status: 'all' | 'unread' | 'read'
  }
  sortConfig: {
    key: SortKey
    direction: SortDirection
  }

  // Actions
  fetchNotifications: (page?: number) => Promise<void>
  markAsRead: (id: string | number) => Promise<void>
  markAllAsRead: () => Promise<void>
  deleteNotification: (id: string | number) => Promise<void>
  deleteSelected: () => Promise<void>
  deleteAllRead: () => Promise<void>
  listenForNotifications: (user_id: string | number, token: string) => void
  disconnectEcho: () => void

  // Local UI actions
  setSearchQuery: (query: string) => void
  setFilters: (filters: Partial<NotificationState['filters']>) => void
  setSortConfig: (config: Partial<NotificationState['sortConfig']>) => void
  toggleSelect: (id: string | number) => void
  selectAll: () => void
  deselectAll: () => void

  // Derived Data Helpers
  getFilteredNotifications: () => Notification[]
  getUnreadCount: () => number
  getSelectedCount: () => number
}

// Helper to format date for display
const formatTime = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export const useNotificationStore = create<NotificationState>((set, get) => ({
  notifications: [],
  selectedIds: new Set(),
  unreadCount: 0,
  total: 0,
  isLoading: false,
  error: null,
  echo: null,

  searchQuery: '',
  filters: {
    type: 'all',
    severity: 'all',
    status: 'all'
  },
  sortConfig: {
    key: 'date',
    direction: 'desc'
  },

  fetchNotifications: async (page = 1) => {
    set({ isLoading: true, error: null })
    try {
      const response = await notificationApi.getNotifications(page)
      const mappedNotifications: Notification[] = response.data.map(n => ({
        id: n.id,
        title: n.title,
        description: n.description,
        time: formatTime(n.created_at),
        rawDate: n.created_at,
        read: n.read,
        type: n.type,
        severity: n.severity,
        metadata: n.metadata || undefined
      }))

      set({
        notifications: mappedNotifications,
        unreadCount: response.pagination.unread_count,
        total: response.pagination.total,
        isLoading: false
      })
    } catch (err: unknown) {
      const error = err as { message?: string }
      set({ error: error.message || 'Failed to fetch notifications', isLoading: false })
    }
  },

  markAsRead: async (id: string | number) => {
    const previousNotifications = get().notifications
    const notification = previousNotifications.find(n => n.id === id)
    if (notification?.read) return

    set((state) => ({
      notifications: state.notifications.map((n) =>
        n.id === id ? { ...n, read: true } : n
      ),
      unreadCount: Math.max(0, state.unreadCount - 1)
    }))

    try {
      await notificationApi.markAsRead(id)
    } catch {
      set({ notifications: previousNotifications, unreadCount: get().unreadCount + 1 })
    }
  },

  markAllAsRead: async () => {
    const previousNotifications = get().notifications
    set((state) => ({
      notifications: state.notifications.map((n) => ({ ...n, read: true })),
      unreadCount: 0
    }))

    try {
      await notificationApi.markAllAsRead()
    } catch {
      set({ notifications: previousNotifications, unreadCount: previousNotifications.filter(n => !n.read).length })
    }
  },

  deleteNotification: async (id: string | number) => {
    const previousNotifications = get().notifications
    const notification = previousNotifications.find(n => n.id === id)

    set((state) => {
      const newSelected = new Set(state.selectedIds)
      newSelected.delete(id)
      return {
        notifications: state.notifications.filter((n) => n.id !== id),
        selectedIds: newSelected,
        unreadCount: notification && !notification.read ? state.unreadCount - 1 : state.unreadCount,
        total: state.total - 1
      }
    })

    try {
      await notificationApi.deleteNotification(id)
    } catch {
      set({ notifications: previousNotifications, total: get().total + 1 })
    }
  },

  deleteSelected: async () => {
    const { selectedIds, notifications, unreadCount, total } = get()
    const previousNotifications = [...notifications]

    const remainingNotifications = notifications.filter((n) => !selectedIds.has(n.id))
    const removedUnreadCount = notifications.filter(n => selectedIds.has(n.id) && !n.read).length

    set({
      notifications: remainingNotifications,
      selectedIds: new Set(),
      unreadCount: Math.max(0, unreadCount - removedUnreadCount),
      total: total - selectedIds.size
    })

    try {
      for (const id of selectedIds) {
        await notificationApi.deleteNotification(id)
      }
    } catch {
      set({ notifications: previousNotifications, total: previousNotifications.length })
    }
  },

  deleteAllRead: async () => {
    const previousNotifications = get().notifications
    const readCount = previousNotifications.filter(n => n.read).length

    set((state) => ({
      notifications: state.notifications.filter((n) => !n.read),
      selectedIds: new Set(),
      total: state.total - readCount
    }))

    try {
      await notificationApi.deleteAllRead()
    } catch {
      set({ notifications: previousNotifications, total: previousNotifications.length })
    }
  },

  setSearchQuery: (searchQuery) => set({ searchQuery }),

  setFilters: (newFilters) => set((state) => ({
    filters: { ...state.filters, ...newFilters }
  })),

  setSortConfig: (config) => set((state) => ({
    sortConfig: { ...state.sortConfig, ...config }
  })),

  toggleSelect: (id: string | number) => set((state) => {
    const newSelected = new Set(state.selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    return { selectedIds: newSelected }
  }),

  selectAll: () => set(() => ({
    selectedIds: new Set(get().getFilteredNotifications().map((n) => n.id)),
  })),

  deselectAll: () => set({ selectedIds: new Set() }),

  listenForNotifications: (user_id: string | number, token: string) => {
    const { echo } = get()
    if (echo) return // Already listening

    const echoInstance = initEcho(token)

    // If Echo is disabled, don't try to listen
    if (!echoInstance) {
      console.log('📡 Real-time notifications disabled')
      return
    }

    // Listen for Laravel Notifications on private channel
    echoInstance.private(`App.Models.User.${user_id}`)
      .notification((notification: { id?: string | number; type?: string; title?: string; description?: string; severity?: 'green' | 'yellow' | 'red' | 'blue'; metadata?: Record<string, unknown> }) => {
        const newNotification: Notification = {
          id: notification.id || Date.now(), // Fallback ID
          type: notification.type || 'info',
          title: notification.title || 'New Notification',
          description: notification.description || '',
          severity: notification.severity || 'blue',
          read: false,
          time: formatTime(new Date().toISOString()),
          rawDate: new Date().toISOString(),
          metadata: notification.metadata || {}
        }

        set((state) => ({
          notifications: [newNotification, ...state.notifications],
          unreadCount: state.unreadCount + 1,
          total: state.total + 1
        }))
      })

    set({ echo: echoInstance })
  },

  disconnectEcho: () => {
    const { echo } = get()
    if (echo) {
      echo.disconnect()
      set({ echo: null })
    }
  },

  getUnreadCount: () => get().unreadCount,

  getSelectedCount: () => get().selectedIds.size,

  getFilteredNotifications: () => {
    const { notifications, searchQuery, filters, sortConfig } = get()

    let filtered = [...notifications]

    // 1. Search Query
    if (searchQuery) {
      const query = searchQuery.toLowerCase()
      filtered = filtered.filter(n =>
        n.title.toLowerCase().includes(query) ||
        n.description?.toLowerCase().includes(query)
      )
    }

    // 2. Status Filter
    if (filters.status !== 'all') {
      filtered = filtered.filter(n =>
        filters.status === 'read' ? n.read : !n.read
      )
    }

    // 3. Type Filter
    if (filters.type !== 'all') {
      filtered = filtered.filter(n => n.type === filters.type)
    }

    // 4. Severity Filter
    if (filters.severity !== 'all') {
      filtered = filtered.filter(n => n.severity === filters.severity)
    }

    // 5. Sorting
    filtered.sort((a, b) => {
      let comparison = 0

      switch (sortConfig.key) {
        case 'date':
          comparison = new Date(b.rawDate).getTime() - new Date(a.rawDate).getTime()
          break
        case 'severity': {
          const order = { red: 3, yellow: 2, blue: 1, green: 0 }
          comparison = order[b.severity] - order[a.severity]
          break
        }
        case 'type':
          comparison = a.type.localeCompare(b.type)
          break
        case 'title':
          comparison = a.title.localeCompare(b.title)
          break
      }

      return sortConfig.direction === 'desc' ? comparison : -comparison
    })

    return filtered
  }
}))
