import { create } from 'zustand'

export interface Notification {
  id: number
  title: string
  time: string
  read: boolean
  type: 'sync' | 'report' | 'alert' | 'info'
  severity: 'green' | 'yellow' | 'red' | 'blue'
}

interface NotificationState {
  notifications: Notification[]
  setNotifications: (notifications: Notification[]) => void
  markAsRead: (id: number) => void
  markAllAsRead: () => void
  getUnreadCount: () => number
}

export const useNotificationStore = create<NotificationState>((set, get) => ({
  notifications: [],
  
  setNotifications: (notifications: Notification[]) => set({ notifications }),
  
  markAsRead: (id: number) => set((state) => ({
    notifications: state.notifications.map((n) =>
      n.id === id ? { ...n, read: true } : n
    ),
  })),
  
  markAllAsRead: () => set((state) => ({
    notifications: state.notifications.map((n) => ({ ...n, read: true })),
  })),
  
  getUnreadCount: () => {
    const { notifications } = get()
    return notifications.filter((n) => !n.read).length
  },
}))
