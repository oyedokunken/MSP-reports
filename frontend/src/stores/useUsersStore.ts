import { create } from 'zustand'
import { userApi } from '@/services/api/users'
import type { UserWithPermissions } from '@/pages/users/types'

interface UsersState {
  // Data
  users: UserWithPermissions[]
  isLoading: boolean
  error: string | null

  // Search and filter
  searchQuery: string
  statusFilter: string // 'all' | 'active' | 'inactive' | 'admin'

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

  // Search and filter actions
  setSearchQuery: (query: string) => void
  setStatusFilter: (filter: string) => void
  getFilteredUsers: () => UserWithPermissions[]

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
  searchQuery: '',
  statusFilter: 'all',
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
      const response = await userApi.getUsers()
      const usersList = response.data as UserWithPermissions[]

      set({
        users: usersList,
        isLoading: false,
      })
    } catch (error) {
      console.error('Failed to fetch users:', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to load users'
      set({
        error: errorMessage,
        isLoading: false,
      })
    }
  },

  // Deactivate user
  deactivateUser: async (userId: number) => {
    try {
      await userApi.deactivateUser(userId)
      await get().fetchUsers()
    } catch (error) {
      console.error('Failed to deactivate user:', error)
      throw error
    }
  },

  // Reactivate user
  reactivateUser: async (userId: number) => {
    try {
      await userApi.reactivateUser(userId)
      await get().fetchUsers()
    } catch (error) {
      console.error('Failed to reactivate user:', error)
      throw error
    }
  },

  // Search and filter actions
  setSearchQuery: (query: string) => set({ searchQuery: query }),

  setStatusFilter: (filter: string) => set({ statusFilter: filter }),

  getFilteredUsers: () => {
    const { users, searchQuery, statusFilter } = get()

    let filtered = users

    // Apply search filter
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase()
      filtered = filtered.filter(user =>
        user.name.toLowerCase().includes(query) ||
        user.email.toLowerCase().includes(query) ||
        user.role?.name.toLowerCase().includes(query)
      )
    }

    // Apply status filter
    if (statusFilter !== 'all') {
      if (statusFilter === 'admin') {
        filtered = filtered.filter(user => user.role?.name === 'Admin')
      } else {
        filtered = filtered.filter(user => user.status === statusFilter)
      }
    }

    return filtered
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
