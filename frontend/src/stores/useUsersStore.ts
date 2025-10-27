import { create } from 'zustand'
import { api } from '@/lib/api'
import type { UserWithPermissions } from '@/pages/users/types'

interface UsersState {
  // Data
  users: UserWithPermissions[]
  isLoading: boolean
  error: string | null
  
  // Modal state
  modals: {
    create: boolean
    edit: boolean
    permissions: boolean
    resetPassword: boolean
    delete: boolean
  }
  selectedUser: UserWithPermissions | null
  
  // Actions
  fetchUsers: () => Promise<void>
  deactivateUser: (userId: number) => Promise<void>
  reactivateUser: (userId: number) => Promise<void>
  
  // Modal actions
  openModal: (modal: keyof UsersState['modals'], user?: UserWithPermissions) => void
  closeModal: (modal: keyof UsersState['modals']) => void
  setSelectedUser: (user: UserWithPermissions | null) => void
}

export const useUsersStore = create<UsersState>((set, get) => ({
  // Initial state
  users: [],
  isLoading: true,
  error: null,
  modals: {
    create: false,
    edit: false,
    permissions: false,
    resetPassword: false,
    delete: false,
  },
  selectedUser: null,

  // Fetch users
  fetchUsers: async () => {
    set({ isLoading: true, error: null })
    try {
      const response = await api.get('/users')
      const data = (response as any).data || response
      const usersList = Array.isArray(data) ? data : data.data || []
      
      set({
        users: usersList,
        isLoading: false,
      })
    } catch (err: any) {
      console.error('Failed to fetch users:', err)
      set({
        error: err.response?.data?.message || err.message || 'Failed to load users',
        isLoading: false,
      })
    }
  },

  // Deactivate user
  deactivateUser: async (userId: number) => {
    try {
      await api.patch(`/users/${userId}/deactivate`)
      await get().fetchUsers()
    } catch (err: any) {
      console.error('Failed to deactivate user:', err)
      throw err
    }
  },

  // Reactivate user
  reactivateUser: async (userId: number) => {
    try {
      await api.patch(`/users/${userId}/reactivate`)
      await get().fetchUsers()
    } catch (err: any) {
      console.error('Failed to reactivate user:', err)
      throw err
    }
  },

  // Modal actions
  openModal: (modal, user) => {
    set(state => ({
      modals: { ...state.modals, [modal]: true },
      selectedUser: user || state.selectedUser,
    }))
  },

  closeModal: modal => {
    set(state => ({
      modals: { ...state.modals, [modal]: false },
    }))
  },

  setSelectedUser: (user: UserWithPermissions | null) => {
    set({ selectedUser: user })
  },
}))
