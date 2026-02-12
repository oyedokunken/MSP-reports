import { create } from 'zustand'

export interface AIWatcherMessage {
  id: string
  title: string
  content: string
  timestamp: Date
  eventType: string
  docPath?: string | null
  docLabel?: string | null
  severity: string
  read: boolean
}

interface AIWatcherState {
  messages: AIWatcherMessage[]
  unreadCount: number
  
  addMessage: (message: Omit<AIWatcherMessage, 'id' | 'timestamp' | 'read'>) => void
  markAllRead: () => void
  clearMessages: () => void
  getLatestUnread: () => AIWatcherMessage | null
}

export const useAIWatcherStore = create<AIWatcherState>((set, get) => ({
  messages: [],
  unreadCount: 0,

  addMessage: (message) => {
    const newMessage: AIWatcherMessage = {
      ...message,
      id: `ai-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
      timestamp: new Date(),
      read: false,
    }

    set((state) => ({
      messages: [newMessage, ...state.messages].slice(0, 50), // Keep last 50
      unreadCount: state.unreadCount + 1,
    }))
  },

  markAllRead: () => {
    set((state) => ({
      messages: state.messages.map((m) => ({ ...m, read: true })),
      unreadCount: 0,
    }))
  },

  clearMessages: () => set({ messages: [], unreadCount: 0 }),

  getLatestUnread: () => {
    const { messages } = get()
    return messages.find((m) => !m.read) || null
  },
}))
