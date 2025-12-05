import { create } from 'zustand'

export interface Notification {
  id: number
  title: string
  description?: string
  time: string
  read: boolean
  type: 'sync' | 'report' | 'alert' | 'info' | 'user' | 'customer' | 'schedule' | 'system'
  severity: 'green' | 'yellow' | 'red' | 'blue'
  metadata?: Record<string, any>
}

interface NotificationState {
  notifications: Notification[]
  selectedIds: Set<number>
  setNotifications: (notifications: Notification[]) => void
  addNotification: (notification: Omit<Notification, 'id'>) => void
  markAsRead: (id: number) => void
  markAllAsRead: () => void
  deleteNotification: (id: number) => void
  deleteSelected: () => void
  deleteAllRead: () => void
  toggleSelect: (id: number) => void
  selectAll: () => void
  deselectAll: () => void
  getUnreadCount: () => number
  getSelectedCount: () => number
}

let notificationIdCounter = 100

export const useNotificationStore = create<NotificationState>((set, get) => ({
  notifications: [],
  selectedIds: new Set(),
  
  setNotifications: (notifications: Notification[]) => set({ notifications, selectedIds: new Set() }),
  
  addNotification: (notification: Omit<Notification, 'id'>) => set((state) => ({
    notifications: [
      { ...notification, id: ++notificationIdCounter },
      ...state.notifications,
    ],
  })),
  
  markAsRead: (id: number) => set((state) => ({
    notifications: state.notifications.map((n) =>
      n.id === id ? { ...n, read: true } : n
    ),
  })),
  
  markAllAsRead: () => set((state) => ({
    notifications: state.notifications.map((n) => ({ ...n, read: true })),
  })),
  
  deleteNotification: (id: number) => set((state) => {
    const newSelected = new Set(state.selectedIds)
    newSelected.delete(id)
    return {
      notifications: state.notifications.filter((n) => n.id !== id),
      selectedIds: newSelected,
    }
  }),
  
  deleteSelected: () => set((state) => ({
    notifications: state.notifications.filter((n) => !state.selectedIds.has(n.id)),
    selectedIds: new Set(),
  })),
  
  deleteAllRead: () => set((state) => ({
    notifications: state.notifications.filter((n) => !n.read),
    selectedIds: new Set(),
  })),
  
  toggleSelect: (id: number) => set((state) => {
    const newSelected = new Set(state.selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    return { selectedIds: newSelected }
  }),
  
  selectAll: () => set((state) => ({
    selectedIds: new Set(state.notifications.map((n) => n.id)),
  })),
  
  deselectAll: () => set({ selectedIds: new Set() }),
  
  getUnreadCount: () => {
    const { notifications } = get()
    return notifications.filter((n) => !n.read).length
  },
  
  getSelectedCount: () => {
    return get().selectedIds.size
  },
}))
