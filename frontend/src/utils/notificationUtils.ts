import type { Notification } from '@/stores/useNotificationStore'

export interface NotificationGroup {
  label: string
  notifications: Notification[]
}

export const groupNotificationsByDate = (notifications: Notification[]): NotificationGroup[] => {
  const groups: Record<string, Notification[]> = {
    'Today': [],
    'Yesterday': [],
    'This Week': [],
    'Earlier': []
  }

  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  const weekAgo = new Date(today)
  weekAgo.setDate(weekAgo.getDate() - 7)

  notifications.forEach(n => {
    const date = new Date(n.rawDate)
    const midnight = new Date(date)
    midnight.setHours(0, 0, 0, 0)

    if (midnight.getTime() === today.getTime()) {
      groups['Today'].push(n)
    } else if (midnight.getTime() === yesterday.getTime()) {
      groups['Yesterday'].push(n)
    } else if (date.getTime() > weekAgo.getTime()) {
      groups['This Week'].push(n)
    } else {
      groups['Earlier'].push(n)
    }
  })

  return Object.entries(groups)
    .map(([label, notifications]) => ({ label, notifications }))
    .filter(group => group.notifications.length > 0)
}
